<?php

declare(strict_types=1);

namespace Devly\WP\Routing\Responses;

use Devly\WP\Routing\Contracts\IResponse;
use Devly\WP\Routing\Request;
use Nette\Http\Response as HttpRequest;

class VoidResponse implements IResponse
{
    public function send(Request $request, HttpRequest $httpResponse): void
    {
    }
}
