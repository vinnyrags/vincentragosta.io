<?php
/**
 * Update card price/stock in WordPress directly from the Singles sheet.
 *
 * Stripe-free Sheet → WP sync (Stripe retired — Whatnot pivot). Reads a JSON
 * exported by Nous/scripts/shop/export-card-prices.mjs and applies it to
 * existing `card` posts. It does NOT create cards or touch images/taxonomies —
 * create-cards-from-sheet.php owns creation; only price, stock, sale fields
 * and (for red "do not sell" rows) post status change here.
 *
 * Join resolution, per row (col S is the sheet's generic join key):
 *   1. numeric joinKey  → WP post ID directly (cards created Stripe-free;
 *      stamped into col S by backfill-card-postids.mjs). Sanity-checked
 *      against card_name so a drifted ID can't update the wrong card.
 *   2. `prod_…` joinKey → `stripe_product_id` postmeta (legacy cards from the
 *      retired Stripe pipeline — the meta survives as an inert join handle).
 *   3. blank/failed     → card_name + card_number, with a normalized set-name
 *      tiebreak when multiple match. Ambiguous rows are skipped with a
 *      warning rather than guessed.
 *
 * Per card it will:
 *   - set `price` (col D) and `stock_quantity` (col F) when they differ,
 *   - clear `sale_price` / `sale_price_id` (sales are retired — col G is now
 *     the Whatnot Auction Price Override, not a sale price),
 *   - move red "do not sell" rows to `draft` so they drop off the catalog.
 * Un-redding a card does NOT auto-republish it (one-way, to avoid publishing a
 * half-ready draft); republish manually.
 *
 * Dry-run by default — prints what would change. Set APPLY=1 to write.
 *
 * Usage (local):  CARD_PRICES_JSON=/path/card-prices.json ddev wp eval-file scripts/update-card-prices.php
 *         apply:  CARD_PRICES_JSON=... APPLY=1 ddev wp eval-file scripts/update-card-prices.php
 * Remote: CARD_PRICES_JSON=/tmp/card-prices.json wp eval-file scripts/update-card-prices.php --path=/var/www/site/wp --allow-root
 */

if (!defined('ABSPATH')) {
    echo "This script must be run via WP-CLI: wp eval-file scripts/update-card-prices.php\n";
    exit(1);
}

$jsonPath = getenv('CARD_PRICES_JSON') ?: '/tmp/card-prices.json';
if (!file_exists($jsonPath)) {
    echo "Error: card prices JSON not found at {$jsonPath}\n";
    echo "Generate it first: node Nous/scripts/shop/export-card-prices.mjs > /tmp/card-prices.json\n";
    exit(1);
}

$entries = json_decode(file_get_contents($jsonPath), true);
if (!is_array($entries)) {
    echo "Error: could not parse JSON at {$jsonPath}\n";
    exit(1);
}

$apply = !empty(getenv('APPLY'));
echo ($apply ? 'APPLYING' : 'DRY RUN (no writes — set APPLY=1 to write)') . "\n";
echo 'Source: ' . $jsonPath . ' (' . count($entries) . " sheet rows)\n\n";

/**
 * Loose set-name comparison: the sheet and WP format set names differently
 * for older cards ("Brilliant Stars Trainer Gallery" vs "SWSH09: Brilliant
 * Stars Trainer Gallery"), so exact equality is too strict. Case-insensitive
 * containment in either direction.
 */
function cardSetMatches(string $a, string $b): bool
{
    $a = strtolower(trim($a));
    $b = strtolower(trim($b));
    if ($a === '' || $b === '') {
        return false;
    }
    return str_contains($a, $b) || str_contains($b, $a);
}

/**
 * Resolve a sheet row to a card post. Returns WP_Post or null.
 */
