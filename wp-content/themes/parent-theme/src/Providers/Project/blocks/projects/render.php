<?php
/**
 * Server-side rendering for the Projects block.
 */

use ParentTheme\Providers\Project\ProjectRepository;
use ParentTheme\Theme;
use Timber\Timber;

$mode = get_field('display_mode') ?: 'latest';
$selected_ids = get_field('selected_projects') ?: [];

$repository = Theme::container()->get(ProjectRepository::class);

if ($mode === 'all') {
    $projects = $repository->all();
} elseif ($mode === 'curated' && !empty($selected_ids)) {
    $projects = $repository->findMany($selected_ids);
} else {
    $projects = $repository->latest();
}

$context = Timber::context();
$context['projects'] = $projects;

// Get block wrapper attributes (alignment comes from block supports).
$wrapper_attributes = get_block_wrapper_attributes();

echo '<div ' . $wrapper_attributes . '>';
Timber::render(__DIR__ . '/grid.twig', $context);
echo '</div>';
