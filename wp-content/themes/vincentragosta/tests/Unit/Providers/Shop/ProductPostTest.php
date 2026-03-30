<?php

namespace ChildTheme\Tests\Unit\Providers\Shop;

use ChildTheme\Providers\Shop\ProductPost;
use IX\Models\Post;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the ProductPost model.
 */
class ProductPostTest extends TestCase
{
    public function testPostTypeConstant(): void
    {
        $this->assertEquals('product', ProductPost::POST_TYPE);
    }

    public function testExtendsBasePost(): void
    {
        $this->assertTrue(is_subclass_of(ProductPost::class, Post::class));
    }

    public function testIsOnSaleMethodSignature(): void
    {
        $reflection = new \ReflectionClass(ProductPost::class);
        $method = $reflection->getMethod('isOnSale');

        $this->assertTrue($method->isPublic());
        $this->assertEquals('bool', (string) $method->getReturnType());
    }

    public function testIsInStockMethodSignature(): void
    {
        $reflection = new \ReflectionClass(ProductPost::class);
        $method = $reflection->getMethod('isInStock');

        $this->assertTrue($method->isPublic());
        $this->assertEquals('bool', (string) $method->getReturnType());
    }

    public function testCheckoutPriceIdMethodSignature(): void
    {
        $reflection = new \ReflectionClass(ProductPost::class);
        $method = $reflection->getMethod('checkoutPriceId');

        $this->assertTrue($method->isPublic());
        $this->assertEquals('string', (string) $method->getReturnType());
    }

    public function testStripePriceIdMethodSignature(): void
    {
        $reflection = new \ReflectionClass(ProductPost::class);
        $method = $reflection->getMethod('stripePriceId');

        $this->assertTrue($method->isPublic());
        $this->assertEquals('string', (string) $method->getReturnType());
    }

    public function testStripeProductIdMethodSignature(): void
    {
        $reflection = new \ReflectionClass(ProductPost::class);
        $method = $reflection->getMethod('stripeProductId');

        $this->assertTrue($method->isPublic());
        $this->assertEquals('string', (string) $method->getReturnType());
    }

    public function testPriceMethodSignature(): void
    {
        $reflection = new \ReflectionClass(ProductPost::class);
        $method = $reflection->getMethod('price');

        $this->assertTrue($method->isPublic());
        $this->assertEquals('string', (string) $method->getReturnType());
    }

    public function testSalePriceMethodSignature(): void
    {
        $reflection = new \ReflectionClass(ProductPost::class);
        $method = $reflection->getMethod('salePrice');

        $this->assertTrue($method->isPublic());
        $this->assertEquals('string', (string) $method->getReturnType());
    }

    public function testCostMethodSignature(): void
    {
        $reflection = new \ReflectionClass(ProductPost::class);
        $method = $reflection->getMethod('cost');

        $this->assertTrue($method->isPublic());
        $this->assertEquals('string', (string) $method->getReturnType());
    }

    public function testSkuMethodSignature(): void
    {
        $reflection = new \ReflectionClass(ProductPost::class);
        $method = $reflection->getMethod('sku');

        $this->assertTrue($method->isPublic());
        $this->assertEquals('string', (string) $method->getReturnType());
    }

    public function testStockQuantityMethodSignature(): void
    {
        $reflection = new \ReflectionClass(ProductPost::class);
        $method = $reflection->getMethod('stockQuantity');

        $this->assertTrue($method->isPublic());
        $this->assertEquals('int', (string) $method->getReturnType());
    }

    public function testCardTypeSlugsMethodSignature(): void
    {
        $reflection = new \ReflectionClass(ProductPost::class);
        $method = $reflection->getMethod('cardTypeSlugs');

        $this->assertTrue($method->isPublic());
        $this->assertEquals('string', (string) $method->getReturnType());
    }
}
