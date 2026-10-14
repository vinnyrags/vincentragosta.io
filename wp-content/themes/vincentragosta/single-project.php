<?php
/**
 * Template for displaying single projects.
 *
 * Projects have no post content: the page renders from the "Project" ACF
 * fields (see ProjectPost). Adds the next project (index order) and up to
 * three related projects with images from the same category.
 */

use ChildTheme\Providers\Project\ProjectRepository;
use ChildTheme\Theme;
use IX\Services\SchemaBuilderService;
use Timber\Timber;

$context     = Timber::context();
$timber_post = Timber::get_post();
$context['post'] = $timber_post;

$repository = Theme::container()->get(ProjectRepository::class);
$next       = $repository->nextAfter($timber_post);
$context['next_project'] = $next;

$categories = $timber_post->categories();

if (!empty($categories)) {
    $related = array_filter(
        $repository->relatedRandom($categories[0]->slug, 12),
        static fn($project): bool => $project->thumbnail() && (!$next || $project->ID !== $next->ID)
    );
    $context['related']          = array_slice(array_values($related), 0, 3);
    $context['related_category'] = $categories[0];
}

// Structured data.
$year = method_exists($timber_post, 'sortYear') ? (string) $timber_post->sortYear() : null;
$thumbnail = $timber_post->thumbnail();

$creativeWork = [
    '@context' => 'https://schema.org',
    '@type' => 'CreativeWork',
    'name' => $timber_post->title(),
    // Projects have no post content; the lead field carries the summary.
    'description' => wp_strip_all_tags($timber_post->post_excerpt ?: wp_trim_words(wp_strip_all_tags(
        method_exists($timber_post, 'lead') ? $timber_post->lead() : ''
    ), 30)),
    'url' => get_permalink($timber_post->ID),
    'creator' => (new SchemaBuilderService())->person(),
];

if ($year) {
    $creativeWork['dateCreated'] = $year;
}

if ($thumbnail) {
    $creativeWork['image'] = $thumbnail->src();
}

if ($timber_post->client()) {
    $creativeWork['sourceOrganization'] = ['@type' => 'Organization', 'name' => $timber_post->client()];
}

if ($timber_post->technologies()) {
    $creativeWork['keywords'] = implode(', ', $timber_post->technologies());
}

$schema = new SchemaBuilderService();
$schema->add($creativeWork)
    ->breadcrumbList([
        ['label' => 'Home', 'url' => home_url('/')],
        ['label' => 'Projects', 'url' => home_url('/projects/')],
        ['label' => $timber_post->title()],
    ]);

$context['schema'] = $schema->toJson();

if (post_password_required($timber_post->ID)) {
    Timber::render('single-password.twig', $context);
} else {
    Timber::render('single-project.twig', $context);
}
