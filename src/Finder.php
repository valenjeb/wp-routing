<?php

declare(strict_types=1);

namespace Devly\WP\Routing;

use Brain\Hierarchy\Hierarchy;
use Devly\Utils\Str;
use WP_Query;

use function array_map;
use function class_exists;
use function trim;

/**
 * Controller Finder
 */
class Finder
{
    public const CONTROLLER_SUFFIX = 'Controller';

    protected string $suffix;
    protected ?string $default;
    protected string $namespace;

    /**
     * @param string|null $namespace The namespace  controllers.
     * @param string|null $suffix    The controller suffix.
     * @param string|null $default   Default controller class name.
     */
    public function __construct(?string $namespace = null, ?string $suffix = null, ?string $default = null)
    {
        $this->suffix    = apply_filters(Hooks::FILTER_CONTROLLER_SUFFIX, $suffix ?? self::CONTROLLER_SUFFIX);
        $this->namespace = apply_filters(Hooks::FILTER_NAMESPACE, $namespace ?? '');
        $this->default   = apply_filters(Hooks::FILTER_DEFAULT_CONTROLLER, $default);
    }

    /** @return string[] */
    protected function getHierarchy(?WP_Query $query = null): array
    {
        $hierarchy = new Hierarchy();

        $classes = array_map(function (string $template): string {
            if ($template === '404') {
                $template = 'error-404';
            }

            if ($template === 'index') {
                $template = 'default';
            }

            return $this->convert(Str::classify($template));
        }, $hierarchy->templates($query));

        if ($this->default) {
            $classes[] = $this->default;
        }

        return $classes;
    }

    public function match(?WP_Query $query = null): ?string
    {
        foreach ($this->getHierarchy($query) as $controller) {
            if (class_exists($controller)) {
                return $controller;
            }
        }

        return null;
    }

    public function getNamespace(): string
    {
        return empty($this->namespace) ? '' : trim(str_replace('.', '-', $this->namespace), '\\') . '\\';
    }

    public function getSuffix(): string
    {
        return $this->suffix;
    }

    /**
     * Convert kebab case string to a fully qualified controller name.
     */
    protected function convert(string $name): string
    {
        return $this->getNamespace() . Str::classify($name) . $this->getSuffix();
    }
}
