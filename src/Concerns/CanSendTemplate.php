<?php

declare(strict_types=1);

namespace Devly\WP\Routing\Concerns;

use Devly\WP\Routing\Contracts\IRequest;
use Devly\WP\Routing\Utility;
use Nette\Http\Response;

trait CanSendTemplate
{
    /** @internal */
    public function sendTemplate(string $template): string
    {
        if (! isset($this->response)) {
            return $template;
        }

        $this->response->send($this->container[IRequest::class], $this->container[Response::class]);

        return Utility::EMPTY_PLACEHOLDER;
    }
}
