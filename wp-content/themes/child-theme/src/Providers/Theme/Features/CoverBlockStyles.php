<?php

namespace ChildTheme\Providers\Theme\Features;

use ParentTheme\Providers\Contracts\Registrable;

/**
 * Registers custom block styles for core/cover.
 */
class CoverBlockStyles implements Registrable
{
    public function register(): void
    {
        add_action('init', [$this, 'registerStyles']);
    }

    /**
     * Register block styles for core/cover.
     */
    public function registerStyles(): void
    {
        register_block_style('core/cover', [
            'name'  => 'animated',
            'label' => __('Animated Background', 'child-theme'),
        ]);
    }
}
