<?php
/**
 * The front page template.
 *
 * This template is used when the site's front page is set to display
 * a static page or latest posts.
 *
 * @package  ParentTheme
 */

$context = Timber::context();

$timber_post     = Timber::get_post();
$context['post'] = $timber_post;

Timber::render(array('front-page.twig', 'page.twig'), $context);
