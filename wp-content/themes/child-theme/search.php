<?php

declare(strict_types=1);

use Timber\Timber;

$context = Timber::context();
$context['query'] = get_search_query();
$context['posts'] = Timber::get_posts();
$context['found_posts'] = $GLOBALS['wp_query']->found_posts;

// Extract unique post type labels from results for filter tabs.
$postTypes = [];
foreach ($context['posts'] as $post) {
    $type = $post->post_type;
    if (!isset($postTypes[$type])) {
        $obj = get_post_type_object($type);
        $postTypes[$type] = $obj ? $obj->labels->singular_name : ucfirst($type);
    }
}
$context['post_types'] = $postTypes;

Timber::render('search.twig', $context);
