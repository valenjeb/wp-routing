<?php

declare(strict_types=1);

namespace Devly\WP\Routing\Responses;

use Devly\WP\Routing\Request;
use Nette\Http\Response as HttpResponse;

class CallbackResponse extends Response
{
    /** @var callable */
    private $callback;

    /** @param callable(Request, HttpResponse): void $callback */
    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    public function send(Request $request, HttpResponse $httpResponse): void
    {
        ($this->callback)($request, $httpResponse);
    }
}
