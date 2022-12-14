<?php

declare(strict_types=1);

namespace Devly\WP\Routing\Tests\Unit;

use Devly\Exceptions\RouteNotFoundException;
use Devly\WP\Routing\Contracts\IRoute;
use Devly\WP\Routing\Routes;
use Devly\WP\Routing\Routes\Ajax;
use Devly\WP\Routing\Routes\Route;
use PHPUnit\Framework\TestCase;

class RoutesCollectionTest extends TestCase
{
    protected Routes $routes;
    /** @var Route */
    protected IRoute $route;
    /** @var Ajax */
    protected IRoute $ajaxRoute;

    protected function setUp(): void
    {
        parent::setUp();

        $this->routes = new Routes();

        $this->route     = $this->routes->addRoute('GET', '/', 'route_callback')->name('webRoute');
        $this->ajaxRoute = $this->routes->addAjaxRoute('/', 'route_callback')->name('ajaxRoute');
    }

    protected function tearDown(): void
    {
        parent::tearDown(); // TODO: Change the autogenerated stub
        unset($this->routes, $this->route, $this->ajaxRoute);
    }

    public function testRetrieveRoute(): void
    {
        $route = $this->routes->get('webRoute');

        $this->assertSame($route, $this->route);
        $this->assertInstanceOf(Route::class, $route);
        $this->assertInstanceOf(Ajax::class, $this->routes->get('ajaxRoute'));
    }

    public function testRetrieveRouteThrowsRouteNotFoundException(): void
    {
        $this->expectException(RouteNotFoundException::class);

        $this->routes->get('bar');
    }

    public function testRoutesIterator(): void
    {
        foreach ($this->routes as $name => $route) {
            $this->assertContains($name, ['webRoute', 'ajaxRoute']);
            $this->assertInstanceOf(IRoute::class, $route);
        }
    }
}
