<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Theme\Hooks;

use Mythus\Contracts\Hook;

class SearchSetup implements Hook
{
    public function register(): void
    {
        add_action('pre_get_posts', [$this, 'setSearchPostsPerPage']);
        add_filter('relevanssi_post_types', [$this, 'filterPostTypes']);
    }

    public function setSearchPostsPerPage(\WP_Query $query): void
    {
        if (!$query->is_main_query() || is_admin() || !$query->is_search()) {
            return;
        }

        $query->set('posts_per_page', 20);
    }

    /**
     * @param array<string> $types
     * @return array<string>
     */
    public function filterPostTypes(array $types): array
    {
        $postType = sanitize_key($_GET['post_type'] ?? '');

        if ($postType && post_type_exists($postType)) {
            return [$postType];
        }

        return $types;
    }
}
