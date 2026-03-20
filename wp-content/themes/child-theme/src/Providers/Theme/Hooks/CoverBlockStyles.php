<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Theme\Hooks;

use Mythus\Hooks\BlockStyles;

/**
 * Registers custom block styles for core/cover.
 */
class CoverBlockStyles extends BlockStyles
{
    protected function styles(): array
    {
        return [
            'core/cover' => [
                ['name' => 'animated', 'label' => __('Animated Background', 'child-theme')],
            ],
        ];
    }
}
