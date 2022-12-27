<?php

declare(strict_types=1);

namespace Devly\WP\Routing\Concerns;

use WP_Query;

use function call_user_func;

trait CanManipulateQuery
{
    /** @var callable|Closure */
    protected $queryManipulateCallback;

    /**
     * Manipulate the main WordPress query
     *
     * This callback will run in 'pre_get_posts' action hook.
     *
     * @param callable|Closure $callback
     *
     * @return static
     */
    public function manipulateQuery($callback): self
    {
        $this->queryManipulateCallback = $callback;

        return $this;
    }

    public function executeQueryManipulationCallback(WP_Query $query): void
    {
        if (is_admin() || ! $query->is_main_query()) {
            return;
        }

        call_user_func($this->queryManipulateCallback, $query);
    }
}
