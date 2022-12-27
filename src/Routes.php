<?php

declare(strict_types=1);

namespace Devly\WP\Routing;

use ArrayIterator;
use Devly\Exceptions\RouteNotFoundException;
use Devly\WP\Routing\Contracts\IRoute;
use Devly\WP\Routing\Routes\Ajax;
use Devly\WP\Routing\Routes\Query;
use Devly\WP\Routing\Routes\Route;
use IteratorAggregate;

use function array_key_exists;
use function sprintf;

/** @implements IteratorAggregate<string, IRoute|Route|Ajax|Query> */
class Routes implements IteratorAggregate
{
    /** @var Route[]|Ajax[]|Query[] */
    protected array $routes = [];
    /** @var array<string, Route|Ajax|Query> */
    protected array $namedRoutes = [];

    /**
     * @param callable|class-string<T>|string|array<class-string<T>|T, string> $callback
     *
     * @template T of object
     */
    public function addRoute(string $pattern, $callback): Route
    {
        $route = new Route($pattern, $callback);

        $this->routes[] = $route;

        return $route;
    }

    /**
     * @param callable|class-string<T>|string|array<class-string<T>|T, string> $callback
     *
     * @template T of object
     */
    public function addAjaxRoute(string $action, $callback): Ajax
    {
        $route = new Ajax($action, $callback);

        $this->routes[] = $route;

        return $route;
    }

    /**
     * @param array<array{key: string, operator: string, value: mixed}>        $args
     * @param callable|class-string<T>|string|array<class-string<T>|T, string> $callback
     *
     * @template T of object
     */
    public function addQueryRoute(array $args = [], $callback = null): Query
    {
        $route = new Query($args, $callback);

        $this->routes[] = $route;

        return $route;
    }

    public function has(string $name): bool
    {
        if (array_key_exists($name, $this->namedRoutes)) {
            return true;
        }

        foreach ($this->routes as $i => $route) {
            $this->namedRoutes[$route->name()] = $route;
            unset($this->routes[$i]);

            if ($route->name() === $name) {
                return true;
            }
        }

        return false;
    }

    protected function generateNamedRouteList(): void
    {
        foreach ($this->routes as $i => $route) {
            $this->namedRoutes[$route->name()] = $route;
            unset($this->routes[$i]);
        }
    }

    /**
     * Get route from the collection by its name
     *
     * @return Route|Ajax|Query
     *
     * @throws RouteNotFoundException
     */
    public function get(?string $name = null): IRoute
    {
        if (! $this->has($name)) {
            throw new RouteNotFoundException(sprintf(
                'Route "%s" does not exist.',
                $name,
            ));
        }

        return $this->namedRoutes[$name];
    }

    /** @return ArrayIterator<string, Route|Ajax> */
    public function getIterator(): ArrayIterator
    {
        $this->generateNamedRouteList();

        return new ArrayIterator($this->namedRoutes);
    }

    public function forget(string $name): void
    {
        if (! $this->has($name)) {
            throw new RouteNotFoundException(sprintf(
                'Route "%s" does not exist.',
                $name,
            ));
        }

        unset($this->namedRoutes[$name]);
    }
}
