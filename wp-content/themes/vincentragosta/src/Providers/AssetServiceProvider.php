<?php

namespace ChildTheme\Providers;

/**
 * Handles all asset enqueueing for frontend and editor.
 */
class AssetServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueFrontendAssets']);
        add_action('wp_head', [$this, 'addFontPreconnects']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueueEditorAssets']);
        add_action('enqueue_block_assets', [$this, 'enqueueBlockAssets']);
    }

    /**
     * Enqueue frontend scripts and styles.
     */
    public function enqueueFrontendAssets(): void
    {
        wp_enqueue_style(
            'fira-code-font',
            'https://fonts.googleapis.com/css2?family=Fira+Code:wght@300;400;500;600;700&display=swap',
            [],
            null
        );

        wp_enqueue_style(
            'vincentragosta-style',
            get_stylesheet_uri(),
            [],
            wp_get_theme()->get('Version')
        );

        $script_path = get_template_directory() . '/dist/js/frontend.js';
        if (file_exists($script_path)) {
            wp_enqueue_script(
                'vincentragosta-frontend-js',
                get_template_directory_uri() . '/dist/js/frontend.js',
                [],
                wp_get_theme()->get('Version'),
                true
            );
        }
    }

    /**
     * Add preconnect links for Google Fonts.
     */
    public function addFontPreconnects(): void
    {
        echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
        echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
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
        $blocks_style_path = get_template_directory() . '/dist/blocks/style-index.css';
        if (file_exists($blocks_style_path)) {
            wp_enqueue_style(
                'vincentragosta-blocks-style',
                get_template_directory_uri() . '/dist/blocks/style-index.css',
                [],
                filemtime($blocks_style_path)
            );
        }

        if (is_admin()) {
            $blocks_editor_style_path = get_template_directory() . '/dist/blocks/index.css';
            if (file_exists($blocks_editor_style_path)) {
                wp_enqueue_style(
                    'vincentragosta-blocks-editor-style',
                    get_template_directory_uri() . '/dist/blocks/index.css',
                    ['wp-edit-blocks', 'vincentragosta-blocks-style'],
                    filemtime($blocks_editor_style_path)
                );
            }
        }
    }

    /**
     * Enqueue the master blocks script.
     */
    private function enqueueBlocksScript(): void
    {
        $asset_path = get_template_directory() . '/dist/blocks/index.asset.php';
        if (!file_exists($asset_path)) {
            return;
        }

        $asset = require $asset_path;
        wp_enqueue_script(
            'vincentragosta-blocks-js',
            get_template_directory_uri() . '/dist/blocks/index.js',
            $asset['dependencies'],
            $asset['version'],
            true
        );
    }

    /**
     * Enqueue the main editor script.
     */
    private function enqueueMainEditorScript(): void
    {
        $asset_path = get_template_directory() . '/dist/js/main.asset.php';
        if (!file_exists($asset_path)) {
            return;
        }

        $asset = require $asset_path;
        $dependencies = $asset['dependencies'];
        if (!in_array('wp-element', $dependencies, true)) {
            $dependencies[] = 'wp-element';
        }

        wp_enqueue_script(
            'vincentragosta-js',
            get_template_directory_uri() . '/dist/js/main.js',
            $dependencies,
            $asset['version'],
            true
        );

        wp_set_script_translations(
            'vincentragosta-js',
            'vincentragosta',
            get_template_directory() . '/languages'
        );
    }
}
