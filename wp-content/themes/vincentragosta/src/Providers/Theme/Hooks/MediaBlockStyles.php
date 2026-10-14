<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Theme\Hooks;

use Mythus\Hooks\BlockStyles;

/**
 * Registers custom block styles for media blocks (core/cover, core/image).
 */
class MediaBlockStyles extends BlockStyles
{
    protected function styles(): array
    {
        return [
            'core/cover' => [
                ['name' => 'animated', 'label' => __('Animated Background', 'vincentragosta')],
            ],
            'core/image' => [
                ['name' => 'paper', 'label' => __('Paper', 'vincentragosta')],
            ],
        ];
    }
}
