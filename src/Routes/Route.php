<?php

declare(strict_types=1);

namespace Devly\WP\Routing\Routes;

use Devly\DI\Contracts\IContainer;
use Devly\Exceptions\AbortException;
use Devly\Utils\Pipeline;
use Devly\Utils\Str;
use Devly\WP\Routing\Contracts\IRequest;
use Devly\WP\Routing\Utility;
use Nette\Http\Response;
use RuntimeException;
use Throwable;

use function add_filter;
use function array_keys;
use function array_map;
use function call_user_func;
use function implode;
use function is_array;
use function is_callable;
use function is_int;
use function is_object;
use function ltrim;
use function preg_match_all;
use function rtrim;
use function sprintf;
use function str_replace;

class Route extends RouteBase
{
    protected string $patternParamRegex = '/(\{[a-zA-Z_\-\?]+})/';
    /**
     * Pattern mapping
     *
     * @var array<string, string>
     */
    protected array $mapping = [];
    /**
     * List of query vars used by the route
     *
     * @var array<string, string|int>
     */
    protected array $queryVars = [];
    /**
     * List of query vars callbacks used by the route
     *
     * @var array<string, callable(): mixed>
     */
    protected array $queryVarCallbacks = [];
    protected string $rewriteRule;
    protected string $regex;
    protected bool $patternParsed = false;

    /** @param callable|class-string|string|array<class-string|object, string>|null $callback */
    public function __construct(string $pattern, $callback = null)
    {
        $this->pattern    = $pattern;
        $this->controller = $callback;
    }

    /** @param array<string, string|int|callable(): mixed> $queryVars */
    public function setQueryVars(array $queryVars): self
    {
        $this->queryVars         = [];
        $this->queryVarCallbacks = [];
        foreach ($queryVars as $key => $value) {
            $this->setQueryVar($key, $value);
        }

        return $this;
    }

    /** @param string|int|callable(): mixed $value */
    public function setQueryVar(string $key, $value): self
    {
        if (is_callable($value)) {
            $this->queryVarCallbacks[$key] = $value;
        } else {
            $this->queryVars[$key] = $value;
        }

        return $this;
    }

    /** @return string[] All query vars used by this route */
    public function getQueryVarKeys(): array
    {
        return array_keys($this->queryVars + $this->queryVarCallbacks);
    }

    /** @return array<string, string|int> All query vars used by this route */
    public function getQueryVars(): array
    {
        return $this->queryVars;
    }

    /** @param string|array<string, string> $name */
    public function where($name, ?string $regex = null): self
    {
        if (is_array($name)) {
            $this->mapping += $name;
        } else {
            $this->mapping[$name] = $regex;
        }

        return $this;
    }

    public function whereAlpha(string $name): self
    {
        return $this->where($name, '[a-zA-Z]+');
    }

    public function whereNumeric(string $name): self
    {
        return $this->where($name, '[0-9]+');
    }

    public function whereAlphaNumeric(string $name): self
    {
        return $this->where($name, '[a-zA-Z0-9]+');
    }

    /** @return array<string, string> */
    public function getRewriteRule(): array
    {
        return [$this->parsePattern() => $this->generateRewriteRule()];
    }

    /**
     * Generate the WP rewrite rule for this route
     */
    protected function generateRewriteRule(): string
    {
        if (isset($this->rewriteRule)) {
            return $this->rewriteRule;
        }

        $rule = 'index.php?';
        $vars = [];

        foreach ($this->getQueryVars() as $var => $value) {
            if (is_int($value)) {
                $vars[] = $var . '=' . $this->pregindex($value);
            } else {
                $vars[] = $var . '=' . $value;
            }
        }

        $vars[] = Utility::QUERY_VAR . '=' . $this->name();

        return $this->rewriteRule = $rule . implode('&', $vars);
    }

    /**
     * Pass an integer through $wp_rewrite->preg_index()
     */
    protected function pregindex(int $int): string
    {
        $rewrite = $GLOBALS['wp_rewrite'];

        $rewrite->matches = 'matches'; // because it may not be set, yet

        return $rewrite->preg_index($int);
    }

