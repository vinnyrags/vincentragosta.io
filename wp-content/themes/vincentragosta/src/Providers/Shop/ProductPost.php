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
     * Get the display price (e.g., "$24.99").
     */
    public function price(): string
    {
        return (string) $this->getField('price');
    }

    /**
     * Get the card condition.
     */
    public function condition(): string
    {
        return (string) $this->getField('condition');
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
