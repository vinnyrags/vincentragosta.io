<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop\Hooks;

use Mythus\Contracts\Hook;
use WPGraphQL\Data\DataSource;

/**
 * Registers WPGraphQL extensions for the Card CPT.
 *
 * The headless storefront's homepage CatalogTeaser wants to feature the
 * top N priced in-stock cards across the whole catalog (not just a
 * recency window). Doing the price sort client-side requires fetching
 * the entire catalog every time — expensive on the cold-render path.
 *
 * The blocker for a server-side sort is that ACF stores the `price`
 * field as a display string ("$650.00"), so a vanilla `meta_query`
 * with `orderby: META_NUM` would CAST a non-numeric value and return 0
 * for every card. We work around this by registering a custom root
 * field `topPricedCards(limit: Int!)` that runs raw SQL with
 * `CAST(REPLACE(REPLACE(meta_value, '$', ''), ',', '') AS DECIMAL)` —
 * stripping the dollar sign and any thousands comma before the cast,
 * so MySQL sees a real number to sort on.
 *
 * The resolver returns posts hydrated through DataSource::resolve_post_object,
 * which lets WPGraphQL wrap each one as the auto-generated `Card` type
 * — so the existing CARD_FIELDS_FRAGMENT on the frontend works unchanged.
 */
class CardGraphQL implements Hook
{
    public function register(): void
    {
        add_action('graphql_register_types', [$this, 'registerTypes']);
    }

    public function registerTypes(): void
    {
        register_graphql_field('RootQuery', 'topPricedCards', [
            'type'        => ['list_of' => 'Card'],
            'description' => 'Top N in-stock cards sorted by price descending across the entire catalog. Excludes personal-collection cards (those live on /collection and are not for sale through the standard catalog). Used by the homepage CatalogTeaser and the thank-you page teaser to feature the most expensive available singles without paying the cost of fetching the entire catalog.',
            'args'        => [
                'limit' => [
                    'type'        => ['non_null' => 'Int'],
                    'description' => 'Maximum number of cards to return. Capped to 50 server-side to avoid abuse.',
                ],
            ],
            'resolve'     => function ($source, array $args, $context) {
                global $wpdb;
                $limit = max(1, min(50, (int) $args['limit']));

                // The "price" ACF field is stored as a display string with
                // a "$" prefix and (sometimes) thousands commas. Strip both
                // before CASTing to DECIMAL so MySQL can sort numerically.
                // INNER JOIN on price + stock ensures we only consider cards
                // with both fields set; the LEFT JOIN on is_personal_collection
                // is permissive (NULL/empty/'0' all pass as "for sale").
                $sql = "
                    SELECT p.ID
                    FROM {$wpdb->posts} p
                    INNER JOIN {$wpdb->postmeta} mp ON mp.post_id = p.ID AND mp.meta_key = 'price'
                    INNER JOIN {$wpdb->postmeta} ms ON ms.post_id = p.ID AND ms.meta_key = 'stock_quantity'
                    LEFT JOIN {$wpdb->postmeta} mpc ON mpc.post_id = p.ID AND mpc.meta_key = 'is_personal_collection'
                    WHERE p.post_type = 'card'
                      AND p.post_status = 'publish'
                      AND CAST(ms.meta_value AS UNSIGNED) > 0
                      AND (mpc.meta_value IS NULL OR mpc.meta_value = '0' OR mpc.meta_value = '')
                    ORDER BY CAST(REPLACE(REPLACE(mp.meta_value, '$', ''), ',', '') AS DECIMAL(10,2)) DESC
                    LIMIT %d
                ";

                $ids = $wpdb->get_col($wpdb->prepare($sql, $limit));
                if (!$ids) {
                    return [];
                }

                // Hydrate as WPGraphQL Post models so the Card type
                // resolves correctly with the existing CARD_FIELDS_FRAGMENT.
                $posts = array_map(
                    static fn($id) => DataSource::resolve_post_object((int) $id, $context),
                    $ids
                );

                return array_filter($posts);
            },
        ]);
    }
}
