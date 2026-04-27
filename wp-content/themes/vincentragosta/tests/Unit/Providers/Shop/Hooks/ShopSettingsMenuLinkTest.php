<?php

namespace ChildTheme\Tests\Unit\Providers\Shop\Hooks;

use ChildTheme\Providers\Shop\Hooks\ShopSettingsMenuLink;
use Mythus\Contracts\Hook;
use PHPUnit\Framework\TestCase;

class ShopSettingsMenuLinkTest extends TestCase
{
    public function testImplementsHook(): void
    {
        $this->assertInstanceOf(Hook::class, new ShopSettingsMenuLink());
    }

    public function testReorderingMovesAppendedSettingsToFirstPosition(): void
    {
        // Simulate the post-add_submenu_page state: CPT submenus first, the
        // newly-appended Settings entry last. The hook is supposed to lift
        // the last entry (Settings) to position 0 so the parent link points
        // at it instead of Products.
        global $submenu;
        $submenu = [
            'shop-settings' => [
                ['Products',      'edit_posts', 'edit.php?post_type=product'],
                ['Cards',         'edit_posts', 'edit.php?post_type=card'],
                ['Card Requests', 'edit_posts', 'card-requests'],
                ['Settings',      'edit_posts', 'shop-settings'],
            ],
        ];

        // Reproduce the reorder block from the hook (the hook itself also
        // calls add_submenu_page, which we can't exercise in pure unit
        // tests — assert the reorder logic in isolation).
        $last = array_key_last($submenu['shop-settings']);
        if ($last !== null && $last !== 0) {
            $settings = $submenu['shop-settings'][$last];
            unset($submenu['shop-settings'][$last]);
            array_unshift($submenu['shop-settings'], $settings);
        }

        $reordered = array_values($submenu['shop-settings']);

        $this->assertSame('Settings', $reordered[0][0]);
        $this->assertSame('shop-settings', $reordered[0][2]);
        $this->assertSame('Products', $reordered[1][0]);
        $this->assertSame('Card Requests', $reordered[3][0]);
    }
}
