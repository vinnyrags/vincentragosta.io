<?php
/**
 * Export sellable `card` posts (singles catalog) to a Whatnot bulk-import
 * CSV. Uses Buy it Now + Offerable=TRUE so buyers can purchase outright
 * or submit offers. Auctions are NOT used here — auction-format on a
 * standing catalog with no live bidders torches inventory at $1 floors.
 *
 * Run via: wp eval-file scripts/export-whatnot-cards-csv.php > /tmp/whatnot-cards.csv
 *
 * Filters:
 *   - is_personal_collection = true        → skipped (not for sale)
 *   - stock_quantity < 1                   → skipped
 *   - no price set                         → skipped
 *
 * All cards: Category="Trading Card Games", Type="Buy it Now",
 * Offerable="TRUE", Hazmat="Not Hazmat". Sub Category, Condition, and
 * Shipping Profile are derived per-card.
 */

$columns = [
    'Category', 'Sub Category', 'Title', 'Description', 'Quantity', 'Type',
    'Price', 'Shipping Profile', 'Offerable', 'Hazmat', 'Condition',
    'Cost Per Item', 'SKU',
    'Image URL 1', 'Image URL 2', 'Image URL 3', 'Image URL 4',
    'Image URL 5', 'Image URL 6', 'Image URL 7', 'Image URL 8',
];

function map_subcategory(string $game): string {
    $g = strtolower(trim($game));
    return match (true) {
        str_contains($g, 'pokemon') || str_contains($g, 'pokémon') => 'Pokémon Cards',
        str_contains($g, 'yu-gi-oh') || str_contains($g, 'yugioh') => 'Yu-Gi-Oh! Cards',
        str_contains($g, 'magic') || $g === 'mtg'                  => 'Magic: The Gathering',
        str_contains($g, 'one piece')                               => 'One Piece Cards',
        str_contains($g, 'lorcana')                                 => 'Lorcana',
        str_contains($g, 'dragon ball')                             => 'Dragon Ball Cards',
        str_contains($g, 'digimon')                                 => 'Digimon Cards',
        str_contains($g, 'flesh') && str_contains($g, 'blood')      => 'Flesh & Blood',
        str_contains($g, 'weiss') || str_contains($g, 'weiß')       => 'Weiß Schwarz',
        str_contains($g, 'union arena')                             => 'Union Arena',
        str_contains($g, 'naruto')                                  => 'Naruto Cards',
        default                                                     => 'Pokémon Cards',
    };
}

function map_condition(string $acf_value): string {
    return match ($acf_value) {
        'near-mint'         => 'Near Mint',
        'lightly-played'    => 'Light Played',
        'moderately-played' => 'Moderately Played',
        'heavily-played'    => 'Heavily Played',
        'damaged'           => 'Damaged',
        default             => 'Near Mint',
    };
}

function format_rarity(string $r): string {
    if ($r === '') return '';
    return ucwords(str_replace('-', ' ', $r));
}

function format_variant(string $v): string {
    if ($v === '' || $v === 'regular') return '';
    return ucwords(str_replace('-', ' ', $v));
}

function clean_price($val): string {
    if ($val === null || $val === '') return '';
    $cleaned = preg_replace('/[^0-9.]/', '', (string) $val);
    return $cleaned === '' ? '' : $cleaned;
}

function whatnot_price_int($val): string {
    $cleaned = clean_price($val);
    if ($cleaned === '') return '';
    return (string) (int) ceil((float) $cleaned);
}

