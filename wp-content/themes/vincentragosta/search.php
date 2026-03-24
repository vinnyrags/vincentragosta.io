<?php

declare(strict_types=1);

use Timber\Timber;

$context = Timber::context();
$context['query'] = get_search_query();
$context['posts'] = Timber::get_posts();
$context['found_posts'] = $GLOBALS['wp_query']->found_posts;
$context['active_post_type'] = sanitize_key($_GET['post_type'] ?? '');

// Static list of searchable post types for filter tabs.
$context['post_types'] = [
    'page'    => 'Page',
    'post'    => 'Post',
    'project' => 'Project',
];

Timber::render('search.twig', $context);
