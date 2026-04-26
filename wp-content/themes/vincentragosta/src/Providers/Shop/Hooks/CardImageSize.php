<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop\Hooks;

use Mythus\Contracts\Hook;

/**
 * Registers a custom image size for the /cards catalog grid.
 *
 * Card originals come from the Pokemon TCG API at 734x1024 PNG. WordPress
 * skips its medium_large (768) and large (1024) sizes because the source
 * is narrower than 768px, leaving only medium (215x300) and the original.
 * Without this hook, the GraphQL query falls back to the full-resolution
 * original for every card in the grid — a flood of half-megabyte PNGs.
 */
class CardImageSize implements Hook
{
    public function register(): void
    {
        add_action('after_setup_theme', [$this, 'addImageSize']);
    }

    public function addImageSize(): void
    {
        add_image_size('card-grid', 600, 836, false);
    }
}
