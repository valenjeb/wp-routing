<?php

declare(strict_types=1);

namespace Devly\WP\Routing\Tests\Unit;

use Devly\WP\Routing\Routes\Ajax;
use WP_UnitTestCase;

class AjaxRouteTest extends WP_UnitTestCase
{
    protected Ajax $route;

    public function setUp(): void
    {
        parent::setUp();

        $this->route = new Ajax('update_product', 'ProductController::update');
    }

    public function tearDown(): void
    {
        parent::tearDown();

        unset($this->route);
    }

    public function testGetRouteUrl(): void
    {
        $this->assertEquals(
            'http://example.org/wp-admin/admin-ajax.php?action=update_product&id=1234',
            $this->route->url(['id' => 1234])
        );
    }

    public function testSetAndGetRouteName(): void
    {
        $this->route->name('foo');

        $this->assertEquals('foo', $this->route->name());
    }

    public function testGetAutoGeneratedName(): void
    {
        $this->assertMatchesRegularExpression(
            '/[a-zA-Z0-9]{6}/',
            $this->route->name()
        );
    }
}
