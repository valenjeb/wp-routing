<?php
/** @noinspection PhpComposerExtensionStubsInspection */

declare(strict_types=1);

namespace Devly\WP\Routing\Tests\Integration;

use Closure;
use Devly\DI\Container;
use Devly\DI\DI;
use Devly\WP\Routing\Contracts\IRequest;
use Devly\WP\Routing\Request;
use Devly\WP\Routing\Responses\TextResponse;
use Devly\WP\Routing\Routes\Ajax;
use Nette\Http\IRequest as HttpRequestContract;
use Nette\Http\RequestFactory;
use WP_Ajax_UnitTestCase;
use WPAjaxDieContinueException;

use function json_decode;

class AjaxRouteTest extends WP_Ajax_UnitTestCase
{
    protected string $action = 'test_route';
    protected Container $container;

    public function setUp(): void
    {
        parent::setUp();

        $action       = 'test_route';
        $this->action = $action;

        (new Ajax($this->action, static function () {
            return ['foo' => 'bar'];
        }))->middleware(static function (IRequest $request, Closure $next) use ($action) {
            if (check_ajax_referer($action, 'security', false) !== 1) {
                return new TextResponse('Forbidden', 403);
            }

            return $next($request);
        })->run($this->getContainer());
    }

    public function tearDown(): void
    {
        parent::tearDown();

        unset($this->container);
    }

    public function testAjaxRouteApplied(): void
    {
        $this->assertTrue(
            has_action('wp_ajax_nopriv_' . $this->action),
            'Ajax `wp_ajax_nopriv_{$action}` action hook should be applied'
        );

        $this->assertTrue(
            has_action('wp_ajax_' . $this->action),
            'Ajax `wp_ajax_{$action}` action hook should be applied'
        );
    }

    public function testShouldApplyAdminAction(): void
    {
        (new Ajax('admin_only', static function () {
            return ['foo' => 'bar'];
        }))->setAdminOnly()->run($this->getContainer());

        $this->assertFalse(
            has_action('wp_ajax_nopriv_admin_only'),
            '`wp_ajax_nopriv_{$action}` action hook should not be applied'
        );

        $this->assertTrue(
            has_action('wp_ajax_admin_only'),
            '`wp_ajax_{$action}` action hook should be applied'
        );
    }

    public function testHandleSuccessfulAjaxRequest(): void
    {
        $_POST['_ajax_nonce'] = wp_create_nonce($this->action);

        try {
            $this->_handleAjax($this->action);
        } catch (WPAjaxDieContinueException $e) {
        }

        $response = (array) json_decode($this->_last_response);

        $this->assertEquals(['foo' => 'bar'], $response);
    }

    public function testAjaxRequestShouldBeRejected(): void
    {
        try {
            $this->_handleAjax($this->action);
        } catch (WPAjaxDieContinueException $e) {
        }

        $this->assertEquals('Forbidden', $this->_last_response);
    }

    protected function getContainer(): Container
    {
        if (! isset($this->container)) {
            $this->container = new Container(
                [
                    HttpRequestContract::class => DI::factory(RequestFactory::class)->return('@fromGlobals'),
                    Request::class => DI::factory(Request::class),
                ],
                true,
                true
            );

            $this->container->alias(IRequest::class, Request::class);
        }

        return $this->container;
    }
}
