<?php

namespace ChildTheme\Tests\Unit\Providers\Shop;

use ChildTheme\Providers\Shop\Hooks\CardGraphQL;
use ChildTheme\Providers\Shop\Hooks\ShopRedirect;
use ChildTheme\Providers\Shop\Hooks\ShopSettingsMenuLink;
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

    public function testRegistersNoRoutes(): void
    {
        $reflection = new \ReflectionClass(ShopProvider::class);
        $property = $reflection->getProperty('routes');
        $property->setAccessible(true);

        $provider = $reflection->newInstanceWithoutConstructor();

        $this->assertEmpty(
            $property->getValue($provider),
            'The shop REST surface is retired — itzenzo.tv reads cards and products over GraphQL'
        );
    }

    public function testDeclaresHooks(): void
    {
        $reflection = new \ReflectionClass(ShopProvider::class);
        $property = $reflection->getProperty('hooks');
        $property->setAccessible(true);

        $provider = $reflection->newInstanceWithoutConstructor();
        $hooks = $property->getValue($provider);

        $this->assertContains(CardGraphQL::class, $hooks);
        $this->assertContains(ShopRedirect::class, $hooks);
        $this->assertContains(ShopSettingsMenuLink::class, $hooks);
    }
}
