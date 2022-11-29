<?php

declare(strict_types=1);

namespace Devly\WP\Routing\Responses;

use Devly\WP\Routing\Contracts\IResponse;
use Devly\WP\Routing\Request;
use Nette\Http\Response as HttpResponse;

use const JSON_PRETTY_PRINT;

class JsonResponse implements IResponse
{
    protected ?int $statusCode;
    /** @var mixed */
    protected $payload;

    /** @param mixed $payload */
    public function __construct($payload, ?int $statusCode = null)
    {
        $this->payload    = $payload;
        $this->statusCode = $statusCode;
    }

    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    /** @return mixed */
    public function getPayload()
    {
        return $this->payload;
    }

    /**
     * Sends response to output.
     */
    public function send(Request $request, HttpResponse $httpResponse): void
    {
        $httpResponse->setHeader('Content-Type', 'application/json; charset=' . get_option('blog_charset'));
        if ($this->getStatusCode() !== null) {
            $httpResponse->setCode($this->getStatusCode());
        }

        echo wp_json_encode($this->getPayload(), JSON_PRETTY_PRINT);
    }
}
