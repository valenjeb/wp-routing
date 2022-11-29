<?php

declare(strict_types=1);

namespace Devly\WP\Routing\Contracts;

use Devly\WP\Routing\Request;
use Nette\Http\Response;

interface IResponse
{
    public function send(Request $request, Response $httpResponse): void;
}
