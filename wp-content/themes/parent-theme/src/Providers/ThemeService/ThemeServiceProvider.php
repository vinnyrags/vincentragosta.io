<?php

namespace ParentTheme\Providers\ThemeService;

use ParentTheme\Providers\ServiceProvider;
use ParentTheme\Providers\ThemeService\Features\DisableBlocks;
use ParentTheme\Providers\ThemeService\Features\DisableComments;
use ParentTheme\Providers\ThemeService\Features\DisablePosts;
use ParentTheme\Providers\ThemeService\Features\EnableSvgUploads;

/**
 * Handles core theme setup, configuration, and asset enqueueing.
 *
 * Registers standard WordPress theme supports and enqueues all
 * frontend and editor assets. Can be extended by child themes
 * for additional functionality.
 */
class ThemeServiceProvider extends ServiceProvider
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
        // Enqueue parent theme's main stylesheet from dist
        $parent_style_path = get_template_directory() . '/dist/css/main.css';
        if (file_exists($parent_style_path)) {
            wp_enqueue_style(
                $this->handlePrefix . '-style',
                get_template_directory_uri() . '/dist/css/main.css',
                [],
                filemtime($parent_style_path)
            );
        }

        $script_path = get_stylesheet_directory() . '/dist/js/frontend.js';
        if (file_exists($script_path)) {
            wp_enqueue_script(
                $this->handlePrefix . '-frontend-js',
                get_stylesheet_directory_uri() . '/dist/js/frontend.js',
                [],
                filemtime($script_path),
                true
            );
        }
    }

    /**
     * Enqueue block editor scripts.
     */
    public function enqueueEditorAssets(): void
    {
        $this->enqueueBlocksScript();
        $this->enqueueMainEditorScript();
    }

    /**
     * Enqueue block styles for frontend and editor.
     */
    public function enqueueBlockAssets(): void
    {
        $blocks_style_path = get_stylesheet_directory() . '/dist/blocks/style-index.css';
        if (file_exists($blocks_style_path)) {
            wp_enqueue_style(
                $this->handlePrefix . '-blocks-style',
                get_stylesheet_directory_uri() . '/dist/blocks/style-index.css',
                [],
                filemtime($blocks_style_path)
            );
        }

        if (is_admin()) {
            $blocks_editor_style_path = get_stylesheet_directory() . '/dist/blocks/index.css';
            if (file_exists($blocks_editor_style_path)) {
                wp_enqueue_style(
                    $this->handlePrefix . '-blocks-editor-style',
                    get_stylesheet_directory_uri() . '/dist/blocks/index.css',
                    ['wp-edit-blocks', $this->handlePrefix . '-blocks-style'],
                    filemtime($blocks_editor_style_path)
                );
            }
        }
    }

    /**
     * Enqueue the master blocks script.
     */
    protected function enqueueBlocksScript(): void
    {
        $asset_path = get_stylesheet_directory() . '/dist/blocks/index.asset.php';
        if (!file_exists($asset_path)) {
            return;
        }

        $asset = require $asset_path;
        wp_enqueue_script(
            $this->handlePrefix . '-blocks-js',
            get_stylesheet_directory_uri() . '/dist/blocks/index.js',
            $asset['dependencies'],
            $asset['version'],
            true
        );
    }

    /**
     * Enqueue the main editor script.
     */
    protected function enqueueMainEditorScript(): void
    {
        $asset_path = get_stylesheet_directory() . '/dist/js/main.asset.php';
        if (!file_exists($asset_path)) {
            return;
        }

        $asset = require $asset_path;
        $dependencies = $asset['dependencies'];
        if (!in_array('wp-element', $dependencies, true)) {
            $dependencies[] = 'wp-element';
        }

        wp_enqueue_script(
            $this->handlePrefix . '-js',
            get_stylesheet_directory_uri() . '/dist/js/main.js',
            $dependencies,
            $asset['version'],
            true
        );

        wp_set_script_translations(
            $this->handlePrefix . '-js',
            $this->handlePrefix,
            get_stylesheet_directory() . '/languages'
        );
    }
}
