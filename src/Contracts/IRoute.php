<?php

/** phpcs:disable Generic.Files.LineLength.TooLong */

declare(strict_types=1);

namespace Devly\WP\Routing\Contracts;

use Devly\DI\Contracts\IContainer;

interface IRoute
{
    /**
     * Set or get the route name.
     *
     * @return self|string
     */
    public function name(?string $name = null);

    /**
     * Retrieves the controller for the route
     *
     * @param callable|class-string|string|array<class-string|object, string>|false|null $callback The controller action, `false`
     *                                                                                             to remove controller or `null`
     *                                                                                             to retrieve the configured
     *                                                                                             controller
     *
     * @return callable|string|array{class-string|object, string}|static|null
     */
    public function controller($callback = null);

    /**
     * Set or get middleware
     *
     * @param callable|class-string|array<callable|class-string>|array<empty>|null $callback A callable or a class name
     *                                                                                       (the router will call the
     *                                                                                       handle() method automatically)
     *
     * @return static|array<callable|class-string> Self instance if used to add middleware or
     *                                             an array of bound middleware if called with
     *                                             no value (null).
     */
    public function middleware($callback = null, bool $override = false);

    /**
     * Return the URL for this route, with the given arguments
     *
     * @param array<array-key, string|int> $args
     */
    public function url(array $args = []): string;

    /**
     * Execute the route
     */
    public function run(IContainer $container): void;

    /**
     * Pass parameters to the route
     *
     * @param array<string, mixed> $parameters
     *
     * @return static
     */
    public function setParameters(array $parameters): self;

    /**
     * Get the route parameters
     *
     * @return array<string, mixed>
     */
    public function getParameters(): array;

    /**
     * Pass parameter to the route
     *
     * @param mixed $value
     *
     * @return static
     */
    public function setParameter(string $key, $value): self;

    /**
     * Get parameter by key name
     *
     * @param string $key     The parameter name to retrieve
     * @param mixed  $default Default value to return if key
     *                        does not exist.
     *
     * @return mixed
     */
    public function getParameter(string $key, $default = null);
}