function build_card_description(int $id): string {
    $game        = get_field('game', $id) ?: 'Pokémon';
    $game_label  = ucfirst(strtolower($game)) === 'Pokemon' ? 'Pokémon' : ucwords(strtolower($game));
    $card_name   = get_field('card_name', $id) ?: '';
    $card_number = get_field('card_number', $id) ?: '';
    $set_name    = get_field('set_name', $id) ?: '';
    $set_code    = get_field('set_code', $id) ?: '';
    $rarity      = format_rarity(get_field('rarity', $id) ?: '');
    $variant     = format_variant(get_field('variant', $id) ?: '');
    $language    = get_field('language', $id) ?: 'English';
    $condition   = map_condition(get_field('condition', $id) ?: 'near-mint');
    $artist      = get_field('artist', $id) ?: '';

    $lines = [];

    // Lead line — the most discoverable summary
    $lead_parts = ["{$game_label} TCG single card"];
    if ($card_name) $lead_parts[] = "{$card_name}";
    if ($card_number) $lead_parts[] = "#{$card_number}";
    $lead = implode(' ', $lead_parts);
    if ($set_name) {
        $lead .= " from the {$set_name} set";
        if ($set_code) $lead .= " ({$set_code})";
    }
    $lead .= ". Language: {$language}.";
    $lines[] = $lead;

    // Detail line — rarity, variant, artist
    $details = [];
    if ($rarity) $details[] = "Rarity: {$rarity}";
    if ($variant) $details[] = "Variant: {$variant}";
    if ($artist) $details[] = "Illustrator: {$artist}";
    if ($details) $lines[] = implode('. ', $details) . '.';

    // Condition line
    $lines[] = "Condition: {$condition}. See images for full visual assessment — buyer is welcome to message with any condition questions before purchase.";

    // Shipping line
    $lines[] = "Ships in a penny sleeve + hard plastic toploader inside a bubble mailer with tracking. Smoke-free environment, packed within 1-2 business days of payment.";

    return implode(' ', $lines);
}

function card_image_urls(int $id, int $max = 8): array {
    $urls = [];
    $featured = get_the_post_thumbnail_url($id, 'full');
    if ($featured) $urls[] = $featured;
    $back = get_field('back_image', $id);
    if (is_array($back) && !empty($back['url'])) {
        $urls[] = $back['url'];
    } elseif (is_numeric($back)) {
        $back_url = wp_get_attachment_url((int) $back);
        if ($back_url) $urls[] = $back_url;
    }
    return array_slice($urls, 0, $max);
}

$posts = get_posts([
    'post_type'      => 'card',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'orderby'        => 'title',
    'order'          => 'ASC',
]);

$out = fopen('php://stdout', 'w');
fputcsv($out, $columns);

$skipped_personal = 0;
$skipped_oos = 0;
$skipped_no_price = 0;
$exported = 0;

foreach ($posts as $p) {
    if (get_field('is_personal_collection', $p->ID)) { $skipped_personal++; continue; }

    $stock = (int) get_field('stock_quantity', $p->ID);
    if ($stock < 1) { $skipped_oos++; continue; }

    $price_raw = get_field('sale_price', $p->ID) ?: get_field('price', $p->ID);
    $price = whatnot_price_int($price_raw);
    if ($price === '') { $skipped_no_price++; continue; }

    $game = get_field('game', $p->ID) ?: 'pokemon';
    $images = card_image_urls($p->ID, 7);

    $row = [
        'Trading Card Games',
        map_subcategory($game),
        $p->post_title,
        build_card_description($p->ID),
        $stock,
        'Buy it Now',
        $price,
        '0-1 oz',
        'TRUE',
        'Not Hazmat',
        map_condition(get_field('condition', $p->ID) ?: 'near-mint'),
        clean_price(get_field('cost', $p->ID)),
        get_field('sku', $p->ID) ?: '',
        $images[0] ?? '',
        $images[1] ?? '',
        $images[2] ?? '',
        $images[3] ?? '',
        $images[4] ?? '',
        $images[5] ?? '',
        $images[6] ?? '',
        '',
    ];

    fputcsv($out, $row);
    $exported++;
}

fclose($out);

fwrite(STDERR, "Exported: {$exported}\n");
fwrite(STDERR, "Skipped (personal collection): {$skipped_personal}\n");
fwrite(STDERR, "Skipped (out of stock): {$skipped_oos}\n");
fwrite(STDERR, "Skipped (no price): {$skipped_no_price}\n");
