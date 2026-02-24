<?php
/**
 * Server-side rendering for the Projects block.
 */

use ChildTheme\Providers\Project\ProjectPost;
use Timber\Timber;

$mode = get_field('display_mode') ?: 'latest';
$selected_ids = get_field('selected_projects') ?: [];

$args = [
    'post_type'      => ProjectPost::POST_TYPE,
    'posts_per_page' => 5,
    'post_status'    => 'publish',
];

if ($mode === 'curated' && !empty($selected_ids)) {
    $args['post__in'] = $selected_ids;
    $args['orderby'] = 'post__in';
} else {
    $args['orderby'] = 'date';
    $args['order'] = 'DESC';
}

$context = Timber::context();
$context['projects'] = Timber::get_posts($args);

// Get block wrapper attributes (alignment comes from block supports).
$wrapper_attributes = get_block_wrapper_attributes();

echo '<div ' . $wrapper_attributes . '>';
// Render the Twig file, passing the context.
Timber::render(__DIR__ . '/grid.twig', $context);
echo '</div>';
