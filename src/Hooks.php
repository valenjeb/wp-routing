<?php

declare(strict_types=1);

namespace Devly\WP\Routing;

use Devly\Utils\StaticClass;

class Hooks
{
    use StaticClass;

    public const FILTER_NAMESPACE           = 'devly/routing/namespace';
    public const FILTER_DEFAULT_CONTROLLER  = 'devly/routing/default_controller';
    public const FILTER_CONTROLLER_SUFFIX   = 'devly/routing/controller_suffix';
    public const FILTER_404_CONTROLLER      = 'devly/routing/default_404_controller';
    public const ACTION_ALTER_ROUTES        = 'devly/routing/alter_routes';
    public const FILTER_CONTROLLER_METADATA = 'devly/routing/controller_metadata';
}
