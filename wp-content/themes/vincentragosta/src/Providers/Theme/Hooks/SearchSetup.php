<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Theme\Hooks;

use ChildTheme\Providers\Theme\SearchResults;
use Mythus\Contracts\Hook;

class SearchSetup implements Hook
{
    public function register(): void
    {
        add_action('pre_get_posts', [$this, 'setSearchPostsPerPage']);
    }

    public function setSearchPostsPerPage(\WP_Query $query): void
    {
        if (!$query->is_main_query() || is_admin() || !$query->is_search()) {
            return;
        }

        $query->set('posts_per_page', 20);

        // Always scope to the searchable types: ?post_type= is a public query
        // var, so an unsearchable value (e.g. the shop's product) would
        // otherwise reach the query as is.
        $postType = SearchResults::activeType(sanitize_key($_GET['post_type'] ?? ''));
        $query->set('post_type', $postType !== '' ? $postType : array_keys(SearchResults::TYPES));
    }
}
