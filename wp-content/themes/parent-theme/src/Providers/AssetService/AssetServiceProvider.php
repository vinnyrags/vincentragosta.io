<?php

namespace ParentTheme\Providers\AssetService;

use ParentTheme\Providers\ServiceProvider;

/**
 * Base asset service provider for frontend and editor assets.
 *
 * Child themes should extend this class and override methods as needed.
 * Uses get_stylesheet_directory() to load assets from the child theme.
 */
class AssetServiceProvider extends ServiceProvider
{
    /**
     * Theme handle prefix for asset registration.
     *
     * @var string
     */
    protected string $handlePrefix = 'theme';

    public function register(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueFrontendAssets']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueueEditorAssets']);
        add_action('enqueue_block_assets', [$this, 'enqueueBlockAssets']);
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
