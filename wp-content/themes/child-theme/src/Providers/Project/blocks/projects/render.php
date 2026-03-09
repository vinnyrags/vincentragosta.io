<?php
/**
 * Server-side rendering for the Projects block.
 */

use ChildTheme\Providers\Project\ProjectRepository;
use ChildTheme\Theme;
use Timber\Timber;

$mode = get_field('display_mode') ?: 'latest';
$selected_ids = get_field('selected_projects') ?: [];

$repository = Theme::container()->get(ProjectRepository::class);

if ($mode === 'all') {
    $projects = $repository->allByYear();
} elseif ($mode === 'curated' && !empty($selected_ids)) {
    $projects = $repository->findMany($selected_ids);
} else {
    $projects = $repository->latestByYear();
}

// Collect unique categories for the filter dropdown (only in "all" mode).
$categories = [];
if ($mode === 'all') {
    foreach ($projects as $project) {
        foreach ($project->categories() as $term) {
            if (!isset($categories[$term->slug])) {
                $categories[$term->slug] = $term->name;
            }
        }
    }
    ksort($categories);
}

$context = Timber::context();
$context['projects'] = $projects;
$context['show_sort'] = ($mode === 'all');
$context['categories'] = $categories;

// Get block wrapper attributes (alignment comes from block supports).
$wrapper_attributes = get_block_wrapper_attributes();

echo '<div ' . $wrapper_attributes . '>';
Timber::render(__DIR__ . '/grid.twig', $context);
echo '</div>';
