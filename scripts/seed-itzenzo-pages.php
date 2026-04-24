<?php
/**
 * Seed the itzenzo.tv Pages ACF repeater.
 *
 * Writes canonical copy for /about, /how-it-works, /gaming, /cards,
 * and /thank-you into the `itzenzo_pages` repeater on the shop-settings
 * ACF options page. Once seeded, WordPress is the single source of
 * truth for this content — editors change it via WP admin; the Next.js
 * frontend reads it through WPGraphQL.
 *
 * Usage:
 *   ddev wp eval-file scripts/seed-itzenzo-pages.php
 *   FORCE=1 ddev wp eval-file scripts/seed-itzenzo-pages.php
 *
 * Remote:
 *   wp eval-file scripts/seed-itzenzo-pages.php --path=/var/www/site/wp --allow-root
 *
 * The script is idempotent by default — it refuses to overwrite existing
 * data. Set FORCE=1 to replace whatever is already in the repeater.
 */

if (!defined('ABSPATH')) {
    echo "This script must be run via WP-CLI: ddev wp eval-file scripts/seed-itzenzo-pages.php\n";
    exit(1);
}

if (!function_exists('update_field')) {
    echo "Error: ACF is not loaded. Make sure Advanced Custom Fields Pro is active.\n";
    exit(1);
}

$force = !empty(getenv('FORCE'));

