<?php

declare(strict_types=1);

namespace Devly\WP\Routing\Routes;

use Devly\WP\Routing\Concerns\CanResponse;
use Devly\WP\Routing\Concerns\HasController;
use Devly\WP\Routing\Concerns\HasMiddleware;
use Devly\WP\Routing\Concerns\HasName;
use Devly\WP\Routing\Concerns\HasParameters;
use Devly\WP\Routing\Contracts\IRoute;

abstract class RouteBase implements IRoute
{
    use HasName;
    use HasController;
    use HasMiddleware;
    use CanResponse;
    use HasParameters;

    protected string $pattern;

    public function getPattern(): string
    {
        return $this->pattern;
    }

    /** @return array<string, string>|array<empty> */
    public function getRewriteRule(): array
    {
        return [];
    }
}
