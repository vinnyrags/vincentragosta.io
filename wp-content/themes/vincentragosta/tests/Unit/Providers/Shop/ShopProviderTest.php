<?php

namespace ChildTheme\Tests\Unit\Providers\Shop;

use ChildTheme\Providers\Shop\Endpoints\CurrentPackBattleEndpoint;
use ChildTheme\Providers\Shop\Endpoints\PullBoxClaimEndpoint;
use ChildTheme\Providers\Shop\Endpoints\StockDecrementEndpoint;
use ChildTheme\Providers\Shop\Hooks\ShopSettingsMenuLink;
use ChildTheme\Providers\Shop\Hooks\StockStatusBadge;
use ChildTheme\Providers\Shop\ShopProvider;
use IX\Providers\Provider;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the ShopProvider.
 */
class ShopProviderTest extends TestCase
{
    public function testExtendsProvider(): void
    {
        $this->assertTrue(is_subclass_of(ShopProvider::class, Provider::class));
    }

    public function testNoBlocksRegistered(): void
    {
        $reflection = new \ReflectionClass(ShopProvider::class);
        $property = $reflection->getProperty('blocks');
        $property->setAccessible(true);

        $provider = $reflection->newInstanceWithoutConstructor();
        $blocks = $property->getValue($provider);

        $this->assertEmpty($blocks, 'Products block moved to itzenzo.tv — no blocks should be registered');
    }

    public function testDeclaresRoutes(): void
    {
        $reflection = new \ReflectionClass(ShopProvider::class);
        $property = $reflection->getProperty('routes');
        $property->setAccessible(true);

        $provider = $reflection->newInstanceWithoutConstructor();
        $routes = $property->getValue($provider);

        // Kept: headless data + queue/pull-box endpoints.
        $this->assertContains(CurrentPackBattleEndpoint::class, $routes);
        $this->assertContains(PullBoxClaimEndpoint::class, $routes);
        $this->assertContains(StockDecrementEndpoint::class, $routes);

        // Retired: Stripe checkout/webhook routes must be gone entirely.
        foreach ($routes as $route) {
            $this->assertStringNotContainsString('Checkout', $route, 'Checkout endpoints are retired');
            $this->assertNotSame('ChildTheme\\Providers\\Shop\\Endpoints\\StripeWebhookEndpoint', $route);
        }
    }

    public function testDeclaresHooks(): void
    {
        $reflection = new \ReflectionClass(ShopProvider::class);
        $property = $reflection->getProperty('hooks');
        $property->setAccessible(true);

        $provider = $reflection->newInstanceWithoutConstructor();
        $hooks = $property->getValue($provider);

        $this->assertContains(StockStatusBadge::class, $hooks);
        $this->assertContains(ShopSettingsMenuLink::class, $hooks);
    }

    public function testRouteNamespace(): void
    {
        $reflection = new \ReflectionClass(ShopProvider::class);
        $property = $reflection->getProperty('routeNamespace');
        $property->setAccessible(true);

        $provider = $reflection->newInstanceWithoutConstructor();
        $this->assertEquals('shop', $property->getValue($provider));
    }
}
