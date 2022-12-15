<?php

declare(strict_types=1);

namespace Devly\WP\Routing\Tests\Integration;

use Devly\WP\Routing\Router;
use WP_UnitTestCase;

use function array_keys;

class RouterTest extends WP_UnitTestCase
{
    protected Router $router;
    /** @var int[] */
    protected array $posts;
    protected string $routeBase = 'products';

    public function setUp(): void
    {
        parent::setUp();

        $this->set_permalink_structure('%postname%');

        $this->posts = $this->factory()->post->create_many(3);

        $this->router = new Router();

        $this->router->addRoute($this->routeBase . '/{name?}', static function (): void {
            echo 'foo';
        })->setQueryVar('name', 1)->name('getProductByName');

        do_action('init');
    }

    public function tearDown(): void
    {
        parent::tearDown();

        $this->set_permalink_structure();

        foreach ($this->posts as $id) {
            wp_delete_post($id);
        }

        unset($this->router, $this->posts);
    }

    public function testRewriteRulesAppliedToWordpress(): void
    {
        $this->assertArrayHasKey(
            $this->getRouteRegex(),
            get_option('rewrite_rules'),
            'Rewrite rules was not added to WordPress.'
        );
    }

    public function testRouteMatched(): void
    {
        $post = get_post($this->posts[0]);
        $this->go_to($this->routeBase . '/' . $post->post_name);

        $this->assertEquals(
            $this->getRouteRegex(),
            $GLOBALS['wp']->matched_rule,
            'Rewrite rule was not matched.'
        );

        $this->assertFalse(is_404());
        $this->assertTrue(is_single());
    }

    protected function getRouteRegex(): string
    {
        // @phpstan-ignore-next-line
        return array_keys($this->router->getRoute('getProductByName')->getRewriteRule())[0];
    }
}
