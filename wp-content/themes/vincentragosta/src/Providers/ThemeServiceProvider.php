<?php

namespace ChildTheme\Providers;

/**
 * Handles core theme setup and configuration.
 */
class ThemeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        add_action('after_setup_theme', [$this, 'addThemeSupports']);
        add_filter('show_admin_bar', '__return_false');
    }

    /**
     * Register theme supports.
     */
    public function addThemeSupports(): void
    {
        add_theme_support('automatic-feed-links');
        add_theme_support('title-tag');
        add_theme_support('post-thumbnails');
        add_theme_support('menus');
        add_theme_support('html5', [
            'comment-form',
            'comment-list',
            'gallery',
            'caption',
            'style',
            'script',
        ]);
        add_theme_support('editor-styles');
        add_theme_support('wp-block-styles');
        add_theme_support('layout');
        add_theme_support('custom-spacing');
        add_theme_support('align-wide');

        add_editor_style('style.css');
    }
}
