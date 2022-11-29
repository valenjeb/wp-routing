<?php

declare(strict_types=1);

namespace Devly\WP\Routing\Concerns;

trait HasParameters
{
    /**
     * List of parameters to pass to the presenter.
     *
     * @var array<string, mixed>
     */
    protected array $parameters = [];

    /**
     * @param array<string, mixed> $parameters
     *
     * @return static
     */
    public function setParameters(array $parameters): self
    {
        $this->parameters = $parameters;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * @param mixed $value
     *
     * @return static
     */
    public function setParameter(string $key, $value): self
    {
        $this->parameters[$key] = $value;

        return $this;
    }

    /**
     * @param string $key     The parameter name to retrieve
     * @param mixed  $default Default value to return if key
     *                        does not exist.
     *
     * @return mixed
     */
    public function getParameter(string $key, $default = null)
    {
        return $this->parameters[$key] ?? $default;
    }
}
