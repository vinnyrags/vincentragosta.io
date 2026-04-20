<?php
/**
 * Template for displaying single projects.
 *
 * Adds related projects to the Timber context via ProjectRepository.
 */

use ChildTheme\Theme;
use IX\Providers\Project\ProjectRepository;
use IX\Services\SchemaBuilderService;
use Timber\Timber;

$context     = Timber::context();
$timber_post = Timber::get_post();
$context['post'] = $timber_post;

$categories = $timber_post->categories();

if (!empty($categories)) {
    $repository = Theme::container()->get(ProjectRepository::class);
    $context['related'] = $repository->relatedRandom($categories[0]->slug);
}

// Structured data.
$year = method_exists($timber_post, 'sortYear') ? (string) $timber_post->sortYear() : null;
$thumbnail = $timber_post->thumbnail();

$creativeWork = [
    '@context' => 'https://schema.org',
    '@type' => 'CreativeWork',
    'name' => $timber_post->title(),
    'description' => wp_strip_all_tags($timber_post->post_excerpt ?: wp_trim_words(wp_strip_all_tags($timber_post->post_content), 30)),
    'url' => get_permalink($timber_post->ID),
    'creator' => (new SchemaBuilderService())->person(),
];

if ($year) {
    $creativeWork['dateCreated'] = $year;
}

if ($thumbnail) {
    $creativeWork['image'] = $thumbnail->src();
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
