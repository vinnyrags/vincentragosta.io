<?php

namespace ChildTheme\Tests\Unit\Providers\Shop\Hooks;

use ChildTheme\Providers\Shop\Hooks\ShopRedirect;
use Mythus\Contracts\Hook;
use PHPUnit\Framework\TestCase;

class ShopRedirectTest extends TestCase
{
    public function testImplementsHook(): void
    {
        $this->assertInstanceOf(Hook::class, new ShopRedirect());
    }

    /**
     * @dataProvider redirectingPaths
     */
    public function testShouldRedirectMatchesShopPaths(string $path): void
    {
        $this->assertTrue(ShopRedirect::shouldRedirect($path), "expected '{$path}' to redirect");
    }

    public static function redirectingPaths(): array
    {
        return [
            'bare /shop' => ['/shop'],
            '/shop/ trailing slash' => ['/shop/'],
            '/shop/ subroute' => ['/shop/cart'],
            '/shop/ deep subroute' => ['/shop/checkout/success'],
            'mixed case' => ['/SHOP/'],
        ];
    }

    /**
     * @dataProvider nonRedirectingPaths
     */
    public function testShouldRedirectIgnoresOtherPaths(string $path): void
    {
        $this->assertFalse(ShopRedirect::shouldRedirect($path), "expected '{$path}' NOT to redirect");
    }

    public static function nonRedirectingPaths(): array
    {
        return [
            'home' => ['/'],
            'projects archive' => ['/projects/'],
            'shopping (substring, not prefix)' => ['/shopping/'],
            // Dodges the prefix-only-match guard — /shopper would otherwise
            // be a false positive if we used a naive str_starts_with('/shop').
            'shopper' => ['/shopper/'],
            'wp-admin' => ['/wp-admin/'],
            'graphql endpoint' => ['/graphql'],
            'rest endpoint' => ['/wp-json/shop/v1/checkout'],
        ];
    }
}
