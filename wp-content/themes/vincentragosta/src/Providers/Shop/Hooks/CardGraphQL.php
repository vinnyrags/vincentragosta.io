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

    /**
     * SQL fragment for the WHERE/JOIN clauses that drive both the
     * paginated `cardsByScope` field and the `cardCount` aggregate.
     * Centralized so the count and the fetch can never drift out of
     * sync — both filter on identical criteria.
     *
     * @return array{joins:string,where:string} Pre-built fragments to
     *                                          inject into a SELECT.
     */
    private function scopeFragments(string $scope): array
    {
        global $wpdb;

        // Catalog: in-stock + not in personal collection (the public
        // /cards browse view). Collection: items flagged as personal
        // collection (the /collection vault — not for sale, no stock
        // requirement since the price + Add to Cart row is suppressed).
        if ($scope === 'COLLECTION') {
            $joins = "
                INNER JOIN {$wpdb->postmeta} mpc
                    ON mpc.post_id = p.ID AND mpc.meta_key = 'is_personal_collection'
            ";
            $where = "AND mpc.meta_value = '1'";
        } else {
            // CATALOG (default)
            $joins = "
                INNER JOIN {$wpdb->postmeta} ms
                    ON ms.post_id = p.ID AND ms.meta_key = 'stock_quantity'
                LEFT JOIN {$wpdb->postmeta} mpc
                    ON mpc.post_id = p.ID AND mpc.meta_key = 'is_personal_collection'
            ";
            $where = "AND CAST(ms.meta_value AS UNSIGNED) > 0
                      AND (mpc.meta_value IS NULL OR mpc.meta_value = '0' OR mpc.meta_value = '')";
        }

        return ['joins' => $joins, 'where' => $where];
    }

    /**
     * SQL fragments that drive ORDER BY for `cardsByScope`. Kept
     * separate from filter fragments so cardCount (which doesn't
     * order) avoids the extra LEFT JOINs.
     *
     * Ordering must match what the headless storefront's CardToolbar
     * does client-side after data lands — otherwise the SSR'd first
     * batch of 20 cards renders in one order and then visibly
     * reshuffles when prefetched batches arrive and the client
     * re-sorts.
     *
     * For CATALOG (default sort = "set-asc" on /cards):
     *   1. release_date ASC (oldest first), missing/empty values last
     *   2. set_name ASC (alphabetical tiebreaker)
     *   3. card_number numerically ascending. Numbers like "001/100"
     *      get the leading int via SUBSTRING_INDEX('/', 1) — close
     *      enough; promo codes like "SWSH021" cast to 0 and sort to
     *      the top of their set, which is acceptable.
     *
     * For COLLECTION (default sort = "title-asc" on /collection):
     *   - Just the post_title alphabetically. ID as tiebreaker for
     *     stable pagination across batches.
     *
     * @return array{joins:string,orderBy:string}
     */
    private function scopeOrderFragments(string $scope): array
    {
        global $wpdb;

        if ($scope === 'COLLECTION') {
            return [
                'joins'   => '',
                'orderBy' => 'ORDER BY p.post_title ASC, p.ID ASC',
            ];
        }

        // CATALOG: chain matches CardToolbar.compareBySet(direction='asc').
        $joins = "
            LEFT JOIN {$wpdb->postmeta} mr
                ON mr.post_id = p.ID AND mr.meta_key = 'release_date'
            LEFT JOIN {$wpdb->postmeta} msn
                ON msn.post_id = p.ID AND msn.meta_key = 'set_name'
            LEFT JOIN {$wpdb->postmeta} mcn
                ON mcn.post_id = p.ID AND mcn.meta_key = 'card_number'
        ";
        $orderBy = "
            ORDER BY
                CASE WHEN mr.meta_value IS NULL OR mr.meta_value = '' THEN 1 ELSE 0 END,
                mr.meta_value ASC,
                msn.meta_value ASC,
                CAST(SUBSTRING_INDEX(COALESCE(mcn.meta_value, ''), '/', 1) AS UNSIGNED) ASC,
                p.ID ASC
        ";

        return ['joins' => $joins, 'orderBy' => $orderBy];
    }

    public function registerTypes(): void
    {
        // Scope enum used by both `cardCount` and `cardsByScope` to keep
        // the catalog/collection split consistent across the two queries.
        register_graphql_enum_type('CardScope', [
            'description' => 'Which slice of the cards CPT to query: CATALOG = in-stock + non-personal-collection (the /cards browse view); COLLECTION = personal-collection items (the /collection vault).',
            'values' => [
                'CATALOG'    => ['value' => 'CATALOG'],
                'COLLECTION' => ['value' => 'COLLECTION'],
            ],
        ]);

        register_graphql_field('RootQuery', 'cardCount', [
            'type'        => ['non_null' => 'Int'],
            'description' => 'Total number of cards matching the given scope. Used by the headless storefront to render the catalog count badge before the full card list has finished prefetching.',
            'args'        => [
                'scope' => [
                    'type'        => ['non_null' => 'CardScope'],
                    'description' => 'Which slice of the catalog to count.',
                ],
            ],
            'resolve'     => function ($source, array $args) {
                global $wpdb;
                $scope = $args['scope'] === 'COLLECTION' ? 'COLLECTION' : 'CATALOG';
                $frag  = $this->scopeFragments($scope);

                $sql = "
                    SELECT COUNT(*)
                    FROM {$wpdb->posts} p
                    {$frag['joins']}
                    WHERE p.post_type = 'card'
                      AND p.post_status = 'publish'
                      {$frag['where']}
                ";

                return (int) $wpdb->get_var($sql);
            },
        ]);

        register_graphql_field('RootQuery', 'cardsByScope', [
            'type'        => ['list_of' => 'Card'],
            'description' => 'Paginated list of cards matching the given scope. Returns up to `limit` cards starting at `offset`, ordered by post date descending (matches the existing /cards and /collection sort). Used by the headless storefront to background-prefetch the catalog in batches after the initial server render.',
            'args'        => [
                'scope' => [
                    'type'        => ['non_null' => 'CardScope'],
                    'description' => 'Which slice of the catalog to return.',
                ],
                'limit' => [
                    'type'        => ['non_null' => 'Int'],
                    'description' => 'Maximum number of cards to return per call. Capped to 200 server-side.',
                ],
                'offset' => [
                    'type'        => ['non_null' => 'Int'],
                    'description' => 'Number of cards to skip before starting the slice. Use 0 for the first batch, then loadedCount for each subsequent batch.',
                ],
            ],
            'resolve'     => function ($source, array $args, $context) {
                global $wpdb;
                $scope  = $args['scope'] === 'COLLECTION' ? 'COLLECTION' : 'CATALOG';
                $limit  = max(1, min(200, (int) $args['limit']));
                $offset = max(0, (int) $args['offset']);
                $frag   = $this->scopeFragments($scope);
                $order  = $this->scopeOrderFragments($scope);

                $sql = "
                    SELECT p.ID
                    FROM {$wpdb->posts} p
                    {$frag['joins']}
                    {$order['joins']}
                    WHERE p.post_type = 'card'
                      AND p.post_status = 'publish'
                      {$frag['where']}
                    {$order['orderBy']}
                    LIMIT %d OFFSET %d
                ";

                $ids = $wpdb->get_col($wpdb->prepare($sql, $limit, $offset));
                if (!$ids) {
                    return [];
                }

                $posts = array_map(
                    static fn($id) => DataSource::resolve_post_object((int) $id, $context),
                    $ids
                );

                return array_filter($posts);
            },
        ]);

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
