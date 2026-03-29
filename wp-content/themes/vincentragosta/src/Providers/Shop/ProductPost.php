<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop;

use IX\Models\Post;

/**
 * Product post model.
 *
 * Represents a product in the card shop.
 */
class ProductPost extends Post
{
    public const POST_TYPE = 'product';

    /**
     * Get the Stripe Price ID for this product.
     */
    public function stripePriceId(): string
    {
        return (string) $this->getField('stripe_price_id');
    }

    /**
     * Get the Stripe Product ID.
     */
    public function stripeProductId(): string
    {
        return (string) $this->getField('stripe_product_id');
    }

    /**
     * Get the display price (e.g., "$24.99").
     */
    public function price(): string
    {
        return (string) $this->getField('price');
    }

    /**
     * Whether the product is currently on sale.
     * Derived from the presence of a sale_price_id.
     */
    public function isOnSale(): bool
    {
        return (string) $this->getField('sale_price_id') !== '';
    }

    /**
     * Get the sale display price.
     */
    public function salePrice(): string
    {
        return (string) $this->getField('sale_price');
    }

    /**
     * Get the Stripe Price ID to use for checkout.
     * Returns sale price ID if on sale, otherwise the regular price ID.
     */
    public function checkoutPriceId(): string
    {
        if ($this->isOnSale()) {
            $salePriceId = (string) $this->getField('sale_price_id');
            if ($salePriceId) {
                return $salePriceId;
            }
        }

        return $this->stripePriceId();
    }

    /**
     * Get the per-unit cost.
     */
    public function cost(): string
    {
        return (string) $this->getField('cost');
    }

    /**
     * Get the SKU.
     */
    public function sku(): string
    {
        return (string) $this->getField('sku');
    }

    /**
     * Get the stock quantity.
     */
    public function stockQuantity(): int
    {
        return (int) ($this->getField('stock_quantity') ?: 0);
    }

    /**
     * Whether the product is in stock.
     */
    public function isInStock(): bool
    {
        return $this->stockQuantity() > 0;
    }

    /**
     * Get space-separated category slugs for client-side filtering.
     */
    public function cardTypeSlugs(): string
    {
        return $this->categorySlugs();
    }
}
