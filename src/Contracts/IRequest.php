<?php

declare(strict_types=1);

namespace Devly\WP\Routing\Contracts;

use Devly\WP\Routing\Finder;
use Nette\Http\IRequest as HttpRequest;
use WP;

interface IRequest
{
    public function getHttpRequest(): HttpRequest;

    /**
     * Retrieve the WordPress environment instance.
     */
    public function wp(): WP;

    /**
     * Get all query variables from the current WordPress request.
     *
     * @return array<string, mixed>
     */
    public function getQueryVars(): array;

    /**
     * Sets the value of a query variable for setting up the WordPress Query Loop.
     *
     * @param string|array<string, mixed> $key
     * @param mixed                       $value
     */
    public function setQueryVar($key, $value = null): self;

    /**
     * Retrieve query variable from the current WordPress request.
     *
     * @param mixed $default
     *
     * @return mixed
     */
    public function getQueryVar(string $key, $default = null);

    /**
     * Retrieves the matched route for the current request
     */
    public function getRoute(): ?IRoute;

    /**
     * Determines whether the request has matched route.
     */
    public function hasRoute(): bool;

    public function setRoute(IRoute $route): void;

    public function getControllerFinder(): Finder;

    public function matchController(): ?string;
}
