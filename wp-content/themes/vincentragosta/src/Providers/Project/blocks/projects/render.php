<?php
/**
 * Server-side rendering for the Projects block.
 *
 * "all" renders the projects archive (stats, filters, featured case studies,
 * year-grouped index / card grid). "featured" renders the case studies in the
 * featured layout (homepage). "latest" and "curated" render a card grid.
 */

use ChildTheme\Providers\Project\ProjectArchive;
use ChildTheme\Providers\Project\ProjectRepository;
use ChildTheme\Theme;
use Timber\Timber;

$mode = get_field('display_mode') ?: 'latest';
$selected_ids = get_field('selected_projects') ?: [];

$repository = Theme::container()->get(ProjectRepository::class);
$context = Timber::context();

if ($mode === 'all') {
    $archive = new ProjectArchive($repository->allByYear(), $repository->caseStudies());
    $context['archive'] = $archive->toArray();
} elseif ($mode === 'featured') {
    $context['featured'] = $repository->caseStudies();
} elseif ($mode === 'curated' && !empty($selected_ids)) {
    $context['projects'] = $repository->findMany($selected_ids);
} else {
    $context['projects'] = $repository->latestByYear();
}

// Get block wrapper attributes (alignment comes from block supports).
$wrapper_attributes = get_block_wrapper_attributes(
    $mode === 'all' ? ['class' => 'is-archive', 'data-view' => 'index'] : []
);

echo '<div ' . $wrapper_attributes . '>';
Timber::render(__DIR__ . '/grid.twig', $context);
echo '</div>';
