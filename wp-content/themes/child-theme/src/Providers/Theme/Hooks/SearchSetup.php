<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Theme\Hooks;

use Mythus\Contracts\Hook;

class SearchSetup implements Hook
{
    public function register(): void
    {
        add_action('pre_get_posts', [$this, 'setSearchPostsPerPage']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueSearchAssets']);
    }

    public function setSearchPostsPerPage(\WP_Query $query): void
    {
        if (!$query->is_main_query() || is_admin() || !$query->is_search()) {
            return;
        }

        $query->set('posts_per_page', 20);
    }

    public function enqueueSearchAssets(): void
    {
        if (!is_search()) {
            return;
        }

        $jsPath = get_stylesheet_directory() . '/dist/js/theme/search-filters.js';

        if (file_exists($jsPath)) {
            wp_enqueue_script(
                'child-theme-search-filters',
                get_stylesheet_directory_uri() . '/dist/js/theme/search-filters.js',
                [],
                filemtime($jsPath),
                true
            );
        }
    }
}
