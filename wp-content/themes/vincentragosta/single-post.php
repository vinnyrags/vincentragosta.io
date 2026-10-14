<?php
/**
 * Template for displaying single Nous Signal transmissions.
 *
 * The header (breadcrumb, way back to the archive, title, signal number,
 * lead, meta strip) and the previous / next links render from the post; the
 * body is the post content, with a Contents card built from its anchored H2s.
 */

use ChildTheme\Providers\Blog\NousSequence;
use ChildTheme\Providers\Theme\TableOfContents;
use IX\Services\SchemaBuilderService;
use Timber\Timber;

$context     = Timber::context();
$timber_post = Timber::get_post();
$context['post'] = $timber_post;

$blogPageId    = function_exists('get_field') ? get_field('blog_page', 'option') : null;
$blogPageUrl   = $blogPageId ? get_permalink($blogPageId) : home_url('/nous-signal/');
$blogPageTitle = $blogPageId ? get_the_title($blogPageId) : 'Nous Signal';
$context['archive_url'] = $blogPageUrl;

// Place in the run: signal number + previous / next.
$sequence = NousSequence::locate(get_posts([
    'post_type'   => 'post',
    'post_status' => 'publish',
    'numberposts' => -1,
    'orderby'     => 'date',
    'order'       => 'ASC',
    'fields'      => 'ids',
]), $timber_post->ID);

$context['sequence'] = $sequence;
$context['previous'] = $sequence && $sequence['previous'] ? Timber::get_post($sequence['previous']) : null;
$context['next']     = $sequence && $sequence['next'] ? Timber::get_post($sequence['next']) : null;

$context['toc'] = TableOfContents::fromBlocks(parse_blocks($timber_post->post_content));

// Structured data.
$schema = new SchemaBuilderService();
$schema->add([
    '@context' => 'https://schema.org',
    '@type' => 'BlogPosting',
    'headline' => $timber_post->title(),
    'datePublished' => get_the_date('c', $timber_post->ID),
    'dateModified' => get_the_modified_date('c', $timber_post->ID),
    'author' => $schema->person(),
    'publisher' => $schema->organization(),
    'description' => wp_strip_all_tags($timber_post->post_excerpt ?: wp_trim_words(wp_strip_all_tags($timber_post->post_content), 30)),
    'mainEntityOfPage' => get_permalink($timber_post->ID),
    'wordCount' => $timber_post->wordCount(),
    'timeRequired' => 'PT' . $timber_post->readingTime() . 'M',
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
