<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop\Hooks;

use Mythus\Contracts\Hook;

/**
 * Adds an explicit "Settings" submenu under the itzenzo.tv (shop-settings)
 * top-level menu so the ACF options page is reachable from the sidebar.
 *
 * Without this, the only submenus are the CPTs (Products, Cards) and
 * Card Requests — none of which point at the parent's own page. WordPress
 * then promotes the first registered submenu (Products) as the click
 * target of the parent label, leaving no visible path to the settings.
 *
 * Hooked at admin_menu priority 100 — after ACF Pro registers the parent
 * page at priority 99, after CPT submenus at priority 10, and after this
 * provider's other admin_menu hooks. Using parent_slug=menu_slug means
 * the submenu link routes to the parent's existing ACF callback, so no
 * separate render method is needed.
 */
class ShopSettingsMenuLink implements Hook
{
    public function register(): void
    {
        add_action('admin_menu', [$this, 'addLink'], 100);
    }

    public function addLink(): void
    {
        global $submenu;

        add_submenu_page(
            'shop-settings',
            __('itzenzo.tv Settings', 'vincentragosta'),
            __('Settings', 'vincentragosta'),
            'edit_posts',
            'shop-settings'
        );

        // add_submenu_page appends; WordPress points the parent's sidebar
        // link at the first submenu, so the Settings link has to lead the
        // list or clicking "itzenzo.tv" lands on Products (the next first).
        if (isset($submenu['shop-settings']) && is_array($submenu['shop-settings'])) {
            $last = array_key_last($submenu['shop-settings']);
            if ($last !== null && $last !== 0) {
                $settings = $submenu['shop-settings'][$last];
                unset($submenu['shop-settings'][$last]);
                array_unshift($submenu['shop-settings'], $settings);
            }
        }
    }
}