function resolveCardPost(array $e): ?WP_Post
{
    $key = trim((string) ($e['joinKey'] ?? ''));

    // 1. Numeric col S → direct post ID, with a card_name sanity check so an
    //    ID that drifted (e.g. local/staging DB divergence) can't update the
    //    wrong card — fall through to the name join instead.
    if ($key !== '' && ctype_digit($key)) {
        $post = get_post((int) $key);
        if ($post && $post->post_type === 'card') {
            $wpName = (string) get_field('card_name', $post->ID);
            if ($wpName === '' || strcasecmp($wpName, (string) $e['name']) === 0) {
                return $post;
            }
            echo "  [id mismatch] col S {$key} is \"{$wpName}\", sheet says \"{$e['name']}\" — falling back to name join\n";
        }
    }

    // 2. Legacy Stripe product ID → postmeta join.
    if ($key !== '' && str_starts_with($key, 'prod_')) {
        $q = new WP_Query([
            'post_type'      => 'card',
            'post_status'    => ['publish', 'draft', 'pending'],
            'posts_per_page' => 1,
            'no_found_rows'  => true,
            'meta_query'     => [['key' => 'stripe_product_id', 'value' => $key]],
        ]);
        if ($q->have_posts()) {
            return $q->posts[0];
        }
    }

    // 3. Fallback: card_name + card_number, set as tiebreak.
    if (($e['name'] ?? '') === '') {
        return null;
    }
    $q = new WP_Query([
        'post_type'      => 'card',
        'post_status'    => ['publish', 'draft', 'pending'],
        'posts_per_page' => 5,
        'no_found_rows'  => true,
        'meta_query'     => [
            'relation' => 'AND',
            ['key' => 'card_name', 'value' => $e['name']],
            ['key' => 'card_number', 'value' => (string) ($e['number'] ?? '')],
        ],
    ]);
    $hits = $q->posts;
    if (count($hits) > 1 && ($e['set'] ?? '') !== '') {
        $hits = array_values(array_filter(
            $hits,
            fn($p) => cardSetMatches((string) get_field('set_name', $p->ID), (string) $e['set'])
        ));
    }
    if (count($hits) === 1) {
        return $hits[0];
    }
    if (count($hits) > 1) {
        echo "  [ambiguous] {$e['name']} #{$e['number']} matches " . count($hits) . " WP cards — skipped\n";
    }
    return null;
}

$changed = 0;
$unchanged = 0;
$unmatched = 0;
$salesCleared = 0;
$drafted = 0;

foreach ($entries as $e) {
    $post = resolveCardPost($e);

    if (!$post) {
        $unmatched++;
        echo '  [no WP card] ' . trim((string) ($e['joinKey'] ?? '')) . ' — ' . ($e['name'] ?? '') . ' #' . ($e['number'] ?? '') . "\n";
        continue;
    }

    $postId = $post->ID;
    $changes = [];

    if (isset($e['price'])) {
        $cur = (string) get_field('price', $postId);
        if ($cur !== $e['price']) {
            $changes[] = "price {$cur} → {$e['price']}";
            if ($apply) {
                update_field('price', $e['price'], $postId);
            }
        }
    }

    if (array_key_exists('stock', $e) && $e['stock'] !== null) {
        $cur = (int) get_field('stock_quantity', $postId);
        if ($cur !== (int) $e['stock']) {
            $changes[] = "stock {$cur} → {$e['stock']}";
            if ($apply) {
                update_field('stock_quantity', (int) $e['stock'], $postId);
            }
        }
    }

    // Retire sale fields — col G is the Whatnot override now, not a sale price.
    $curSale = (string) get_field('sale_price', $postId);
    $curSaleId = (string) get_field('sale_price_id', $postId);
    if ($curSale !== '' || $curSaleId !== '') {
        $changes[] = 'clear sale (' . ($curSale ?: '—') . ' / ' . ($curSaleId ?: '—') . ')';
        $salesCleared++;
        if ($apply) {
            update_field('sale_price', '', $postId);
            update_field('sale_price_id', '', $postId);
        }
    }

    // Red "do not sell" rows → draft (one-way).
    if (!empty($e['doNotSell']) && $post->post_status !== 'draft') {
        $changes[] = "status {$post->post_status} → draft (do not sell)";
        $drafted++;
        if ($apply) {
            wp_update_post(['ID' => $postId, 'post_status' => 'draft']);
        }
    }

    if ($changes) {
        $changed++;
        echo "  #{$postId} {$post->post_title}\n      " . implode("\n      ", $changes) . "\n";
    } else {
        $unchanged++;
    }
}

echo "\n" . ($apply ? 'Applied' : 'Would change') . ": {$changed} card(s), {$unchanged} already current, {$unmatched} sheet row(s) with no WP card.\n";
echo "  sales cleared: {$salesCleared}, drafted as do-not-sell: {$drafted}\n";
if (!$apply) {
    echo "\nRe-run with APPLY=1 to write these changes.\n";
}
