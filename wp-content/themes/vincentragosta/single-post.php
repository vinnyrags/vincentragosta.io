<?php
/**
 * Template for displaying single blog posts.
 *
 * Adds related posts to the Timber context via BlogRepository.
 */

use ChildTheme\Theme;
use IX\Providers\Blog\BlogRepository;
use IX\Services\SchemaBuilderService;
use Timber\Timber;

$context     = Timber::context();
$timber_post = Timber::get_post();
$context['post'] = $timber_post;

$categories = $timber_post->categories();

if (!empty($categories)) {
    $repository = Theme::container()->get(BlogRepository::class);
    $context['related'] = $repository->relatedRandom($categories[0]->slug);
}

// Structured data.
$wordCount = str_word_count(wp_strip_all_tags($timber_post->post_content));
$readingMinutes = max(1, (int) ceil($wordCount / 200));

$blogPageId = get_field('blog_page', 'option');
$blogPageUrl = $blogPageId ? get_permalink($blogPageId) : home_url('/nous-signal/');
$blogPageTitle = $blogPageId ? get_the_title($blogPageId) : 'Nous Signal';

$schema = new SchemaBuilderService();
$schema->add([
    '@context' => 'https://schema.org',
    '@type' => 'BlogPosting',
    'headline' => $timber_post->title(),
    'datePublished' => get_the_date('c', $timber_post->ID),
    'dateModified' => get_the_modified_date('c', $timber_post->ID),
    'author' => $schema->person(),
    'publisher' => $schema->organization(),
    'description' => wp_strip_all_tags((string) $timber_post->preview()->length(30)),
    'mainEntityOfPage' => get_permalink($timber_post->ID),
    'wordCount' => $wordCount,
    'timeRequired' => "PT{$readingMinutes}M",
])->breadcrumbList([
    ['label' => 'Home', 'url' => home_url('/')],
    ['label' => $blogPageTitle, 'url' => $blogPageUrl],
    ['label' => $timber_post->title()],
]);

$context['schema'] = $schema->toJson();

if (post_password_required($timber_post->ID)) {
    Timber::render('single-password.twig', $context);
} else {
    Timber::render('single-post.twig', $context);
}
