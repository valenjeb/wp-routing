<?php

declare(strict_types=1);

namespace Devly\WP\Routing\Routes;

use Devly\DI\Contracts\IContainer;
use Devly\Exceptions\AbortException;
use Devly\Utils\Pipeline;
use Devly\Utils\Str;
use Devly\WP\Routing\Contracts\IRequest;
use Devly\WP\Routing\Contracts\IResponse;
use Devly\WP\Routing\Utility;
use Nette\Http\Response;
use RuntimeException;
use Throwable;

use function add_filter;
use function array_filter;
use function array_keys;
use function array_map;
use function call_user_func;
use function implode;
use function is_array;
use function is_callable;
use function is_int;
use function is_object;
use function is_string;
use function ltrim;
use function preg_match;
use function preg_match_all;
use function rtrim;
use function sprintf;
use function str_replace;

use const ARRAY_FILTER_USE_KEY;
use const PREG_UNMATCHED_AS_NULL;

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
    protected bool $patternParsed = false;
    protected IContainer $container;
    protected IResponse $response;

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
        return [$this->getRegexPattern() => $this->generateRewriteRule()];
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

    protected function getRegexPattern(): string
    {
        $pattern = $this->pattern;

        preg_match_all($this->patternParamRegex, $pattern, $matches);

        foreach ($matches[0] as $match) {
            $key = str_replace(['{', '}'], [''], $match);

            $isOptional = Str::endsWith($key, '?');

            if ($isOptional) {
                $key = Str::replace('?', '', $key);
            }

            $regex = $this->parseParamRegex($key, $isOptional);
            $match = $isOptional ? '/' . $match : $match;

            $pattern = str_replace($match, $regex, $pattern);
        }

        return $pattern . '/?$';
    }

    public function getParametrizedRegexPattern(): string
    {
        $pattern = $this->pattern;

        preg_match_all($this->patternParamRegex, $pattern, $matches);

        foreach ($matches[0] as $match) {
            $key = str_replace(['{', '}'], [''], $match);

            $isOptional = Str::endsWith($key, '?');

            if ($isOptional) {
                $key = Str::replace('?', '', $key);
            }

            $regex = $this->parameterizeRegex($key, $isOptional);
            $regex = $isOptional ? $regex : '\/' . $regex;

            $pattern = str_replace('/' . $match, $regex, $pattern);
        }

        return $pattern . '\/?$';
    }

    /** @return array<string, string> */
    public function getParamsFromPattern(string $pattern): array
    {
        $regex = $this->getParametrizedRegexPattern();
        preg_match('/' . $regex . '/', $pattern, $matches, PREG_UNMATCHED_AS_NULL);

        return array_filter($matches, static function ($key) {
            return is_string($key);
        }, ARRAY_FILTER_USE_KEY);
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

        $this->container = $container;

        add_action('template_redirect', [$this, 'execute']);
    }

    /** @internal */
    public function execute(): void
    {
        $request    = $this->container[IRequest::class];
        $controller = $this->controller() ?? $request->matchController();

        if ($controller === null) {
            return;
        }

        $urlParams = $this->getParamsFromPattern($request->wp()->request);
        if (! empty($urlParams)) {
            $this->setParameters($urlParams);
        }

        $params = $request->getQueryVars() + $this->getParameters();

        try {
            $controller = $this->normalizeController($controller);

            if (is_callable($controller)) {
                $response = $this->container->call($controller, $params);
            } else {
                [$class, $method] = $controller;

                $object = is_object($class) ? $class : $this->container->makeWith($class, $params);

                $response = $this->container->call([$object, $method], $params);
            }
        } catch (Throwable $e) {
            throw new RuntimeException(sprintf('An error occurred during route "%s" execution.', $this->name()), 0, $e);
        }

        if (empty($response)) {
            return;
        }

        try {
            $this->response = $this->ensureResponse($response);
        } catch (AbortException $e) {
            throw new RuntimeException(sprintf('Route "%s" returned invalid response.', $this->name()));
        }

        add_filter('template_include', [$this, 'sendTemplate'], 10);
    }

    /** @internal */
    public function sendTemplate(string $template): string
    {
        if (! isset($this->response)) {
            return $template;
        }

        $this->response->send($this->container[IRequest::class], $this->container[Response::class]);

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

    private function parameterizeRegex(string $key, bool $isOptional): string
    {
        $regex = $this->mapping[$key] ?? '[-\w]+';

        return $isOptional ? '(?:\/(?P<' . $key . '>' . $regex . '))' : '(?P<' . $key . '>' . $regex . ')';
    }
}
