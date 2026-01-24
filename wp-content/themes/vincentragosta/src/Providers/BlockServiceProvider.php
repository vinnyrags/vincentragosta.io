<?php

namespace ChildTheme\Providers;

use ChildTheme\Blocks\ButtonIconEnhancer;
use ChildTheme\Contracts\Registrable;
use ChildTheme\Services\Icon;

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

    /**
     * Block enhancers to register.
     *
     * @var array<class-string<Registrable>>
     */
    protected array $enhancers = [
        ButtonIconEnhancer::class,
    ];

    public function register(): void
    {
        add_action('init', [$this, 'registerBlocks']);
        add_action('enqueue_block_editor_assets', [$this, 'localizeEditorData'], 99);

        $this->registerEnhancers();
    }

    /**
     * Register all block enhancers.
     */
    protected function registerEnhancers(): void
    {
        foreach ($this->enhancers as $enhancer) {
            (new $enhancer())->register();
        }
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
            'svgOptions' => Icon::options('svg', __('Select SVG for Hero', 'vincentragosta')),
            'svgContent' => Icon::contentMap('svg'),
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
            'iconOptions' => Icon::options('sprite', __('— No Icon —', 'vincentragosta')),
            'iconContentMap' => Icon::contentMap('sprite'),
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
