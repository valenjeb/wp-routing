<?php

declare(strict_types=1);

namespace Devly\WP\Routing\Responses;

use Devly\WP\Routing\Contracts\IResponse;

use function headers_sent;

abstract class Response implements IResponse
{
    protected ?int $statusCode;

    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    public function setStatusCode(?int $statusCode): void
    {
        $this->statusCode = $statusCode;
    }

    public function setStatusHeader(): void
    {
        if (headers_sent() || $this->getStatusCode() === null) {
            return;
        }

        status_header($this->getStatusCode());
    }
}
