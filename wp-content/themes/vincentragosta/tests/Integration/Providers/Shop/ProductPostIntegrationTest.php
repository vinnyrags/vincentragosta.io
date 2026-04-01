<?php

declare(strict_types=1);

namespace ChildTheme\Tests\Integration\Providers\Shop;

use ChildTheme\Tests\Mocks\MockProductPost;
use PHPUnit\Framework\TestCase;

/**
 * Tests for ProductPost field accessor logic.
 *
 * Uses MockProductPost to bypass Timber's protected constructor
 * while testing the actual business logic in the accessors.
 */
class ProductPostIntegrationTest extends TestCase
{
    // =====================================================================
    // Stock
    // =====================================================================

    public function testIsInStockWithPositiveQuantity(): void
    {
        $product = MockProductPost::create([], ['stock_quantity' => 5]);
        $this->assertTrue($product->isInStock());
        $this->assertEquals(5, $product->stockQuantity());
    }

    public function testIsNotInStockWithZero(): void
    {
        $product = MockProductPost::create([], ['stock_quantity' => 0]);
        $this->assertFalse($product->isInStock());
        $this->assertEquals(0, $product->stockQuantity());
    }

    public function testIsNotInStockWithMissingMeta(): void
    {
        $product = MockProductPost::create([], []);
        $this->assertFalse($product->isInStock());
        $this->assertEquals(0, $product->stockQuantity());
    }

    // =====================================================================
    // Stripe IDs
    // =====================================================================

    public function testStripePriceId(): void
    {
        $product = MockProductPost::create([], ['stripe_price_id' => 'price_abc123']);
        $this->assertEquals('price_abc123', $product->stripePriceId());
    }

    public function testStripeProductId(): void
    {
        $product = MockProductPost::create([], ['stripe_product_id' => 'prod_xyz789']);
        $this->assertEquals('prod_xyz789', $product->stripeProductId());
    }

    public function testMissingStripeIdsReturnEmptyString(): void
    {
        $product = MockProductPost::create([], []);
        $this->assertEquals('', $product->stripePriceId());
        $this->assertEquals('', $product->stripeProductId());
    }

    // =====================================================================
    // Checkout price (sale vs regular)
    // =====================================================================

    public function testCheckoutPriceIdReturnsRegularWhenNoSale(): void
    {
        $product = MockProductPost::create([], [
            'stripe_price_id' => 'price_regular',
        ]);
        $this->assertEquals('price_regular', $product->checkoutPriceId());
    }

    public function testCheckoutPriceIdReturnsSaleWhenOnSale(): void
    {
        $product = MockProductPost::create([], [
            'stripe_price_id' => 'price_regular',
            'sale_price_id'   => 'price_sale',
        ]);
        $this->assertEquals('price_sale', $product->checkoutPriceId());
    }

    public function testIsOnSaleWhenSalePriceIdExists(): void
    {
        $product = MockProductPost::create([], ['sale_price_id' => 'price_sale']);
        $this->assertTrue($product->isOnSale());
    }

    public function testIsNotOnSaleWhenSalePriceIdEmpty(): void
    {
        $product = MockProductPost::create([], []);
        $this->assertFalse($product->isOnSale());
    }

    // =====================================================================
    // Price display
    // =====================================================================

    public function testPriceReturnsFormattedString(): void
    {
        $product = MockProductPost::create([], ['price' => '$24.99']);
        $this->assertEquals('$24.99', $product->price());
    }

    public function testSalePriceReturnsFormattedString(): void
    {
        $product = MockProductPost::create([], ['sale_price' => '$19.99']);
        $this->assertEquals('$19.99', $product->salePrice());
    }

    // =====================================================================
    // Other fields
    // =====================================================================

    public function testCostField(): void
    {
        $product = MockProductPost::create([], ['cost' => '$12.50']);
        $this->assertEquals('$12.50', $product->cost());
    }

    public function testSkuField(): void
    {
        $product = MockProductPost::create([], ['sku' => 'PKM-PE-001']);
        $this->assertEquals('PKM-PE-001', $product->sku());
    }
}
