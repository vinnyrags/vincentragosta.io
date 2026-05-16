<?php
/**
 * Export published `product` posts to a Whatnot bulk-import CSV.
 *
 * Run via: wp eval-file scripts/export-whatnot-csv.php > /tmp/whatnot.csv
 *
 * Whatnot CSV columns (locked by their template):
 *   Category, Sub Category, Title, Description, Quantity, Type, Price,
 *   Shipping Profile, Offerable, Hazmat, Condition, Cost Per Item, SKU,
 *   Image URL 1..8
 *
 * All sealed product gets: Category="Trading Card Games", Type="Buy it Now",
 * Offerable="FALSE", Hazmat="Not Hazmat", Condition="New".
 *
 * Sub Category and Shipping Profile are inferred from the product title;
 * fallbacks are sensible defaults (Pokémon Cards / 8-11 oz). Operator
 * should sanity-check the resulting CSV in Google Sheets before upload.
 */

$columns = [
    'Category', 'Sub Category', 'Title', 'Description', 'Quantity', 'Type',
    'Price', 'Shipping Profile', 'Offerable', 'Hazmat', 'Condition',
    'Cost Per Item', 'SKU',
    'Image URL 1', 'Image URL 2', 'Image URL 3', 'Image URL 4',
    'Image URL 5', 'Image URL 6', 'Image URL 7', 'Image URL 8',
];

function infer_subcategory(string $title): string {
    $t = strtolower($title);
    if (str_contains($t, 'pokemon') || str_contains($t, 'pokémon')) return 'Pokémon Cards';
    if (str_contains($t, 'yu-gi-oh') || str_contains($t, 'yugioh')) return 'Yu-Gi-Oh! Cards';
    if (str_contains($t, 'magic') || str_contains($t, 'mtg')) return 'Magic: The Gathering';
    if (str_contains($t, 'lorcana')) return 'Lorcana';
    if (str_contains($t, 'one piece')) return 'One Piece Cards';
    if (str_contains($t, 'dragon ball')) return 'Dragon Ball Cards';
    if (str_contains($t, 'digimon')) return 'Digimon Cards';
    if (str_contains($t, 'flesh') && str_contains($t, 'blood')) return 'Flesh & Blood';
    if (str_contains($t, 'weiss') || str_contains($t, 'weiß')) return 'Weiß Schwarz';
    if (str_contains($t, 'union arena')) return 'Union Arena';
    if (str_contains($t, 'naruto')) return 'Naruto Cards';
    if (str_contains($t, 'metazoo')) return 'MetaZoo';
    return 'Pokémon Cards';
}

function infer_shipping(string $title): string {
    $t = strtolower($title);
    if (str_contains($t, 'booster box')) return '1-2 lbs';
    if (str_contains($t, 'elite trainer') || str_contains($t, 'etb')) return '1 lb';
    if (str_contains($t, 'ultra premium')) return '1-2 lbs';
    if (str_contains($t, 'premium collection') || str_contains($t, 'premium')) return '1-2 lbs';
    if (str_contains($t, 'booster bundle') || str_contains($t, 'bundle')) return '8-11 oz';
    if (str_contains($t, 'collection')) return '1 lb';
    if (str_contains($t, 'tin')) return '8-11 oz';
    if (str_contains($t, 'booster pack') || str_contains($t, 'pack')) return '1-3 oz';
    if (str_contains($t, 'box')) return '1 lb';
    return '8-11 oz';
}

function clean_price($val): string {
    if ($val === null || $val === '') return '';
    $cleaned = preg_replace('/[^0-9.]/', '', (string) $val);
    return $cleaned === '' ? '' : $cleaned;
}

/**
 * Whatnot requires Price to be a positive integer (whole dollars only,
 * no decimals). Round UP to preserve seller revenue ($10.99 → 11,
 * $124.99 → 125, $499.99 → 500).
 */
function whatnot_price_int($val): string {
    $cleaned = clean_price($val);
    if ($cleaned === '') return '';
    return (string) (int) ceil((float) $cleaned);
}

/**
 * Per-product enriched descriptions, keyed by case-insensitive title
 * substring. First match wins. Falls through to WP post_content if
 * present, otherwise to a generic sealed-product fallback. Substrings
 * are intentionally specific so similar products don't collide
 * (e.g., "Pokemon Center Elite Trainer Box" beats plain "Elite Trainer
 * Box").
 */
