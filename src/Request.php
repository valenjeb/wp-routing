<?php

declare(strict_types=1);

namespace Devly\WP\Routing;

use Devly\WP\Routing\Contracts\IRequest;
use Devly\WP\Routing\Contracts\IRoute;
use Nette\Http\IRequest as HttpRequestContract;
use WP;

use function is_array;

class Request implements IRequest
{
    protected HttpRequestContract $httpRequest;
    protected ?IRoute $route = null;
    protected Finder $finder;

    public function __construct(HttpRequestContract $httpRequest, ?Finder $finder = null)
    {
        $this->httpRequest = $httpRequest;
        $this->finder      = $finder ?? new Finder();
    }

    public function getHttpRequest(): HttpRequestContract
    {
        return $this->httpRequest;
    }

    public function wp(): WP
    {
        return $GLOBALS['wp'];
    }

    /** @inheritdoc */
    public function getQueryVars(): array
    {
        return $this->wp()->query_vars;
    }

    /** @inheritdoc */
    public function setQueryVar($key, $value = null): self
    {
        if (is_array($key)) {
            foreach ($key as $varKey => $value) {
                $this->setQueryVar($varKey, $value);
            }

            return $this;
        }

        $this->wp()->set_query_var($key, $value);

        return $this;
    }

    /** @inheritdoc */
    public function getQueryVar(string $key, $default = null)
    {
        return $this->wp()->query_vars[$key] ?? $default;
    }

    public function getRoute(): ?IRoute
    {
        return $this->route;
    }

    public function hasRoute(): bool
    {
        return $this->getRoute() !== null;
    }

    public function setRoute(IRoute $route): void
    {
        $this->route = $route;
    }

    public function getControllerFinder(): Finder
    {
        return $this->finder;
    }
}
