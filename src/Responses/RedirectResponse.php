<?php

declare(strict_types=1);

namespace Devly\WP\Routing\Responses;

use Devly\WP\Routing\Request;
use Nette\Http\Response as HttpResponse;

class RedirectResponse extends Response
{
    private string $url;

    public function __construct(string $url, int $httpCode = 302)
    {
        $this->url        = $url;
        $this->statusCode = $httpCode;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Sends response to output.
     */
    public function send(Request $request, HttpResponse $httpResponse): void
    {
        wp_redirect($this->getUrl(), $this->getStatusCode());

        exit;
    }
}