function enriched_descriptions(): array {
    $shipping_close = " Ships factory sealed in a padded, tracked mailer from a smoke-free environment.";
    return [
        'one piece illustration box vol. 6' =>
            "One Piece Card Game Illustration Box Volume 6 — a sealed Bandai illustration collector's box from the Japanese One Piece TCG line. Includes booster packs and exclusive illustration content for One Piece collectors and players." . $shipping_close,

        'astral radiance booster pack' =>
            "Pokemon Sword & Shield: Astral Radiance booster pack (English, 2022). 10 cards per pack with a chance at chase cards including Origin Forme Dialga and Palkia VSTAR alts, Hisuian Pokemon, Trainer Gallery cards, and the Radiant Pokemon subset. Sealed individual pack." . $shipping_close,

        'brilliant stars pokemon center elite trainer' =>
            "Pokemon Sword & Shield: Brilliant Stars Pokemon Center Elite Trainer Box (English, 2022) — the Pokemon Center exclusive variant. Includes 8 Brilliant Stars booster packs, 65 card sleeves, 45 energy cards, dice, condition markers, and a Charizard VSTAR promo. The Pokemon Center variant is significantly rarer than the standard ETB and sought after by collectors." . $shipping_close,

        'celebrations elite trainer box' =>
            "Pokemon Celebrations Elite Trainer Box (English, 2021) — released for the Pokemon TCG 25th anniversary. Includes 10 booster packs (4 Celebrations packs + 6 packs from earlier expansions), 65 sleeves, 45 energy cards, dice, and accessory items. Celebrations contains a coveted reprint subset including Base Set Charizard and Mew." . $shipping_close,

        'celebrations lunchbox' =>
            "Pokemon Celebrations Lunchbox (English, 2021) — special anniversary tin in lunchbox form celebrating 25 years of the Pokemon TCG. Includes Celebrations booster packs and a foil promo card commemorating the milestone." . $shipping_close,

        'celebrations pokemon center elite trainer' =>
            "Pokemon Celebrations Pokemon Center Elite Trainer Box (English, 2021) — the Pokemon Center exclusive 25th anniversary ETB variant. Significantly rarer than the standard Celebrations ETB and a holy-grail item for serious 25th anniversary collectors." . $shipping_close,

        'celebrations premium figure collection pikachu' =>
            "Pokemon Celebrations Premium Figure Collection — Pikachu (English, 2021). Anniversary collection box featuring an exclusive Pikachu figure, Celebrations booster packs, and a foil promo card. Limited release tied to the 25th anniversary set." . $shipping_close,

        'celebrations prime collection' =>
            "Pokemon Celebrations Prime Collection (English, 2021). Premium 25th anniversary collection including multiple Celebrations booster packs and exclusive promotional cards. Released as a top-tier celebration product alongside the main Celebrations set." . $shipping_close,

        'pokemon dark fantasma' =>
            "Pokemon Dark Fantasma — Japanese Sword & Shield era booster box (Bandai/Pokemon Japan). Sealed Japanese sealed product with chase cards exclusive to the Japanese print run. Highly collectible for Japanese-print Pokemon fans and players seeking pulls not available in English sets." . $shipping_close,

        'dark sylveon v celebrations collection' =>
            "Pokemon Celebrations Dark Sylveon V Box (English, 2021) — collection box featuring an exclusive jumbo Dark Sylveon V card, Celebrations booster packs, and a foil promo. Part of the limited 25th anniversary product line." . $shipping_close,

        "lance's charizard v celebrations collection" =>
            "Pokemon Celebrations Lance's Charizard V Box (English, 2021) — collection box featuring an exclusive jumbo Lance's Charizard V card, Celebrations booster packs, and a foil promo. Charizard-themed collection box from the limited 25th anniversary line, especially desirable for Charizard collectors." . $shipping_close,

        'lost origin booster pack' =>
            "Pokemon Sword & Shield: Lost Origin booster pack (English, 2022). 10 cards per pack with a chance at chase cards including Giratina VSTAR, Aerodactyl VSTAR, Trainer Gallery alt-arts, and the Lost Zone mechanic. Sealed individual pack from one of the most beloved Sword & Shield era expansions." . $shipping_close,

        'prismatic evolutions poster' =>
            "Pokemon Scarlet & Violet: Prismatic Evolutions Poster Collection (English) — collection box from the recent Prismatic Evolutions set, including Prismatic Evolutions booster packs, an exclusive poster, and promotional cards. Prismatic Evolutions has been one of the most in-demand Scarlet & Violet sets due to its Eevee-focused chase cards." . $shipping_close,

        'pokemon snow hazard' =>
            "Pokemon Snow Hazard — Japanese Scarlet & Violet era booster box (Bandai/Pokemon Japan, 2023). Sealed Japanese booster box. Contains Japanese-exclusive print runs of chase cards from this expansion. Popular with collectors hunting Japanese alt-art and full-art versions." . $shipping_close,

        'evolving skies elite trainer' =>
            "Pokemon Sword & Shield: Evolving Skies Elite Trainer Box (English, 2021). One of the most sought-after Sword & Shield era ETBs due to the Eeveelution VMAX chase cards (Umbreon VMAX alt-art, Sylveon VMAX, Espeon VMAX, etc.). Includes 8 Evolving Skies booster packs, 65 sleeves, 45 energy cards, dice, and accessory items. Highly collectible." . $shipping_close,

        'fusion strike pokemon center elite trainer' =>
            "Pokemon Sword & Shield: Fusion Strike Pokemon Center Elite Trainer Box (English, 2021) — the Pokemon Center exclusive variant of the Fusion Strike ETB, significantly rarer than the standard release. Includes 8 Fusion Strike booster packs, an exclusive Mew V promo, sleeves, energy cards, and accessory items." . $shipping_close,

        'ultra premium collection charizard' =>
            "Pokemon Sword & Shield: Ultra Premium Collection Charizard (English). Premium Charizard-themed collection including multiple booster packs, jumbo Charizard cards, exclusive promos, sleeves, dice, and a deck box. One of the highest-tier Charizard products released by The Pokemon Company." . $shipping_close,

        'pokemon triple beat' =>
            "Pokemon Triple Beat — Japanese Scarlet & Violet era booster box (Bandai/Pokemon Japan, 2023). Sealed Japanese booster box with chase cards exclusive to the Japanese print run. Triple Beat is known for its Iono SAR (Special Art Rare) and Pikachu chase cards." . $shipping_close,

        'is it wrong to try to pick up girls in a dungeon' =>
            "Weiß Schwarz: Is It Wrong to Try to Pick Up Girls in a Dungeon? (DanMachi) — sealed Japanese Weiß Schwarz TCG booster box from Bushiroad. Features cards from the popular DanMachi anime series. Suitable for Weiß Schwarz players building DanMachi decks and anime collectors." . $shipping_close,

        "jojo's bizarre adventure golden wind" =>
            "Weiß Schwarz: JoJo's Bizarre Adventure — Golden Wind (Part 5) sealed Japanese booster box from Bushiroad. Features characters from the JoJo Part 5 anime including Giorno, Bruno, Mista, and the Passione team. Highly desirable for JoJo fans and Weiß Schwarz collectors." . $shipping_close,

        'ms kobayashi' =>
            "Weiß Schwarz: Miss Kobayashi's Dragon Maid sealed Japanese booster box from Bushiroad. Features cards from the popular slice-of-life anime including Tohru, Kanna, and Lucoa." . $shipping_close,

        'sword art online alicization vol. 2' =>
            "Weiß Schwarz: Sword Art Online Alicization Vol. 2 sealed Japanese booster box from Bushiroad. Continues the Alicization arc cardpool with new characters and scenes from the SAO Alicization War of Underworld arc." . $shipping_close,

        'weiss schwarz tokyo revengers' =>
            "Weiß Schwarz: Tokyo Revengers sealed Japanese booster box from Bushiroad. Features characters from the hit Tokyo Revengers anime including Mikey, Draken, Takemichi, and the Toman gang. Great for fans of the series and Weiß Schwarz players building delinquent-themed decks." . $shipping_close,
    ];
}

