<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Theme\Hooks;

use ParentTheme\Providers\Contracts\Hook;

/**
 * Registers custom block styles for text blocks (core/paragraph, core/list, core/code, core/table).
 */
class TextBlockStyles implements Hook
{
    public function register(): void
    {
        add_action('init', [$this, 'registerStyles']);
    }

    /**
     * Register block styles for text blocks.
     */
    public function registerStyles(): void
    {
        $mutedStyle = [
            'name'  => 'muted',
            'label' => __('Muted', 'child-theme'),
        ];

        register_block_style('core/paragraph', $mutedStyle);
        register_block_style('core/list', $mutedStyle);
        register_block_style('core/code', $mutedStyle);
        register_block_style('core/table', $mutedStyle);
        register_block_style('core/button', $mutedStyle);

        register_block_style('core/heading', [
            'name'  => 'subheading',
            'label' => __('Subheading', 'child-theme'),
        ]);
    }
}
