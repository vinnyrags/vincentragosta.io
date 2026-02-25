<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Theme\Hooks;

use ParentTheme\Providers\Contracts\Hook;

/**
 * Registers custom block styles for core/paragraph.
 */
class ParagraphBlockStyles implements Hook
{
    public function register(): void
    {
        add_action('init', [$this, 'registerStyles']);
    }

    /**
     * Register block styles for core/paragraph.
     */
    public function registerStyles(): void
    {
        register_block_style('core/paragraph', [
            'name'  => 'muted',
            'label' => __('Muted', 'child-theme'),
        ]);
    }
}
