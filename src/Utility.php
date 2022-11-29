<?php

declare(strict_types=1);

namespace Devly\WP\Routing;

use Devly\Utils\StaticClass;
use WP_Query;

class Utility
{
    use StaticClass;

    public const QUERY_VAR                = 'devly_route';
    public const ROUTE_CACHE_OPTION       = 'devly_routes';
    public const POST_TYPE                = 'devly_route_page';
    public const PLACEHOLDER_REWRITE_SLUG = 'devly-route';
    public const EMPTY_PLACEHOLDER        = __DIR__ . '/empty-placeholder.php';

    private static int $placeholderPostID = 0;

    public static function registerPlaceholderPostType(): void
    {
        register_post_type(self::POST_TYPE, [
            'public'              => false,
            'show_ui'             => false,
            'exclude_from_search' => true,
            'publicly_queryable'  => true,
            'show_in_menu'        => false,
            'show_in_nav_menus'   => false,
            'supports'            => ['title'],
            'has_archive'         => false,
            'rewrite'             => [
                'slug'       => self::PLACEHOLDER_REWRITE_SLUG,
                'with_front' => false,
                'feeds'      => false,
                'pages'      => false,
            ],
        ]);
    }

    /**
     * Get the ID of the placeholder post
     */
    public static function getPlaceholderPostID(): int
    {
        if (self::$placeholderPostID === 0) {
            $posts = get_posts([
                'post_type'      => self::POST_TYPE,
                'post_status'    => 'publish',
                'posts_per_page' => 1,
            ]);
            if ($posts) {
                self::$placeholderPostID = $posts[0]->ID;
            } else {
                self::$placeholderPostID = self::makePlaceholderPost();
            }
        }

        return self::$placeholderPostID;
    }

    /**
     * Make a new placeholder post
     *
     * @return int The ID of the new post
     */
    protected static function makePlaceholderPost(): int
    {
        $id = wp_insert_post([
            'post_title'  => '',
            'post_status' => 'publish',
            'post_type'   => self::POST_TYPE,
        ]);

        // @phpstan-ignore-next-line
        if (is_wp_error($id)) {
            return 0;
        }

        return $id;
    }

    /** @return array<string, mixed> */
    public static function getPlaceholderPageQueryVars(): array
    {
        return [
            'post_type' => self::POST_TYPE,
            'page_id' => self::getPlaceholderPostID(),
            'page' => '',
        ];
    }

    /**
     * Edit WordPress's query so it finds our placeholder page
     */
    public static function setQueryData(WP_Query $query): void
    {
        // make sure we get the right post
        $query->query_vars['post_type'] = self::POST_TYPE;
        $query->query_vars['p']         = self::getPlaceholderPostID();
        // override any vars WordPress set based on the original query
        $query->is_single   = false;
        $query->is_page     = true;
        $query->is_singular = true;
        $query->is_404      = false;
        $query->is_home     = false;
    }

    /**
     * Edit WordPress's query so it finds our placeholder page
     */
    public static function set404QueryData(WP_Query $query): void
    {
        $query->is_single   = false;
        $query->is_singular = false;
        $query->is_404      = true;
        $query->is_home     = false;
        $query->is_archive  = false;
    }
}
