<?php

namespace ParentTheme\Providers\ThemeService;

use ParentTheme\Providers\ServiceProvider;
use ParentTheme\Providers\ThemeService\Features\DisableBlocks;
use ParentTheme\Providers\ThemeService\Features\DisableComments;

/**
 * Handles core theme setup and configuration.
 *
 * Registers standard WordPress theme supports and can be extended
 * by child themes for additional functionality.
 */
class ThemeServiceProvider extends ServiceProvider
{
    /**
     * Features to register with this provider.
     *
     * @var array<class-string>
     */
    protected array $features = [
        DisableBlocks::class,
        DisableComments::class,
    ];

    public function register(): void
    {
        add_action('after_setup_theme', [$this, 'addThemeSupports']);

        parent::register();
    }

    /**
     * Register theme supports.
     *
     * Child themes can override this method to add or remove supports.
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
