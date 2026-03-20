<?php

declare(strict_types=1);

namespace ParentTheme\Providers\Theme\Hooks;

use Mythus\Contracts\Hook;

/**
 * Registers focal point style variants for core/post-featured-image.
 *
 * When using aspect-ratio + object-fit: cover, the default focal point
 * is center. These styles let editors shift the focal point per instance
 * via the block toolbar's Styles panel.
 */
class FeaturedImageFocalPoint implements Hook
{
    public function register(): void
    {
        add_action('init', [$this, 'registerStyles']);
    }

    public function registerStyles(): void
    {
        $positions = [
            'focal-top'    => ['label' => 'Focal Top',    'position' => 'center top'],
            'focal-bottom' => ['label' => 'Focal Bottom', 'position' => 'center bottom'],
            'focal-left'   => ['label' => 'Focal Left',   'position' => 'left center'],
            'focal-right'  => ['label' => 'Focal Right',  'position' => 'right center'],
        ];

        foreach ($positions as $name => $config) {
            register_block_style('core/post-featured-image', [
                'name'         => $name,
                'label'        => __($config['label'], 'parent-theme'),
                'inline_style' => sprintf(
                    '.wp-block-post-featured-image.is-style-%s img { object-position: %s; }',
                    $name,
                    $config['position']
                ),
            ]);
        }
    }
}
