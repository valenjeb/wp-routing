<?php

declare(strict_types=1);

namespace Devly\WP\Routing;

use Closure;
use Devly\DI\Container;
use Devly\DI\Contracts\IContainer;
use Devly\DI\Exceptions\ContainerException;
use Devly\Exceptions\RouteNotFoundException;
use Devly\WP\Routing\Contracts\IRequest;
use Devly\WP\Routing\Contracts\IRoute;
use Devly\WP\Routing\Responses\RedirectResponse;
use Devly\WP\Routing\Routes\Ajax;
use Devly\WP\Routing\Routes\Query;
use Devly\WP\Routing\Routes\Route;
use LogicException;
use Nette\Http\IRequest as HttpRequestContract;
use Nette\Http\Request as HttpRequest;
use Nette\Http\RequestFactory as HttpRequestFactory;
use RuntimeException;
use WP;

use function array_merge;
use function md5;
use function serialize;
use function sprintf;

class Router
{
    protected IContainer $container;
    /** @var Routes<Route> */
    protected Routes $webRoutes;
    /** @var Routes<Ajax> */
    protected Routes $ajaxRoutes;
    /** @var Routes<Query> */
    protected Routes $queryRoutes;
    protected bool $routeProcessed = false;
    /** @var bool Whether all requests should be processed */
    protected static bool $handleAllRequests = false;

    public function __construct(?IContainer $container = null)
    {
        $this->container   = $container ?? new Container([], true, true);
        $this->webRoutes   = new Routes(); // @phpstan-ignore-line
        $this->ajaxRoutes  = new Routes(); // @phpstan-ignore-line
        $this->queryRoutes = new Routes();

        add_action('init', [$this, 'registerWebRoutes'], 1000);
        add_action('parse_request', [$this, 'handleRequest'], 10);
        add_action('admin_init', [$this, 'handleAjaxRequest'], 1000);
    }

    /**
     * @param callable|class-string<T>|string|array<class-string<T>|T, string>|null $callback
     *
     * @template T of object
     */
    public function addRoute(string $pattern, $callback = null): Route
    {
        return $this->webRoutes->addRoute($pattern, $callback);
    }

    /**
     * @param callable|class-string<T>|string|array<class-string<T>|T, string>|null $callback
     *
     * @template T of object
     */
    public function web(string $pattern, $callback = null): Route
    {
        return $this->addRoute($pattern, $callback);
    }

    /**
     * @param array<array{key: string, operator: string, value: mixed}>        $args
     * @param callable|class-string<T>|string|array<class-string<T>|T, string> $callback
     *
     * @template T of object
     */
    public function query(array $args = [], $callback = null): Query
    {
        return $this->queryRoutes->addQueryRoute($args, $callback);
    }

    public function redirect(string $path, string $target, int $status = 302): Route
    {
        return $this->addRoute($path)
            ->middleware(static fn (IRequest $req, Closure $next) => new RedirectResponse($target, $status));
    }

    public function permanentRedirect(string $path, string $target): Route
    {
        return $this->redirect($path, $target, 301);
    }

    /**
     * @param callable|class-string<T>|string|array<class-string<T>|T, string> $callback
     *
     * @template T of object
     */
    public function ajax(string $action, $callback): Ajax
    {
        return $this->ajaxRoutes->addAjaxRoute($action, $callback);
    }

    /** @throws RouteNotFoundException */
    public function getRoute(string $name): IRoute
    {
        try {
            return $this->getWebRoute($name);
        } catch (RouteNotFoundException $e) {
        }

        try {
            return $this->getQueryRoute($name);
        } catch (RouteNotFoundException $e) {
        }

        return $this->getAjaxRoute($name);
    }

    /** @throws RouteNotFoundException */
    public function getWebRoute(string $name): Route
    {
        return $this->webRoutes->get($name);
    }

    /** @throws RouteNotFoundException */
    public function getAjaxRoute(string $name): Ajax
    {
        return $this->ajaxRoutes->get($name);
    }

