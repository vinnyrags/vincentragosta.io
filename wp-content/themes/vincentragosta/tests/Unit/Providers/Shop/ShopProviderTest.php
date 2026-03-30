<?php

namespace ChildTheme\Tests\Unit\Providers\Shop;

use ChildTheme\Providers\Shop\Endpoints\CreateCheckoutEndpoint;
use ChildTheme\Providers\Shop\Endpoints\StripeWebhookEndpoint;
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

    public function testDeclaresBlocks(): void
    {
        $reflection = new \ReflectionClass(ShopProvider::class);
        $property = $reflection->getProperty('blocks');
        $property->setAccessible(true);

        $provider = $reflection->newInstanceWithoutConstructor();
        $blocks = $property->getValue($provider);

        $this->assertContains('products', $blocks);
    }

    public function testDeclaresRoutes(): void
    {
        $reflection = new \ReflectionClass(ShopProvider::class);
        $property = $reflection->getProperty('routes');
        $property->setAccessible(true);

        $provider = $reflection->newInstanceWithoutConstructor();
        $routes = $property->getValue($provider);

        $this->assertContains(CreateCheckoutEndpoint::class, $routes);
        $this->assertContains(StripeWebhookEndpoint::class, $routes);
    }

    public function testDeclaresHooks(): void
    {
        $reflection = new \ReflectionClass(ShopProvider::class);
        $property = $reflection->getProperty('hooks');
        $property->setAccessible(true);

        $provider = $reflection->newInstanceWithoutConstructor();
        $hooks = $property->getValue($provider);

        $this->assertContains(StockStatusBadge::class, $hooks);
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
