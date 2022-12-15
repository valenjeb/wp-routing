<?php

declare(strict_types=1);

namespace Devly\WP\Routing\Responses;

use Devly\WP\Routing\Request;
use Nette\Http\Response as HttpResponse;
use WP_Error;

class ErrorResponse extends Response
{
    /** @var string|WP_Error */
    protected $error;
    /** @var int|string */
    protected $title;
    /** @var array<string, mixed>|int */
    protected $args;

    /**
     * @param string|WP_Error          $error Error message. If this is a WP_Error object, and not an
     *                                        Ajax or XML-RPC request, the error's messages are used.
     * @param string|int               $title Error title. If $message is a WP_Error object, error
     *                                        data with the key 'title' may be used to specify the
     *                                        title. If $title is an integer, then it is treated as
     *                                        the response code.
     * @param array<string, mixed>|int $args  Arguments to control behavior. If $args is an integer,
     *                                        then it is treated as the response code.
     */
    public function __construct($error, $title = '', $args = [])
    {
        $this->error = $error;
        $this->title = $title;
        $this->args  = $args;
    }

    /**
     * Sends response to output.
     */
    public function send(Request $request, HttpResponse $httpResponse): void
    {
        wp_die($this->error, $this->title, $this->args);
    }
}
