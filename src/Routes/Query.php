<?php

declare(strict_types=1);

namespace Devly\WP\Routing\Routes;

use Devly\DI\Contracts\IContainer;
use Devly\Exceptions\AbortException;
use Devly\Utils\Str;
use Devly\WP\Routing\Concerns\CanSendTemplate;
use Devly\WP\Routing\Contracts\IRequest;
use Devly\WP\Routing\Contracts\IResponse;
use Nette\Http\Response;
use RuntimeException;
use Throwable;
use WP;

use function func_num_args;
use function in_array;
use function is_callable;
use function is_numeric;
use function is_object;
use function json_encode;
use function sprintf;
use function urldecode;

class Query extends RouteBase
{
    use CanSendTemplate;

    public const OPERATOR_EQUALS       = '=';
    public const OPERATOR_NOT_EQUALS   = '!=';
    public const OPERATOR_IN           = 'in';
    public const OPERATOR_NOT_IN       = '!in';
    public const OPERATOR_LIKE         = 'like';
    public const OPERATOR_CONTAINS     = 'contains';
    public const OPERATOR_NOT_CONTAINS = '!contains';
    public const OPERATOR_STARTS       = 'starts';
    public const OPERATOR_NOT_STARTS   = '!starts';
    public const OPERATOR_ENDS         = 'ends';
    public const OPERATOR_NOT_ENDS     = '!ends';

    /** @var array<array{key: string, operator: string, value: mixed}> */
    protected array $wheres;

    protected IContainer $container;
    protected IResponse $response;

    /**
     * @param array<array{key: string, operator: string, value: mixed}>            $args
     * @param callable|class-string|string|array<class-string|object, string>|null $callback
     */
    public function __construct(array $args = [], $callback = null)
    {
        $this->wheres     = $args;
        $this->controller = $callback;
    }

    public function getPattern(): string
    {
        return json_encode($this->wheres);
    }

    /**
     * @param string|mixed $operatorOrValue
     * @param mixed        $value
     */
    public function where(string $key, $operatorOrValue, $value = null): self
    {
        if (func_num_args() === 2) {
            $value           = $operatorOrValue;
            $operatorOrValue = '=';
        }

        $this->wheres[] = [
            'key' => $key,
            'operator' => $operatorOrValue,
            'value' => $value,
        ];

        return $this;
    }

    /**
     * @param string|mixed $operatorOrValue
     * @param mixed        $value
     */
    public function wherePath($operatorOrValue, $value = null): self
    {
        if (func_num_args() === 1) {
            $value           = $operatorOrValue;
            $operatorOrValue = '=';
        }

        return $this->where('request', $operatorOrValue, $value);
    }

    public function whereHomepage(): self
    {
        return $this->wherePath('')->where('s', '=', null);
    }

    public function whereSearch(): self
    {
        return $this->where('s', '!=', null);
    }

    public function whereSingle(?string $type = null): self
    {
        $this->where('post_type', self::OPERATOR_EQUALS, $type);

        return $this->where('name', self::OPERATOR_NOT_EQUALS, null);
    }

    public function whereArchive(string $postType): self
    {
        $this->where('name', self::OPERATOR_EQUALS, null);

        return $this->where('post_type', self::OPERATOR_EQUALS, $postType);
    }

    public function whereCategory(string $name): self
    {
        return $this->where('category_name', self::OPERATOR_EQUALS, $name);
    }

    public function whereTaxonomy(string $name, ?string $term = null): self
    {
        if ($term === null) {
            return $this->where($name, self::OPERATOR_NOT_EQUALS, null);
        }

        return $this->where($name, self::OPERATOR_EQUALS, $term);
    }

    public function wherePageName(string $operatorOrValue, ?string $name = null): self
    {
        if (! $name) {
            $name            = $operatorOrValue;
            $operatorOrValue = '=';
        }

        return $this->where('pagename', $operatorOrValue, $name);
    }

    public function isSatisfied(WP $request): bool
    {
        foreach ($this->wheres as $condition) {
            $key      = $condition['key'];
            $operator = $condition['operator'];
            $expected = $condition['value'];

            if ($key === 'request') {
                if (! $this->handleOperator($request->request, $operator, $expected)) {
                    return false;
                }

                continue;
            }

            $actual = $request->query_vars[$key] ?? null;

            if ($actual !== null) {
                $actual = urldecode($actual);
            }

            $result = $this->handleOperator($actual, $operator, $expected);

            if ($result === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param mixed $actual
     * @param mixed $expected
     */
    protected function handleOperator($actual, string $operator, $expected): bool
    {
        $actual = is_numeric($actual) ? (int) $actual : $actual;

        switch ($operator) {
            case '=':
                return $actual === $expected;

            case '!=':
                return $actual !== $expected;

            case 'in':
                return in_array($actual, (array) $expected);

            case '!in':
                return ! in_array($actual, (array) $expected);

            case 'like':
                return Str::match('/' . $expected . '/', $actual) !== null;

            case '!like':
                return Str::match('/' . $expected . '/', $actual) === null;

            case 'contains':
                return Str::contains($expected, $actual);

            case '!contains':
                return Str::contains($expected, $actual) === false;

            case 'starts':
                return Str::startsWith($expected, $actual);

            case '!starts':
                return Str::startsWith($expected, $actual) === false;

            case 'ends':
                return Str::endsWith($expected, $actual);

            case '!ends':
                return Str::endsWith($expected, $actual) === false;

            default:
                throw new RuntimeException(sprintf("Operator '%s' is not supported.", $operator));
        }
    }

    public function url(array $args = []): string
    {
        return '';
    }

    public function run(IContainer $container): void
    {
        $response = $this->executeMiddleware($container, $container[IRequest::class]);

        if (! empty($response)) {
            try {
                $response = $this->ensureResponse($response);
            } catch (AbortException $e) {
                throw new RuntimeException(sprintf('Route "%s" returned invalid response.', $this->name()));
            }

            $response->send($container[IRequest::class], $container[Response::class]);
        }

        $this->container = $container;

        if (isset($this->queryManipulateCallback)) {
            add_action('pre_get_posts', [$this, 'executeQueryManipulationCallback']);
        }

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
}
