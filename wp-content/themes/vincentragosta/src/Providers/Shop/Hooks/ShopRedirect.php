<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop\Hooks;

use Mythus\Contracts\Hook;

/**
 * Redirects the old shop page to itzenzo.tv.
 *
 * The shop frontend has moved to itzenzo.tv. This hook issues a 301
 * redirect for any visitor hitting the WordPress shop page so bookmarks
 * and stale links point to the new storefront.
 */
class ShopRedirect implements Hook
{
    public function register(): void
    {
        add_action('template_redirect', [$this, 'redirectToFrontend']);
    }

    public function redirectToFrontend(): void
    {
        if (!defined('ITZENZO_FRONTEND_URL')) {
            return;
        }

        if (!function_exists('get_field')) {
            return;
        }

        $shopPageId = get_field('shop_page', 'option');

        if (!$shopPageId || !is_page((int) $shopPageId)) {
            return;
        }

        wp_redirect(ITZENZO_FRONTEND_URL, 301);
        exit;
    }
}
