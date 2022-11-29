<?php

declare(strict_types=1);

namespace Devly\WP\Routing\Concerns;

use Devly\Utils\Str;

use function explode;
use function is_array;
use function is_callable;

trait HasController
{
    /** @var  callable|string|array{class-string|object, string}|null */
    protected $controller;

    /** @inheritdoc */
    public function controller($callback = null)
    {
        if ($callback === null) {
            return $this->controller;
        }

        if ($callback === false) {
            $this->controller = null;
        } else {
            $this->controller = $callback;
        }

        return $this;
    }

    /**
     * @param callable|class-string|string|array<class-string|object, string> $controller
     *
     * @return callable|array{class-string|object, string}
     */
    public function normalizeController($controller)
    {
        if (is_callable($controller) || is_array($controller)) {
            return $controller;
        }

        if (Str::contains($controller, '::')) {
            return explode('::', $controller);
        }

        if (Str::contains($controller, '@')) {
            return explode('@', $controller);
        }

        return [$controller, 'run'];
    }
}
