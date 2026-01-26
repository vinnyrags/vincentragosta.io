<?php
/**
 * Parent Theme functions and definitions.
 *
 * @package ParentTheme
 */

declare(strict_types=1);

// Theme version constant.
define('PARENT_THEME_VERSION', '1.0.0');

/**
 * Sets up theme defaults and registers support for various WordPress features.
 */
function parent_theme_setup(): void
{
    // Add default posts and comments RSS feed links to head.
    add_theme_support('automatic-feed-links');

    // Let WordPress manage the document title.
    add_theme_support('title-tag');

    // Enable support for Post Thumbnails.
    add_theme_support('post-thumbnails');

    // Switch default core markup to output valid HTML5.
    add_theme_support('html5', [
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'style',
        'script',
    ]);

    // Add support for block styles.
    add_theme_support('wp-block-styles');

    // Add support for editor styles.
    add_theme_support('editor-styles');

    // Add support for responsive embedded content.
    add_theme_support('responsive-embeds');

    // Add support for custom spacing.
    add_theme_support('custom-spacing');

    // Add support for wide and full alignments.
    add_theme_support('align-wide');
}
add_action('after_setup_theme', 'parent_theme_setup');
