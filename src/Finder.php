<?php

declare(strict_types=1);

namespace Devly\WP\Routing;

use Devly\Utils\Str;
use WP;

use function class_exists;
use function in_array;
use function strlen;

/**
 * Controller Finder
 */
class Finder
{
    public const CONTROLLER_SUFFIX = 'Controller';

    /**
     * Instance of current WP environment.
     */
    protected WP $wp;
    protected string $suffix;
    protected ?string $defaultController;
    protected string $namespace;
    /**
     * List of possible controller based on the current WP environment.
     *
     * @var array|string[]
     */
    private array $controllers = [];

    public function __construct(?WP $wp = null)
    {
        $this->wp                = $wp ?? $GLOBALS['wp'];
        $this->suffix            = apply_filters(Hooks::FILTER_CONTROLLER_SUFFIX, self::CONTROLLER_SUFFIX);
        $this->defaultController = apply_filters(Hooks::FILTER_DEFAULT_CONTROLLER, 'Default' . $this->getSuffix());
        $this->namespace         = apply_filters(Hooks::FILTER_NAMESPACE, '');
        $this->parseQueryVars();
    }

    private function parseQueryVars(): void
    {
        $queryVars = $this->wp->query_vars;

        $suffix = $this->getSuffix();

        if ($this->wp->request === '' && empty($queryVars)) {
            $this->controllers[] = 'HomePage' . $suffix;
            $this->controllers[] = 'FrontPage' . $suffix;

            return;
        }

        if (! empty($queryVars['post_type'])) {
            if (! empty($queryVars['name'])) {
                $this->controllers[] = 'Single' . Str::classify($queryVars['post_type']) . $suffix;
                $this->controllers[] = 'Single' . $suffix;
            } else {
                $this->controllers[] = 'Archive' . Str::classify($queryVars['post_type']) . $suffix;
                $this->controllers[] = 'Archive' . $suffix;
            }

            return;
        }

        if (
            ! empty($queryVars['p'])
            || ! empty($queryVars['name'])
            || ! empty($queryVars['title'])
        ) {
            $this->controllers[] = 'Single' . $suffix;

            return;
        }

        if (! empty($queryVars['pagename']) || ! empty($queryVars['page_id']) || isset($queryVars['page'])) {
            $this->controllers[] = 'Page' . $suffix;
            $this->controllers[] = 'Single' . $suffix;

            return;
        }

        if (
            ! empty($queryVars['author'])
            || ! empty($queryVars['author_name'])
        ) {
            $this->controllers[] = 'Author' . $suffix;
            $this->controllers[] = 'Archive' . $suffix;

            return;
        }

        if (
            ! empty($queryVars['category_name'])
            || ! empty($queryVars['cat'])
        ) {
            $this->controllers[] = 'Category' . $suffix;
            $this->controllers[] = 'Archive' . $suffix;

            return;
        }

        if (! empty($queryVars['tag'])) {
            $this->controllers[] = 'Tag' . $suffix;
            $this->controllers[] = 'Archive' . $suffix;

            return;
        }

        foreach (get_taxonomies() as $tax) {
            if (! isset($queryVars[$tax])) {
                continue;
            }

            $this->controllers[] = 'Taxonomy' . Str::classify($tax) . $suffix;
            $this->controllers[] = 'Taxonomy' . $suffix;
            $this->controllers[] = 'Archive' . $suffix;

            break;
        }

        if (
            ! empty($queryVars['author__in'])
            || ! empty($queryVars['author__not_in'])
            || ! empty($queryVars['category__and'])
            || ! empty($queryVars['category__in'])
            || ! empty($queryVars['category__not_in'])
            || ! empty($queryVars['tag__and'])
            || ! empty($queryVars['tag__in'])
            || ! empty($queryVars['tag__not_in'])
            || ! empty($queryVars['tag_slug__and'])
            || ! empty($queryVars['tag_slug__in'])
        ) {
            $this->controllers[] = 'Archive' . $suffix;

            return;
        }

        if (! empty($queryVars['s'])) {
            $this->controllers[] = 'Search' . $suffix;
        }

        if (
            ! empty($queryVars['second'])
            || ! empty($queryVars['minute'])
            || ! empty($queryVars['hour'])
        ) {
            $this->controllers[] = 'Time' . $suffix;
            $this->controllers[] = 'Date' . $suffix;
            $this->controllers[] = 'Archive' . $suffix;
        }

        if (
            ! empty($queryVars['day'])
            || ! empty($queryVars['monthnum'])
            || ! empty($queryVars['year'])
            || ! empty($queryVars['w'])
        ) {
            if (! in_array('Date' . $suffix, $this->controllers)) {
                $this->controllers[] = 'Date' . $suffix;
                $this->controllers[] = 'Archive' . $suffix;
            }
        }

        if (empty($queryVars['m'])) {
            return;
        }

        if (strlen($queryVars['m']) > 9) {
            $this->controllers[] = 'Time' . $suffix;
        }

        $this->controllers[] = 'Date' . $suffix;
        $this->controllers[] = 'Archive' . $suffix;
    }

    /** @return array|string[] */
    public function getPossibleControllers(): array
    {
        return $this->controllers;
    }

    public function matchController(): ?string
    {
        foreach ($this->getPossibleControllers() as $controller) {
            $controller = $this->getNamespace() . $controller;
            if (class_exists($controller)) {
                return $controller;
            }
        }

        return $this->getDefaultController();
    }

    public function getNamespace(): string
    {
        return empty($this->namespace) ? '' : $this->namespace . '\\';
    }

    /** @return mixed|null */
    protected function getSuffix()
    {
        return $this->suffix;
    }

    public function get404Controller(): ?string
    {
        $controller = apply_filters(Hooks::FILTER_404_CONTROLLER, null);

        if (! empty($controller)) {
            return $this->getNamespace() . $controller;
        }

        return $this->getDefaultController();
    }

    protected function getDefaultController(): ?string
    {
        if (! isset($this->defaultController)) {
            return null;
        }

        $class = $this->getNamespace() . $this->defaultController;

        if (class_exists($class)) {
            return $class;
        }

        return null;
    }
}
