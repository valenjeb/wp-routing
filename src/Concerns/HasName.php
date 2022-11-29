<?php

declare(strict_types=1);

namespace Devly\WP\Routing\Concerns;

use function md5;
use function substr;

trait HasName
{
    protected string $name;

    /** @inheritdoc */
    public function name(?string $name = null)
    {
        if (empty($name)) {
            if (! isset($this->name)) {
                $this->name = $this->generateUniqueRouteID($this->pattern);
            }

            return $this->name;
        }

        $this->name = $name;

        return $this;
    }

    protected function generateUniqueRouteID(string $pattern): string
    {
        return substr(md5($pattern), 0, 6);
    }
}