    /** @throws RouteNotFoundException */
    public function getQueryRoute(string $name): Query
    {
        return $this->queryRoutes->get($name);
    }

    /** @throws RouteNotFoundException if the provided route name does not exist. */
    public function removeRoute(string $name): void
    {
        try {
            $this->webRoutes->forget($name);

            return;
        } catch (RouteNotFoundException $e) {
        }

        try {
            $this->queryRoutes->forget($name);

            return;
        } catch (RouteNotFoundException $e) {
        }

        $this->ajaxRoutes->forget($name);
    }

    /**
     * Edit a route
     *
     * @param string                $name         The route name to edit.
     * @param Closure(IRoute): void $editCallback An edit callback following signature
     *                                            `Closure(IRoute): void`.
     *
     * @throws RouteNotFoundException if the provided route name does not exist.
     */
    public function editRoute(string $name, Closure $editCallback): void
    {
        $route = $this->getRoute($name);

        $editCallback($route);
    }

    public function hasRoute(string $name): bool
    {
        try {
            $this->getRoute($name);
        } catch (RouteNotFoundException $e) {
            return false;
        }

        return true;
    }

    /**
     * Announce to other plugins that it's time to create rules
     * Action: init
     *
     * @internal
     *
     * @uses do_action() Calls 'devly_router_alter_routes'
     */
    public function registerWebRoutes(): void
    {
        if (! doing_action('init')) {
            throw new LogicException(sprintf(
                'Method "%s::%s" is meant to be used internally within the WordPress init hook.',
                self::class,
                __METHOD__
            ));
        }

        do_action(Hooks::ACTION_ALTER_ROUTES, $this);

        $rules = $this->getRewriteRules();

        add_filter('rewrite_rules_array', fn (array $oldRules) => $this->announceWebRoutes($oldRules, $rules), 10, 1);
        add_filter('query_vars', fn (array $vars) => $this->announceQueryVars($vars), 10, 1);

        if ($this->hash($rules) === get_option(Utility::ROUTE_CACHE_OPTION)) {
            return;
        }

        $this->flushRewriteRules();
    }

    /**
     * Update WordPress's rewrite rules array with registered routes
     * Filter: rewrite_rules_array
     *
     * @param array<string, string> $oldRules
     * @param array<string, string> $rules
     *
     * @return array<string, string>
     */
    protected function announceWebRoutes(array $oldRules, array $rules): array
    {
        update_option(Utility::ROUTE_CACHE_OPTION, $this->hash($rules));

        return array_merge($rules, $oldRules);
    }

    /**
     * Get the array of rewrite rules from all registered routes
     *
     * @return array<string, string>
     */
    protected function getRewriteRules(): array
    {
        $rules = [];
        foreach ($this->webRoutes as $route) {
            $rules = array_merge($rules, $route->getRewriteRule());
        }

        return $rules;
    }

    /**
     * Add all query vars from registered routes to WP's recognized query vars
     *
     * @param array<string, mixed> $vars
     *
     * @return array<string, mixed>
     */
    protected function announceQueryVars(array $vars): array
    {
        return array_merge($vars, $this->getQueryVars());
    }

    /**
     * Get an array of all query vars used by registered routes
     *
     * @return string[]
     */
    protected function getQueryVars(): array
    {
        $vars = [];
        foreach ($this->webRoutes as $route) {
            $vars = array_merge($vars, $route->getQueryVarKeys());
        }

        $vars[] = Utility::QUERY_VAR;

        return $vars;
    }

    /**
     * Create a hash of the registered rewrite rules
     *
     * @param array<string, string> $rules
     */
    protected function hash(array $rules): string
    {
        return md5(serialize($rules));
    }

    /**
     * Tell WordPress to flush its rewrite rules
     */
    protected function flushRewriteRules(): void
    {
        flush_rewrite_rules();
    }

