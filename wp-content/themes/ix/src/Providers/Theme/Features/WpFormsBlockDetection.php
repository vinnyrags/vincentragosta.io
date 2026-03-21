<?php

declare(strict_types=1);

namespace IX\Providers\Theme\Features;

use Mythus\Contracts\Feature;

/**
 * Auto-detect WPForms blocks and ensure assets load on those pages.
 *
 * WPForms' per-page JS detection relies on $this->forms being populated
 * during block rendering, which can fail with Timber/Twig templates.
 * This feature hooks into WPForms' own wpforms_global_assets filter and
 * returns true only when the current page contains a WPForms block,
 * ensuring jQuery and all WPForms scripts load without forcing them
 * onto every page globally.
 *
 * Not included in parent's $features by default — child themes opt in
 * by adding WpFormsBlockDetection::class to their $features array.
 */
class WpFormsBlockDetection implements Feature
{
    public function register(): void
    {
        add_filter('wpforms_global_assets', [$this, 'loadAssetsForBlocks']);
    }

    /**
     * Enable WPForms asset loading on pages that contain a WPForms block.
     *
     * @param mixed $global Current global assets setting.
     * @return bool Whether to load assets globally for this request.
     */
    public function loadAssetsForBlocks(mixed $global): bool
    {
        if ($global) {
            return true;
        }

        if (is_singular()) {
            global $post;

            if ($post && has_block('wpforms/form-selector', $post)) {
                return true;
            }
        }

        return (bool) $global;
    }
}
