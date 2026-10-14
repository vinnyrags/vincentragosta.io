<?php
/**
 * Template for 404 (Not Found).
 *
 * The page body is the "Not Found" content partial (Appearance → Partials,
 * slug `not-found`), so its copy stays in the CMS. Uses the compact footer:
 * the body already offers the ways forward the CTA panel would.
 */

use IX\Providers\Theme\Features\ContentPartial\ContentPartialPost;
use Timber\Timber;

$context = Timber::context();

$find = static function (string $slug): ?WP_Post {
    $posts = get_posts([
        'name'           => $slug,
        'post_type'      => ContentPartialPost::POST_TYPE,
        'post_status'    => 'publish',
        'posts_per_page' => 1,
    ]);

    return $posts[0] ?? null;
};

$notFound = $find('not-found');
$context['not_found'] = $notFound ? apply_filters('the_content', $notFound->post_content) : null;

$compact = $find('footer-compact');
if ($compact) {
    $context['footer_partial'] = [
        'id'      => $compact->ID,
        'slug'    => $compact->post_name,
        'title'   => $compact->post_title,
        'content' => apply_filters('the_content', $compact->post_content),
    ];
}

Timber::render('404.twig', $context);
