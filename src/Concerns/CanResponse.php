<?php

declare(strict_types=1);

namespace Devly\WP\Routing\Concerns;

use Devly\Exceptions\AbortException;
use Devly\WP\Routing\Contracts\IResponse;
use Devly\WP\Routing\Responses\CallbackResponse;
use Devly\WP\Routing\Responses\JsonResponse;
use Devly\WP\Routing\Responses\TextResponse;
use Devly\WP\Routing\Responses\VoidResponse;

use function is_array;
use function is_callable;
use function is_object;
use function is_string;
use function method_exists;

trait CanResponse
{
    /**
     * @param IResponse|string|array<array-key, mixed>|object|callable|null $response
     *
     * @throws AbortException
     */
    protected function ensureResponse($response): IResponse
    {
        if ($response instanceof IResponse) {
            return $response;
        }

        if (is_string($response) || is_object($response) && method_exists($response, 'render')) {
            return new TextResponse($response);
        }

        if (is_callable($response)) {
            return new CallbackResponse($response);
        }

        if (is_array($response) || is_object($response)) {
            return new JsonResponse($response);
        }

        if ($response === null) {
            return new VoidResponse();
        }

        throw new AbortException();
    }
}
