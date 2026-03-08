<?php
/**
 * Server-side rendering for the Projects block.
 */

use ChildTheme\Providers\Project\ProjectPost;
use Timber\Timber;

$mode = get_field('display_mode') ?: 'latest';
$selected_ids = get_field('selected_projects') ?: [];

$args = [
    'post_type'   => ProjectPost::POST_TYPE,
    'post_status' => 'publish',
];

if ($mode === 'all') {
    $args['posts_per_page'] = -1;
    $args['meta_key'] = 'project_year';
    $args['orderby'] = 'meta_value_num';
    $args['order'] = 'DESC';
} elseif ($mode === 'curated' && !empty($selected_ids)) {
    $args['posts_per_page'] = 6;
    $args['post__in'] = $selected_ids;
    $args['orderby'] = 'post__in';
} else {
    $args['posts_per_page'] = 6;
    $args['orderby'] = 'date';
    $args['order'] = 'DESC';
}

$context = Timber::context();
$context['projects'] = Timber::get_posts($args);
$context['show_sort'] = ($mode === 'all');

// Get block wrapper attributes (alignment comes from block supports).
$wrapper_attributes = get_block_wrapper_attributes();

echo '<div ' . $wrapper_attributes . '>';
// Render the Twig file, passing the context.
Timber::render(__DIR__ . '/grid.twig', $context);
echo '</div>';
