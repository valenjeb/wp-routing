<?php

declare(strict_types=1);

namespace Devly\WP\Routing\Tests\Integration;

use Devly\WP\Routing\Finder;
use ReflectionClass;
use WP_UnitTestCase;

use function get_class;

class FinderTest extends WP_UnitTestCase
{
    protected Finder $finder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->finder = new Finder('MyNamespace', 'Presenter', 'DefaultController');
    }

    public function testGetNamespace(): void
    {
        $this->assertEquals('MyNamespace\\', $this->finder->getNamespace());
    }

    public function testGetSuffix(): void
    {
        $this->assertEquals('Presenter', $this->finder->getSuffix());
    }

    public function testControllerHierarchy(): void
    {
        $this->go_to('/');

        $reflection = new ReflectionClass(get_class($this->finder));
        $method     = $reflection->getMethod('getHierarchy');
        $method->setAccessible(true);

        $res = $method->invoke($this->finder);

        $this->assertEquals([
            'MyNamespace\\FrontPagePresenter',
            'MyNamespace\\HomePresenter',
            'MyNamespace\\DefaultPresenter',
            'DefaultController',
        ], $res);
    }
}