$pages = [
    [
        'name'          => 'About',
        'slug'          => 'about',
        'hero_title'    => 'The Shop. The Stream. <strong>The Community.</strong>',
        'hero_subtitle' => 'Sealed TCG product, live pack openings, and a community built around cards, games, and good energy.',
        'sections'      => [
            [
                'title'   => 'What is itzenzo.tv?',
                'content' => '<p>itzenzo.tv is a trading card shop and livestream community. We sell sealed Pokemon, anime, and collectible card products — booster boxes, booster packs, elite trainer boxes, and more. Every card night is livestreamed, so you can watch your packs get opened in real time. It\'s part shop, part entertainment, and entirely built on community.</p>',
            ],
            [
                'title'   => 'Our Story',
                'content' => '<p>This started as a COVID project on June 5th, 2021. What began as a way to stay busy turned into a real business — nearly three years of livestream card sales on TikTok and Twitch, hundreds of orders shipped, and a community that showed up night after night.</p><p>In late 2023, life got in the way and the shop went on a two-and-a-half year hiatus. The inventory sat in boxes, the streams stopped, and the Discord went quiet.</p><p>Now we\'re back. Same energy, same community, better infrastructure. The shop has moved to its own home at itzenzo.tv, the Discord bot handles everything from pack battles to shipping notifications, and the product pipeline is tighter than ever. If you were here before — welcome back. If you\'re new — you picked a good time.</p>',
            ],
            [
                'title'   => 'Card Nights',
                'content' => '<p>Monday through Thursday at 8PM EST. Each card night moves through Pokemon, anime, and gaming segments — with After Dark content later in the evening for 18+ viewers.</p>',
            ],
            [
                'title'   => 'Gaming Nights',
                'content' => '<p>Friday through Sunday at 8PM EST. Fortnite squads with the community, Minecraft horror mods, Marvel Rivals, and whatever the community votes for. Family streams with dad are a regular occurrence. Join the Discord for the Minecraft server IP.</p>',
            ],
            [
                'title'   => 'After Dark',
                'content' => '<p>After the main card night wraps up, the stream shifts to After Dark — our 18+ segment featuring mature-content TCG products. This includes anime cards and playmats with suggestive or adult artwork, sold exclusively to age-verified buyers.</p><p>After Dark only runs on Twitch (with the mature tag enabled) and Instagram. TikTok and YouTube simulcasts end before the After Dark segment begins. In Discord, mature product drops appear in the age-gated channel — you\'ll need the Ena role (18+ verified) to access it.</p>',
            ],
            [
                'title'   => 'The Community',
                'content' => '<p>The Discord server is where everything happens — live queue tracking, pack battle entries, duck race rosters, flash sales, and exclusive deals. Over 1,000 members and growing. Follow on Twitch, TikTok, Instagram, and YouTube for clips, drops, and stream announcements.</p>',
            ],
            [
                'title'   => 'About the Builder',
                'content' => '<p>itzenzo.tv is built and operated by <a href="https://vincentragosta.io" target="_blank" rel="noopener noreferrer">Vincent Ragosta</a> — a full-stack engineer who\'s been collecting Pokemon cards since 1998, and started selling them live on stream when the world shut down in 2021.</p>',
            ],
        ],
    ],
    [
        'name'          => 'How It Works',
        'slug'          => 'how-it-works',
        'hero_title'    => 'How It <strong>Works</strong>',
        'hero_subtitle' => 'Everything you need to know about buying, shipping, and getting involved.',
        'sections'      => [
            [
                'title'   => 'Buying from the Shop',
                'content' => '<p>Browse products anytime at itzenzo.tv. Add what you want to your cart and check out via Stripe — no account required. Your email is saved locally so future visits skip the shipping prompt and apply your existing shipping coverage automatically.</p><p>Every order — whether placed from the website or during a livestream — is queued for the next ship day and confirmed in Discord.</p>',
            ],
            [
                'title'   => 'Buying during a Livestream',
                'content' => '<p>During a livestream, the Discord bot drops products, pull boxes, and pack battles in real time with Buy Now buttons. Clicking a button generates a personalized Stripe checkout: it knows your shipping status, applies the correct rate (or skips it if you\'re already covered), and prefills your email if you\'ve bought before.</p><p>Most livestream buyers actually check out from the website — the Discord buttons are an alternative path, not the only way in.</p>',
            ],
            [
                'title'   => 'Pack Battles',
                'content' => '<p>Multiple buyers each purchase a pack at full retail. Every pack opens live on stream, and the highest-value card wins ALL the cards from ALL the packs. Real competition, real stakes.</p><p>No shipping is collected at buy-in — only the winner pays shipping after the battle is declared. Losers pay nothing for shipping.</p>',
            ],
            [
                'title'   => 'Duck Races',
                'content' => '<p>Every card product purchase automatically enters you in the nightly duck race for a free pack. One entry per buyer regardless of how many items you bought — the more you spend doesn\'t help your odds, but the more often you buy across nights does.</p><p>The race runs as an animation in Discord, takes about 12 seconds, and the winner is completely random.</p>',
            ],
            [
                'title'   => 'Card Singles Catalog',
                'content' => '<p>Browse the full catalog of raw card singles at <a href="/cards">itzenzo.tv/cards</a>. Every card in the catalog is <strong>Near Mint</strong>, hand-inspected, and ready to ship. Same checkout flow as sealed product — add to cart, pay with Stripe.</p><p>Not sure about a card before buying? Hit the <strong>Request to See</strong> button on any card in the grid. Drop your email and we\'ll feature it during the next card night so you can see edges, surface, and holo shift in real time — no commitment.</p>',
            ],
            [
                'title'   => 'Card Shop (Discord)',
                'content' => '<p>Graded cards, vintage one-offs, and anything outside the catalog get listed directly in <code>#card-shop</code> on Discord as embeds with Buy Now buttons. Click to check out — a reservation locks the card to you for 30 minutes while you complete the purchase.</p><p>If you don\'t complete checkout in time, the card is automatically released back to the shop for the next buyer.</p>',
            ],
            [
                'title'   => 'Why Flat-Rate Shipping?',
                'content' => '<p>One shipping charge covers every purchase you make in the same period — a week for US orders, a month for international. Buy a single card or fifteen products in the same week, you pay shipping exactly once.</p><p>This rewards stocking up and removes the per-item shipping math that kills small-purchase impulse buys.</p>',
            ],
            [
                'title'   => 'Shipping Schedule',
                'content' => '<p>Domestic orders ship every Monday. International orders ship at the end of each month. Your shipping coverage is checked automatically at checkout using your email — if you\'ve already paid for the period, your next order ships free.</p><p>The moment a shipping label is purchased, a tracking number is automatically posted to your Discord DMs along with a link to follow your package. No need to ask — it just shows up.</p><p>International buyers can DM anytime to ship sooner if you don\'t want to wait for the month-end batch.</p>',
            ],
            [
                'title'   => 'Discord Account Linking',
                'content' => '<p>Enter your Discord username at checkout (or use the Buy Now buttons in Discord) to link your purchases to your Discord account. Linking unlocks: automatic role promotions as you hit purchase milestones, your name in the live queue and duck race rosters, and tracking DMs sent directly to you.</p><p>You can buy without linking — the experience is just better when you do.</p>',
            ],
            [
                'title'   => 'Payment Security',
                'content' => '<p>All payments go through Stripe, a PCI-compliant payment processor used by millions of businesses. We never see or store your card information — Stripe handles every part of the transaction. You\'ll get an email receipt directly from Stripe for every purchase.</p>',
            ],
        ],
    ],
    [
        'name'          => 'Gaming',
        'slug'          => 'gaming',
        'hero_title'    => 'Three Realms. <strong>Many Games.</strong>',
        'hero_subtitle' => 'Where the brand plays — Minecraft realms, livestream squads, gacha grinds, and weekend tournaments.',
        'sections'      => [
            [
                'title'   => 'The Three Realms',
                'content' => '<p>We run three Minecraft worlds, each with its own vibe. Survival hardcore for the brave, Bedrock horror for the brave-and-curious, and a long-running creative realm for the builders. Realm codes are kept off the public internet — to join, head to <code>#minecraft</code> in Discord and react to the pinned message. The bot DMs you the invite for whichever realm you picked.</p>',
            ],
            [
                'title'   => 'Card Nights Gaming Crossover',
                'content' => '<p>Card nights and gaming nights share the same audience and the same energy. After the After Dark segment wraps on a card night, the stream often transitions into a gaming session — Minecraft, Fortnite, or whatever the community wants. The TCG side and the gaming side feed each other: viewers come for the packs, stay for the squads.</p>',
            ],
            [
                'title'   => 'Family Streams',
                'content' => '<p>A regular fixture: streams with dad, friends from college, and longtime community members. Multi-generational, low-stakes, high-banter. These are some of the most-watched streams of the week, and the easiest entry point if you\'ve never hung out in the Discord before.</p>',
            ],
            [
                'title'   => 'Other Games We Play',
                'content' => '<p>Fortnite squads, Marvel Rivals, and a heavy gacha rotation — Honkai: Star Rail, Zenless Zone Zero, Genshin Impact, and Karuta inside Discord itself. Tournament weekends pop up periodically; community votes decide the game. If you want a game added to the rotation, suggest it in <code>#general-gaming</code>.</p>',
            ],
            [
                'title'   => 'How to Join',
                'content' => '<p>Step one: join the Discord. Step two: open <code>#minecraft</code>, pick the realm you want, react to the bot\'s message. Your invite arrives via DM within seconds. If your DMs are closed, the bot can\'t deliver — open them via the server\'s Privacy Settings. For everything else (gaming chat, squad-finding, tournament signups), <code>#general-gaming</code> is the home base.</p>',
            ],
        ],
    ],
    [
        'name'          => 'Cards',
        'slug'          => 'cards',
        'hero_title'    => 'Raw Singles. <strong>Near Mint.</strong>',
        'hero_subtitle' => 'Every card in the catalog is Near Mint, ready to ship, and free to request a closer look on stream before you buy.',
        'sections'      => [
            [
                'title'   => 'Every card is Near Mint',
                'content' => '<p>Unless the listing explicitly says otherwise, every card in this catalog is graded <strong>Near Mint</strong> by hand — edges, surface, and centering all passed a close inspection before listing. If a card slips through that doesn\'t match NM, we\'ll make it right.</p><p>Graded and vintage cards are still sold ad-hoc in <code>#card-shop</code> on Discord via <code>!sell</code> — they don\'t live in this catalog.</p>',
            ],
            [
                'title'   => 'Request to see any card on stream',
                'content' => '<p>Not sure about a card? Hit the <strong>Request to See</strong> button right on the card in the grid. Drop your email (and Discord username if you have one), and we\'ll feature the card during the next card night so you can see edges, surface, and holo shift in real time.</p><p>You\'re not committing to buy — you\'re just asking for a closer look. If it checks out, add it to your cart right from the grid.</p>',
            ],
            [
                'title'   => 'Shipping & coverage',
                'content' => '<p>Cards ship in top-loaders with a team bag, same cadence as everything else on the site — Monday for domestic, month-end for international. If you\'ve already paid shipping for the period, your next card ships free.</p>',
            ],
            [
                'title'   => 'Sold out or moved on',
                'content' => '<p>If a card is listed as sold, we keep the URL live for historical reference and any linkbacks. You can still hit Request to See if you\'re hoping a duplicate comes back into stock — it\'s useful signal for what to restock.</p>',
            ],
        ],
    ],
    [
        'name'          => 'Thank You',
        'slug'          => 'thank-you',
        'hero_title'    => '<strong>Thank you</strong> for your order!',
        'hero_subtitle' => 'Your payment has been processed. You\'ll receive a confirmation via email and a DM in Discord with your tracking number when your order ships.',
        'sections'      => [],
    ],
];

$existing = get_field('itzenzo_pages', 'option');
$hasExisting = is_array($existing) && count($existing) > 0;

if ($hasExisting && !$force) {
    $count = count($existing);
    echo "itzenzo_pages already has {$count} entry(ies). Run with FORCE=1 to overwrite.\n";
    echo "Existing slugs: " . implode(', ', array_column($existing, 'slug')) . "\n";
    return;
}

$ok = update_field('itzenzo_pages', $pages, 'option');

if ($ok === false) {
    echo "Error: update_field returned false. Check ACF field key.\n";
    exit(1);
}

$written = count($pages);
$action = $hasExisting ? 'Overwrote' : 'Seeded';
echo "✓ {$action} itzenzo_pages with {$written} page(s): " . implode(', ', array_column($pages, 'slug')) . "\n";
