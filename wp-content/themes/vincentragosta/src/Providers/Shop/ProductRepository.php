<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop;

use IX\Repositories\Repository;

/**
 * Product repository.
 *
 * Provides query methods for the product post type.
 */
class ProductRepository extends Repository
{
    protected string $model = ProductPost::class;

    /**
     * Get all in-stock products.
     *
     * @return ProductPost[]
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
     * Get products by card type (category).
     *
     * @return ProductPost[]
     */
    public function byCardType(string $slug, int $limit = -1): array
    {
        return $this->whereTerm('category', $slug, $limit);
    }

    /**
     * Get all products ordered by price (descending).
     *
     * @return ProductPost[]
     */
    public function allByPrice(int $limit = -1): array
    {
        return $this->query([
            'posts_per_page' => $limit,
            'meta_key'       => 'price',
            'orderby'        => 'meta_value_num',
            'order'          => 'DESC',
        ]);
    }

    /**
     * Find a product by its Stripe Price ID.
     * Checks both the regular price and sale price fields.
     */
    public function findByPriceId(string $priceId): ?ProductPost
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