    /** @internal */
    public function handleRequest(WP $wp): void
    {
        if (! doing_action('parse_request')) {
            throw new LogicException(sprintf(
                'Method \'%s::%s\' is meant to be used internally within the WordPress \'parse_request\' hook.',
                self::class,
                __METHOD__
            ));
        }

        if (is_admin() || $this->routeProcessed) {
            return;
        }

        if (isset($wp->query_vars[Utility::POST_TYPE])) {
            wp_redirect(home_url(), 303);

            exit;
        }

        $this->routeProcessed = true;

        $route = $this->identifyRoute($wp);

        if (! $route) {
            return;
        }

        $this->ensureRequiredServices();

        $request = $this->getRequest();

        if (!$route instanceof Route) {
            throw new RuntimeException(sprintf(
                'Invalid route matched. Web route expected to be a "%s" instance, Provided %s.',
                Route::class,
                get_class($route)
            ));
        }

        $vars = $route->getParsedQueryVars();

        if (! empty($vars)) {
            $request->setQueryVar($vars);
        }

        $request->setRoute($route);

        $route->run($this->container);
    }

    /** @internal */
    public function handleAjaxRequest(): void
    {
        if (! doing_action('admin_init')) {
            throw new LogicException(sprintf(
                'Method \'%s::%s\' is meant to be used internally within the WordPress \'admin_init\' hook.',
                self::class,
                __METHOD__
            ));
        }

        if (! wp_doing_ajax() || ! isset($_REQUEST['action'])) {
            return;
        }

        try {
            $route = $this->ajaxRoutes->get($_REQUEST['action']);
        } catch (RouteNotFoundException $e) {
            return;
        }

        if (! $route instanceof Ajax) {
            throw new LogicException(sprintf('Route "%s" is not an Ajax route.', $route->name()));
        }

        $this->ensureRequiredServices();

        $this->getRequest()->setRoute($route);

        $route->run($this->container);
    }

    /** @internal */
    public function identifyRoute(WP $wp): ?IRoute
    {
        $routeName = $wp->query_vars[Utility::QUERY_VAR] ?? null;

        if (! $routeName) {
            foreach ($this->queryRoutes as $route) {
                if ($route->isSatisfied($wp)) { // @phpstan-ignore-line
                    return $route;
                }
            }

            if (self::$handleAllRequests) {
                return $this->createGeneralWebRoute($wp->request);
            }

            return null;
        }

        unset($wp->query_vars[Utility::QUERY_VAR]);

        try {
            $route = $this->webRoutes->get($routeName);
            if ($route instanceof Route) {
                return $route;
            }

            throw new RuntimeException(sprintf(
                'The route ("%s") matched for the current request is not an instance of web route.',
                $routeName
            ));
        } catch (RouteNotFoundException $e) {
            return $this->createGeneralWebRoute($wp->request);
        }
    }

    protected function createGeneralWebRoute(string $path): Route
    {
        $path = $path === '' ? '/' : $path;

        return new Route($path, null);
    }

    protected function ensureRequiredServices(): void
    {
        if (! $this->container->has(IRequest::class)) {
            $this->container->alias(IRequest::class, Request::class);
        }

        if ($this->container->has(HttpRequestContract::class)) {
            return;
        }

        try {
            $this->container->defineShared(HttpRequestFactory::class, HttpRequest::class)->return('@fromGlobals');
        } catch (ContainerException $e) {
        }

        $this->container->alias(HttpRequestContract::class, HttpRequest::class);
        $this->container->alias('http.request', HttpRequest::class);
    }

    protected function getRequest(): IRequest
    {
        return $this->container->get(IRequest::class);
    }

    /**
     * Set whether all requests should be handled by the router.
     *
     * By default, only configured route paths are handled.
     */
    public static function handleAllRequests(bool $all = true): void
    {
        self::$handleAllRequests = $all;
    }

    /**
     * Determines whether all requests should be handled by the router.
     */
    public static function isHandleAllRequests(): bool
    {
        return apply_filters(Hooks::FILTER_HANDLE_ALL_REQUESTS, self::$handleAllRequests);
    }
}
