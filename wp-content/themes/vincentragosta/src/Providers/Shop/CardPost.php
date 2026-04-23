<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop;

use IX\Models\Post;

/**
 * Card post model.
 *
 * Represents a single trading card listing in the card singles catalog.
 * Mirrors the commerce surface of ProductPost and adds card-specific
 * accessors (set, rarity, variant, etc.).
 */
class CardPost extends Post
{
    public const POST_TYPE = 'card';

    public function stripePriceId(): string
    {
        return (string) $this->getField('stripe_price_id');
    }

    public function stripeProductId(): string
    {
        return (string) $this->getField('stripe_product_id');
    }

    public function price(): string
    {
        return (string) $this->getField('price');
    }

    public function isOnSale(): bool
    {
        return (string) $this->getField('sale_price_id') !== '';
    }

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

    public function cost(): string
    {
        return (string) $this->getField('cost');
    }

    public function sku(): string
    {
        return (string) $this->getField('sku');
    }

    public function stockQuantity(): int
    {
        return (int) ($this->getField('stock_quantity') ?: 0);
    }

    public function isInStock(): bool
    {
        return $this->stockQuantity() > 0;
    }

    public function language(): string
    {
        return (string) $this->getField('language');
    }

    public function game(): string
    {
        return (string) $this->getField('game');
    }

    public function cardName(): string
    {
        $name = (string) $this->getField('card_name');
        return $name !== '' ? $name : $this->title();
    }

    public function cardNumber(): string
    {
        return (string) $this->getField('card_number');
    }

    public function setName(): string
    {
        return (string) $this->getField('set_name');
    }

    public function setCode(): string
    {
        return (string) $this->getField('set_code');
    }

    public function releaseYear(): int
    {
        return (int) ($this->getField('release_year') ?: 0);
    }

    public function rarity(): string
    {
        return (string) $this->getField('rarity');
    }

    public function variant(): string
    {
        return (string) $this->getField('variant');
    }

    public function artist(): string
    {
        return (string) $this->getField('artist');
    }

    public function condition(): string
    {
        $value = (string) $this->getField('condition');
        return $value !== '' ? $value : 'near-mint';
    }
}
