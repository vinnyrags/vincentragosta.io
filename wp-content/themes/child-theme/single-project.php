<?php
/**
 * Template for displaying single projects.
 *
 * Adds related projects to the Timber context via ProjectRepository.
 */

use ChildTheme\Theme;
use ParentTheme\Providers\Project\ProjectRepository;
use Timber\Timber;

$context     = Timber::context();
$timber_post = Timber::get_post();
$context['post'] = $timber_post;

$categories = $timber_post->categories();

if (!empty($categories)) {
    $repository = Theme::container()->get(ProjectRepository::class);
    $context['related'] = $repository->relatedRandom($categories[0]->slug);
}

if (post_password_required($timber_post->ID)) {
    Timber::render('single-password.twig', $context);
} else {
    Timber::render('single-project.twig', $context);
}
