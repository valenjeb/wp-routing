<?php

declare(strict_types=1);

namespace Devly\WP\Routing\Responses;

use Devly\WP\Routing\Request;
use Nette\Http\Response as HttpResponse;
use ReflectionException;
use ReflectionMethod;
use RuntimeException;

use function get_class;
use function headers_sent;
use function is_file;
use function is_object;
use function is_string;
use function sprintf;

class TextResponse extends Response
{
    /** @var mixed */
    private $source;

    /**
     * @param object|string $source An object that implements render() method,
     *                              a full file path or a string to output.
     */
    public function __construct($source, ?int $statusCode = null)
    {
        $this->source = $source;
        $this->setStatusCode($statusCode);
    }

    /** @return mixed */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * Sends response to output.
     */
    public function send(Request $request, HttpResponse $httpResponse): void
    {
        if (! headers_sent() && $this->getStatusCode() !== null) {
            status_header($this->getStatusCode());
        }

        if (is_string($this->source) && is_file($this->source)) {
            require $this->source;

            return;
        }

        if (is_object($this->source)) {
            try {
                $rm = new ReflectionMethod($this->source, 'render');
            } catch (ReflectionException $e) {
                throw new RuntimeException(sprintf(
                    '"%s" does not implements the render() method.',
                    get_class($this->source)
                ));
            }

            try {
                $rm->invoke($this->source);

                return;
            } catch (ReflectionException $e) {
                throw new RuntimeException(sprintf(
                    '"%s::render() could not be invoked: %s',
                    get_class($this->source),
                    $e->getMessage()
                ));
            }
        }

        echo $this->source;
    }
}
