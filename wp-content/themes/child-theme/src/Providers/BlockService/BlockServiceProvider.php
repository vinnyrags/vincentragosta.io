<?php

namespace ChildTheme\Providers\BlockService;

use ChildTheme\Providers\BlockService\Features\ButtonIconEnhancer;
use ChildTheme\Providers\BlockService\Features\CoverBlockStyles;
use ChildTheme\Providers\ServiceProvider;
use ChildTheme\Services\IconService;

/**
 * Handles block registration and block editor data.
 */
class BlockServiceProvider extends ServiceProvider
{
    /**
     * Blocks to register.
     */
    protected array $blocks = [
        'projects',
        'shutter-cards',
        'shutter-card',
    ];

    protected array $features = [
        ButtonIconEnhancer::class,
        CoverBlockStyles::class,
    ];

    public function register(): void
    {
        add_action('init', [$this, 'registerBlocks']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueueEditorAssets']);
        add_action('enqueue_block_editor_assets', [$this, 'localizeEditorData'], 99);

        parent::register();
    }

    /**
     * Enqueue frontend assets.
     */
    public function enqueueAssets(): void
    {
        $this->enqueueStyle('child-theme-block-service', 'block-service.css');
    }

    /**
     * Enqueue editor assets.
     */
    public function enqueueEditorAssets(): void
    {
        $this->enqueueStyle('child-theme-block-service', 'block-service.css');
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
     * Register custom blocks.
     */
    public function registerBlocks(): void
    {
        foreach ($this->blocks as $block) {
            $block_dir = get_stylesheet_directory() . '/blocks/' . $block;
            if (file_exists($block_dir . '/block.json')) {
                register_block_type($block_dir);
            }
        }
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