function build_description(WP_Post $p): string {
    $title_lower = strtolower($p->post_title);
    foreach (enriched_descriptions() as $needle => $description) {
        if (str_contains($title_lower, $needle)) {
            return $description;
        }
    }
    $desc = trim(wp_strip_all_tags($p->post_content));
    if ($desc === '') {
        $desc = "Sealed product, brand new, factory sealed. Ships from a smoke-free environment in a padded mailer.";
    }
    return $desc;
}

function gallery_urls(int $post_id, int $max = 7): array {
    $urls = [];
    $gallery = get_field('gallery_images', $post_id);
    if (is_array($gallery)) {
        foreach ($gallery as $img) {
            $url = is_array($img) ? ($img['url'] ?? null)
                 : (is_object($img) ? null
                 : (is_numeric($img) ? wp_get_attachment_url((int) $img) : null));
            if ($url) $urls[] = $url;
            if (count($urls) >= $max) break;
        }
    }
    return $urls;
}

$posts = get_posts([
    'post_type'      => 'product',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'orderby'        => 'title',
    'order'          => 'ASC',
]);

$out = fopen('php://stdout', 'w');
fputcsv($out, $columns);

$skipped = [];
foreach ($posts as $p) {
    $stock = (int) get_field('stock_quantity', $p->ID);
    if ($stock < 1) {
        $skipped[] = "{$p->ID} {$p->post_title} (out of stock)";
        continue;
    }

    $price_raw = get_field('sale_price', $p->ID) ?: get_field('price', $p->ID);
    $price = whatnot_price_int($price_raw);
    if ($price === '') {
        $skipped[] = "{$p->ID} {$p->post_title} (no price)";
        continue;
    }

    $title = $p->post_title;
    $featured = get_the_post_thumbnail_url($p->ID, 'full') ?: '';
    $gallery = gallery_urls($p->ID, 7);

    $row = [
        'Trading Card Games',
        infer_subcategory($title),
        $title,
        build_description($p),
        $stock,
        'Buy it Now',
        $price,
        infer_shipping($title),
        'FALSE',
        'Not Hazmat',
        'New',
        clean_price(get_field('cost', $p->ID)),
        get_field('sku', $p->ID) ?: '',
        $featured,
        $gallery[0] ?? '',
        $gallery[1] ?? '',
        $gallery[2] ?? '',
        $gallery[3] ?? '',
        $gallery[4] ?? '',
        $gallery[5] ?? '',
        $gallery[6] ?? '',
    ];

    fputcsv($out, $row);
}

fclose($out);

if (!empty($skipped)) {
    fwrite(STDERR, "\nSkipped " . count($skipped) . " posts:\n");
    foreach ($skipped as $s) fwrite(STDERR, "  $s\n");
}
