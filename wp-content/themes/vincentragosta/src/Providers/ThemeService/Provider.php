<?php

namespace ChildTheme\Providers\ThemeService;

use ChildTheme\Providers\ServiceProvider;
use ChildTheme\Providers\ThemeService\Features\DisableComments;

/**
 * Handles core theme setup and configuration.
 */
class Provider extends ServiceProvider
{
    protected array $features = [
        DisableComments::class,
    ];

    public function register(): void
    {
        add_action('after_setup_theme', [$this, 'addThemeSupports']);
        add_filter('show_admin_bar', '__return_false');

        parent::register();
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
