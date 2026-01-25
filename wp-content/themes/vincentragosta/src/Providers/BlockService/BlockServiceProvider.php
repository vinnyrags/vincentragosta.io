<?php

namespace ChildTheme\Providers\BlockService;

use ChildTheme\Providers\BlockService\Features\ButtonIconEnhancer;
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
        'hero',
        'projects',
        'shutter-cards',
        'shutter-card',
    ];

    protected array $features = [
        ButtonIconEnhancer::class,
    ];

    public function register(): void
    {
        add_action('init', [$this, 'registerBlocks']);
        add_action('enqueue_block_editor_assets', [$this, 'localizeEditorData'], 99);

        parent::register();
    }

    /**
     * Register custom blocks.
     */
    public function registerBlocks(): void
    {
        foreach ($this->blocks as $block) {
            $block_dir = get_template_directory() . '/blocks/' . $block;
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
        $this->localizeHeroBlockData();
        $this->localizeButtonIconData();
    }

    /**
     * Localize SVG data for the Hero block.
     */
    private function localizeHeroBlockData(): void
    {
        $handle = 'vincentragosta-blocks-js';
        if (!$this->isScriptActive($handle)) {
            return;
        }

        wp_localize_script($handle, 'vincentragostaHeroBlockData', [
            'svgOptions' => IconService::options('svg', __('Select Squiggle', 'vincentragosta')),
            'svgContent' => IconService::contentMap('svg'),
        ]);
    }

    /**
     * Localize icon data for the button icon picker.
     */
    private function localizeButtonIconData(): void
    {
        $handle = 'vincentragosta-js';
        if (!$this->isScriptActive($handle)) {
            return;
        }

        wp_localize_script($handle, 'vincentragostaButtonIconData', [
            'iconOptions' => IconService::options('sprite', __('— No Icon —', 'vincentragosta')),
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
