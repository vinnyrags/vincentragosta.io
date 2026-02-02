<?php

namespace ChildTheme\Providers\ThemeService;

use ChildTheme\Providers\ThemeService\Features\ButtonIconEnhancer;
use ChildTheme\Providers\ThemeService\Features\CoverBlockStyles;
use ParentTheme\Providers\ThemeService\Features\DisableBlocks;
use ParentTheme\Providers\ThemeService\Features\DisableComments;
use ParentTheme\Providers\ThemeService\Features\DisablePosts;
use ParentTheme\Providers\ThemeService\Features\EnableSvgUploads;
use ParentTheme\Providers\ThemeService\ThemeServiceProvider as BaseThemeServiceProvider;
use ParentTheme\Services\IconService;

/**
 * Handles core theme setup and configuration.
 *
 * Extends the parent theme's ThemeService Provider to add site-specific functionality.
 * Includes shutter-cards blocks for interactive card components, button icon
 * enhancements, and cover block styles.
 */
class ThemeServiceProvider extends BaseThemeServiceProvider
{
    /**
     * Features to register.
     *
     * Includes parent theme features plus child theme additions.
     */
    protected array $features = [
        // Parent theme features
        DisableBlocks::class,
        DisableComments::class,
        DisablePosts::class,
        EnableSvgUploads::class,
        // Child theme features
        ButtonIconEnhancer::class,
        CoverBlockStyles::class,
    ];

    /**
     * Blocks to register.
     */
    protected array $blocks = [
        'shutter-cards',
        'shutter-card',
    ];

    public function register(): void
    {
        // Add site-specific hooks
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_filter('show_admin_bar', '__return_false');

        // Block editor assets and data localization
        add_action('enqueue_block_editor_assets', [$this, 'enqueueButtonEditorAssets']);
        add_action('enqueue_block_editor_assets', [$this, 'localizeEditorData'], 99);

        // Call parent to register theme supports, features, and blocks
        parent::register();
    }

    /**
     * Enqueue frontend assets for this theme.
     */
    public function enqueueAssets(): void
    {
        $this->enqueueStyle('child-theme-theme-service', 'theme-service.css');
    }

    /**
     * Enqueue block assets for frontend and editor.
     */
    public function enqueueBlockAssets(): void
    {
        $this->enqueueStyle('child-theme-shutter-cards-block', 'shutter-cards.css');
        $this->enqueueStyle('child-theme-shutter-card-block', 'shutter-card.css');

        // Editor-only styles (enqueued here for iframe compatibility)
        if (is_admin()) {
            $this->enqueueStyle('child-theme-shutter-cards-block-editor', 'shutter-cards-editor.css');
            $this->enqueueStyle('child-theme-shutter-card-block-editor', 'shutter-card-editor.css');
        }
    }

    /**
     * Enqueue block editor assets.
     */
    public function enqueueBlockEditorAssets(): void
    {
        $this->enqueueEditorScript('child-theme-shutter-cards-block-editor', 'shutter-cards.js');
        $this->enqueueEditorScript('child-theme-shutter-card-block-editor', 'shutter-card.js');
    }

    /**
     * Enqueue button icon picker editor assets.
     */
    public function enqueueButtonEditorAssets(): void
    {
        $this->enqueueScript('child-theme-block-service-js', 'button.js', [
            'wp-blocks',
            'wp-element',
            'wp-block-editor',
            'wp-components',
            'wp-compose',
            'wp-hooks',
        ]);
    }

    /**
     * Localize data for the block editor.
     */
    public function localizeEditorData(): void
    {
        $this->localizeButtonIconData();
    }

    /**
     * Localize icon data for the button icon picker.
     */
    private function localizeButtonIconData(): void
    {
        $handle = 'child-theme-block-service-js';
        if (!$this->isScriptActive($handle)) {
            return;
        }

        wp_localize_script($handle, 'childThemeButtonIconData', [
            'iconOptions' => IconService::options('sprite', __('— No Icon —', 'child-theme')),
            'iconContentMap' => IconService::contentMap('sprite'),
        ]);
    }

    /**
     * Check if a script is registered or enqueued.
     */
    private function isScriptActive(string $handle): bool
    {
        return wp_script_is($handle, 'registered') || wp_script_is($handle, 'enqueued');
    }
}