    protected function parsePattern(): string
    {
        $pattern = $this->pattern;

        preg_match_all($this->patternParamRegex, $pattern, $matches);

        foreach ($matches[0] as $i => $match) {
            $key = str_replace(['{', '}'], [''], $match);

            $isOptional = Str::endsWith($key, '?');

            if ($isOptional) {
                $key = Str::replace('?', '', $key);
            }

            $this->setQueryVar($key, $i + 1);

            $regex = $this->parseParamRegex($key, $isOptional);
            $match = $isOptional ? '/' . $match : $match;

            $pattern = str_replace($match, $regex, $pattern);
        }

        return $pattern . '/?$';
    }

    /** @inheritdoc  */
    public function url(array $args = []): string
    {
        $pattern = $this->pattern;

        foreach ($args as $key => $value) {
            $pattern = Str::replace(['{' . $key . '}', '{' . $key . '?}'], $value, $pattern);
        }

        if (preg_match_all($this->patternParamRegex, $pattern, $matches) !== 0) {
            $errors = [];
            foreach ($matches[0] as $param) {
                if (! Str::contains($param, '?')) {
                    $errors[] = $param;

                    continue;
                }

                $pattern = Str::replace($param, '', $pattern);
            }

            if (! empty($errors)) {
                throw new RuntimeException(sprintf(
                    'Missing argument: %s.',
                    str_replace(['{', '}'], '', implode(', ', $errors))
                ));
            }
        }

        return rtrim(trailingslashit(home_url()) . ltrim($pattern, '/'), '/');
    }

    public function run(IContainer $container): void
    {
        $response = Pipeline::create($container)
            ->send($container[IRequest::class])
            ->through($this->middleware())
            ->then(static fn () => null);

        if (! empty($response)) {
            try {
                $response = $this->ensureResponse($response);
            } catch (AbortException $e) {
                throw new RuntimeException(sprintf('Route "%s" returned invalid response.', $this->name()));
            }

            $response->send($container[IRequest::class], $container[Response::class]);
        }

        $request = $container[IRequest::class];

        if (empty($request->getQueryVars()) && ! empty($request->wp()->request !== '')) {
            Utility::registerPlaceholderPostType();
            $request->setQueryVar(Utility::getPlaceholderPageQueryVars());
        }

        add_filter('template_include', function (string $template) use ($container) {
            return $this->execute($template, $container);
        });
    }

    protected function execute(string $template, IContainer $container): string
    {
        $request    = $container[IRequest::class];
        $controller = $this->matchController($request);
//        echo '<pre>'; var_dump($GLOBALS['wp_query']); echo '</pre>';

        if ($controller === null) {
            return $template;
        }

        $params = $request->getQueryVars() + $this->getParameters();

        try {
            $controller = $this->normalizeController($controller);

            if (is_callable($controller)) {
                $response = $container->call($controller, $params);
            } else {
                [$class, $method] = $controller;

                $object = is_object($class) ? $class : $container->makeWith($class, $params);

                $response = $container->call([$object, $method], $params);
            }
        } catch (Throwable $e) {
            throw new RuntimeException(
                sprintf('An error occurred during route "%s" execution.', $this->name()),
                $e->getCode(),
                $e
            );
        }

        if (empty($response)) {
            return $template;
        }

        try {
            $response = $this->ensureResponse($response);
        } catch (AbortException $e) {
            throw new RuntimeException(sprintf('Route "%s" returned invalid response.', $this->name()));
        }

        $response->send($container[IRequest::class], $container[Response::class]);

        return Utility::EMPTY_PLACEHOLDER;
    }

    protected function parseParamRegex(string $key, bool $isOptional): string
    {
        $regex = $this->mapping[$key] ?? '[-\w]+';

        return $isOptional ? '(?:/(' . $regex . '))?' : '(' . $regex . ')';
    }

    /**
     * Executes all query var callbacks and returns array of results.
     *
     * @return array<string, mixed>
     */
    public function getParsedQueryVars(): array
    {
        return array_map(static fn (callable $cb) => call_user_func($cb), $this->queryVarCallbacks);
    }

    /** @return array<class-string|object, string>|callable|string|null */
    protected function matchController(IRequest $request)
    {
        if (is_404()) {
            return $request->getControllerFinder()->get404Controller();
        }

        return $this->controller() ?: $request->getControllerFinder()->matchController();
    }
}
