<?php
/**
 * Update personal-collection card prices in WordPress directly from the
 * Collection sheet tab (Stripe-free). Sibling of update-card-prices.php — so the
 * /collection vault shows a value per card (price overlay + price sort).
 *
 * Joins each row to its WP card by WP Post ID (Collection col O) when present,
 * else by card_name + card_number among is_personal_collection cards. Resolved
 * post IDs are written to .col-postids.json so the Sheet's col O can be
 * backfilled (one-time) for clean future joins.
 *
 * Only updates `price` on existing collection cards — never creates/deletes.
 * Dry-run by default; APPLY=1 writes.
 *
 * Usage:  COLLECTION_PRICES_JSON=/tmp/collection-prices.json ddev wp eval-file scripts/update-collection-prices.php
 *  apply: COLLECTION_PRICES_JSON=... APPLY=1 ddev wp eval-file scripts/update-collection-prices.php
 */

if (!defined('ABSPATH')) {
    echo "Run via WP-CLI: wp eval-file scripts/update-collection-prices.php\n";
    exit(1);
}

$jsonPath = getenv('COLLECTION_PRICES_JSON') ?: '/tmp/collection-prices.json';
if (!file_exists($jsonPath)) {
    echo "Error: JSON not found at {$jsonPath}\n";
    exit(1);
}
$entries = json_decode(file_get_contents($jsonPath), true);
if (!is_array($entries)) {
    echo "Error: could not parse {$jsonPath}\n";
    exit(1);
}

$apply = !empty(getenv('APPLY'));
echo ($apply ? 'APPLYING' : 'DRY RUN (no writes — set APPLY=1 to write)') . "\n";
echo 'Source: ' . $jsonPath . ' (' . count($entries) . " priced collection rows)\n\n";

$changed = 0;
$unchanged = 0;
$unmatched = [];
$resolved = [];   // rowIndex => postId (for col-O backfill)

foreach ($entries as $e) {
    $id = 0;

    $wpId = (int) ($e['wpPostId'] ?? 0);
    if ($wpId && get_post_type($wpId) === 'card') {
        $id = $wpId;
    } else {
        // Match by name + number among personal-collection cards (set as tiebreak).
        $q = new WP_Query([
            'post_type'      => 'card',
            'post_status'    => ['publish', 'draft', 'pending'],
            'posts_per_page' => 5,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'meta_query'     => [
                'relation' => 'AND',
                ['key' => 'is_personal_collection', 'value' => '1'],
                ['key' => 'card_name', 'value' => $e['name']],
                ['key' => 'card_number', 'value' => $e['number']],
            ],
        ]);
        $hits = $q->posts;
        if (count($hits) > 1 && ($e['set'] ?? '') !== '') {
            $hits = array_values(array_filter($hits, fn($pid) => (string) get_field('set_name', $pid) === $e['set']));
        }
        if (count($hits) === 1) {
            $id = (int) $hits[0];
        }
    }

    if (!$id) {
        $unmatched[] = "{$e['name']} #{$e['number']}";
        continue;
    }

    $resolved[(string) $e['rowIndex']] = $id;
    $cur = (string) get_field('price', $id);
    if ($cur !== $e['price']) {
        $changed++;
        echo "  #{$id} {$e['name']} #{$e['number']}: " . ($cur ?: '(none)') . " → {$e['price']}\n";
        if ($apply) {
            update_field('price', $e['price'], $id);
        }
    } else {
        $unchanged++;
    }
}

file_put_contents(__DIR__ . '/.col-postids.json', json_encode($resolved));

echo "\n" . ($apply ? 'Applied' : 'Would change') . ": {$changed} price(s), {$unchanged} already current, " . count($unmatched) . " unmatched.\n";
if ($unmatched) {
    foreach ($unmatched as $u) {
        echo "  - no WP collection card: {$u}\n";
    }
}
echo 'Resolved ' . count($resolved) . " post IDs → .col-postids.json (for col-O backfill).\n";
if (!$apply) {
    echo "\nRe-run with APPLY=1 to write.\n";
}
