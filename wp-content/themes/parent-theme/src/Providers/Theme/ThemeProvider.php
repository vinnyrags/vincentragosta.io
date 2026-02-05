<?php

namespace ParentTheme\Providers\Theme;

use ParentTheme\Providers\ServiceProvider;
use ParentTheme\Providers\Theme\Features\DisableBlocks;
use ParentTheme\Providers\Theme\Features\DisableComments;
use ParentTheme\Providers\Theme\Features\DisablePosts;
use ParentTheme\Providers\Theme\Features\EnableSvgUploads;

/**
 * Handles core theme setup, configuration, and asset enqueueing.
 *
 * Registers standard WordPress theme supports and enqueues all
 * frontend and editor assets. Can be extended by child themes
 * for additional functionality.
 */
class ThemeProvider extends ServiceProvider
{
    /**
     * Theme handle prefix for asset registration.
     *
     * @var string
     */
    protected string $handlePrefix = 'theme';

    /**
     * Features to register with this provider.
     *
     * @var array<class-string>
     */
    protected array $features = [
        DisableBlocks::class,
        DisableComments::class,
        DisablePosts::class,
        EnableSvgUploads::class,
    ];

    public function register(): void
    {
        add_action('after_setup_theme', [$this, 'addThemeSupports']);

        // Core asset enqueueing
        add_action('wp_enqueue_scripts', [$this, 'enqueueFrontendAssets']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueueEditorAssets']);
        add_action('enqueue_block_assets', [$this, 'enqueueBlockAssets']);

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

    /**
     * Enqueue frontend scripts and styles.
     */
    public function enqueueFrontendAssets(): void
    {
        // Parent theme's theme.css uses get_template_directory() (parent path),
        // while AssetManager uses get_stylesheet_directory() (active theme path).
        // Keep this as a direct call to preserve the correct base path.
        $parent_style_path = get_template_directory() . '/dist/css/theme.css';
        if (file_exists($parent_style_path)) {
            wp_enqueue_style(
                $this->handlePrefix . '-style',
                get_template_directory_uri() . '/dist/css/theme.css',
                [],
                filemtime($parent_style_path)
            );
        }

        $this->enqueueScript($this->handlePrefix . '-frontend-js', 'frontend.js');
    }

    /**
     * Enqueue block editor scripts.
     */
    public function enqueueEditorAssets(): void
    {
        $this->enqueueManifestScript($this->handlePrefix . '-blocks-js', 'blocks/index.js');
    }

    /**
     * Enqueue block styles for frontend and editor.
     */
    public function enqueueBlockAssets(): void
    {
        $this->enqueueDistStyle($this->handlePrefix . '-blocks-style', 'blocks/style-index.css');

        if (is_admin()) {
            $this->enqueueDistStyle(
                $this->handlePrefix . '-blocks-editor-style',
                'blocks/index.css',
                ['wp-edit-blocks', $this->handlePrefix . '-blocks-style']
            );
        }
    }
}
