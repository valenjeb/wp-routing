<?php

declare(strict_types=1);

namespace Devly\WP\Routing\Concerns;

use Devly\DI\Contracts\IContainer;
use Devly\Utils\Pipeline;
use Devly\WP\Routing\Contracts\IRequest;

use function is_callable;
use function is_string;

trait HasMiddleware
{
    /** @var array<callable|class-string> */
    protected array $middleware = [];

    /** @inheritdoc */
    public function middleware($callback = null, bool $override = false)
    {
        if ($callback === null) {
            return $this->middleware;
        }

        if (is_callable($callback) || is_string($callback)) {
            if ($override) {
                $this->middleware = [$callback];
            } else {
                $this->middleware[] = $callback;
            }
        }

        return $this;
    }

    /** @return mixed */
    public function executeMiddleware(IContainer $container, IRequest $request)
    {
        return Pipeline::create($container)->send($request)->through($this->middleware())->then(static fn () => null);
    }
}
