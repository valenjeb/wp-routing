<?php

declare(strict_types=1);

namespace Devly\WP\Routing\Tests\Unit;

use Devly\WP\Routing\Routes\Route;
use Devly\WP\Routing\Utility;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class RouteTest extends TestCase
{
    protected Route $route;

    protected function setUp(): void
    {
        parent::setUp();

        $this->route = new Route('product/{name?}', 'ProductController::getProductByName');

        $this->route->name('getProductByName');
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset($this->route);
    }

    public function testGetRewriteRule(): void
    {
        $this->assertEquals(
            ['product(?:/([-\w]+))?/?$' => 'index.php?' . Utility::QUERY_VAR . '=getProductByName'],
            $this->route->getRewriteRule()
        );
    }

    public function testGetRewriteRuleWithSetQueryVar(): void
    {
        $this->route->setQueryVar('name', 1);

        $this->assertEquals(
            ['product(?:/([-\w]+))?/?$' => 'index.php?name=$matches[1]&' . Utility::QUERY_VAR . '=getProductByName'],
            $this->route->getRewriteRule()
        );
    }

    public function testBindRegexToParam(): void
    {
        $this->route->whereNumeric('name')->setQueryVar('name', 1);

        $this->assertEquals(
            ['product(?:/([0-9]+))?/?$' => 'index.php?name=$matches[1]&' . Utility::QUERY_VAR . '=getProductByName'],
            $this->route->getRewriteRule()
        );
    }

    public function testBindParamToRegex(): void
    {
        $route = new Route('product/([a-zA-Z0-9])', static fn () => '');
        $route->setQueryVar('name', 1)->name('getProductByName');

        $this->assertEquals(
            ['product/([a-zA-Z0-9])/?$' => 'index.php?name=$matches[1]&' . Utility::QUERY_VAR . '=getProductByName'],
            $route->getRewriteRule()
        );
    }

    public function testGetUrl(): void
    {
        $this->assertEquals('http://example.org/product/chair', $this->route->url(['name' => 'chair']));
    }

    public function testGetUrlWithOptionsParamMissing(): void
    {
        $this->assertEquals('http://example.org/product', $this->route->url());
    }

    public function testGetUrlThrowsRuntimeExceptionIfNotAllRequiredParamsProvided(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing argument: name.');

        $route = new Route('product/{name}', 'ProductController::getProductByName');
        $route->url();
    }
}
