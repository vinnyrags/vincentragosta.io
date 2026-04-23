<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop;

use IX\Repositories\Repository;

/**
 * Card repository.
 *
 * Provides query methods for the card post type (singles catalog).
 */
class CardRepository extends Repository
{
    protected string $model = CardPost::class;

    /**
     * Get all in-stock cards.
     *
     * @return CardPost[]
     */
    public function inStock(int $limit = -1): array
    {
        return $this->query([
            'posts_per_page' => $limit,
            'meta_query'     => [
                [
                    'key'     => 'stock_quantity',
                    'value'   => 0,
                    'compare' => '>',
                    'type'    => 'NUMERIC',
                ],
            ],
        ]);
    }

    /**
     * Get cards by game taxonomy slug.
     *
     * @return CardPost[]
     */
    public function byGame(string $slug, int $limit = -1): array
    {
        return $this->whereTerm('card_game', $slug, $limit);
    }

    /**
     * Get cards by set taxonomy slug.
     *
     * @return CardPost[]
     */
    public function bySet(string $slug, int $limit = -1): array
    {
        return $this->whereTerm('card_set', $slug, $limit);
    }

    /**
     * Get cards by rarity ACF meta.
     *
     * @return CardPost[]
     */
    public function byRarity(string $rarity, int $limit = -1): array
    {
        return $this->query([
            'posts_per_page' => $limit,
            'meta_query'     => [
                [
                    'key'   => 'rarity',
                    'value' => $rarity,
                ],
            ],
        ]);
    }

    /**
     * Find a card by its Stripe Price ID.
     * Checks both the regular price and sale price fields.
     */
    public function findByPriceId(string $priceId): ?CardPost
    {
        $results = $this->query([
            'posts_per_page' => 1,
            'meta_query'     => [
                'relation' => 'OR',
                [
                    'key'   => 'stripe_price_id',
                    'value' => $priceId,
                ],
                [
                    'key'   => 'sale_price_id',
                    'value' => $priceId,
                ],
            ],
        ]);

        return $results[0] ?? null;
    }
}
