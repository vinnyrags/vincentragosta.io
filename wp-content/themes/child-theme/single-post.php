<?php
/**
 * Template for displaying single blog posts.
 *
 * Adds related posts to the Timber context via BlogRepository.
 */

use ChildTheme\Theme;
use IX\Providers\Blog\BlogRepository;
use Timber\Timber;

$context     = Timber::context();
$timber_post = Timber::get_post();
$context['post'] = $timber_post;

$categories = $timber_post->categories();

if (!empty($categories)) {
    $repository = Theme::container()->get(BlogRepository::class);
    $context['related'] = $repository->relatedRandom($categories[0]->slug);
}

if (post_password_required($timber_post->ID)) {
    Timber::render('single-password.twig', $context);
} else {
    Timber::render('single-post.twig', $context);
}
