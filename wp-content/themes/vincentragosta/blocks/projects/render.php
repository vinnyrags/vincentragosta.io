<?php
/**
 * Server-side rendering for the Projects block.
 */

use Timber\Timber;

$mode = $attributes['mode'] ?? 'latest';
$selected_ids = $attributes['selectedProjects'] ?? [];

$args = [
    'post_type'      => 'project',
    'posts_per_page' => 3,
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
$context['attributes'] = $attributes;

// Get block wrapper attributes and echo the block.
$wrapper_attributes = get_block_wrapper_attributes();

echo '<div ' . $wrapper_attributes . '>';
// Render the Twig file, passing the context.
Timber::render('blocks/projects/projects-grid.twig', $context);
echo '</div>';