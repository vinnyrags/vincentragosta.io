<?php
/**
 * One-shot surgical content sweep — Whatnot-first migration (2026-05-16).
 *
 * Migrates the customer-facing copy in the `itzenzo_pages` ACF repeater
 * from the original "itzenzo.tv is the live venue" framing to the new
 * "Whatnot is the live venue, itzenzo.tv is the catalog" framing.
 *
 * Same pattern as the prior content-sweep-* scripts: surgical
 * str_replace against specific target strings. Idempotent — running
 * twice is a no-op once the new strings are in place. Does NOT touch
 * any section that doesn't match a target, so concurrent edits by
 * another operator are preserved.
 *
 * Run via:
 *   wp eval-file scripts/content-sweep-whatnot-2026-05-16.php --allow-root
 *
 * Pre-flight (run yourself first):
 *   wp db export /tmp/before-whatnot-sweep-$(date +%Y%m%d-%H%M%S).sql
 *
 * Post-flight:
 *   wp cache flush
 *   curl https://itzenzo.tv/api/revalidate to flush Next.js cache
 */

// Each replacement: [old string → new string]. Include both apostrophe
// forms (\' and &#8217;) where the source string contains apostrophes,
// since wpautop may emit either depending on the editor flow used to
// write the content. Strings without apostrophes only need one entry.
$replacements = [
    // --- About page (slug: about) ---

    // What is itzenzo.tv? section
    'itzenzo.tv is a trading card shop and livestream community. I sell sealed Pokemon, anime, and collectible card products — booster boxes, booster packs, elite trainer boxes, and more. Every card night is livestreamed, so you can watch your packs get opened in real time. It\'s part shop, part entertainment, and entirely built on community.'
        => 'itzenzo.tv is a trading card shop and the catalog home for itzenzoTTV. I sell sealed Pokemon, anime, and collectible card products — booster boxes, booster packs, elite trainer boxes, and more — alongside a hand-inspected singles catalog. Live pack openings, singles auctions, and group breaks happen on Whatnot during scheduled shows. It\'s part shop, part live entertainment, and entirely built on community.',
    'itzenzo.tv is a trading card shop and livestream community. I sell sealed Pokemon, anime, and collectible card products — booster boxes, booster packs, elite trainer boxes, and more. Every card night is livestreamed, so you can watch your packs get opened in real time. It&#8217;s part shop, part entertainment, and entirely built on community.'
        => 'itzenzo.tv is a trading card shop and the catalog home for itzenzoTTV. I sell sealed Pokemon, anime, and collectible card products — booster boxes, booster packs, elite trainer boxes, and more — alongside a hand-inspected singles catalog. Live pack openings, singles auctions, and group breaks happen on Whatnot during scheduled shows. It&#8217;s part shop, part live entertainment, and entirely built on community.',

    // Our Story section
    'nearly three years of livestream card sales on TikTok and Twitch'
        => 'nearly three years of livestream card sales across TikTok, Twitch, and now Whatnot',

    // --- Stream page (slug: stream) ---

    // Stream hero subtitle
    'Card nights, gaming nights, and the schedule for what airs each night of the week.'
        => 'Card nights live on Whatnot, gaming nights on Twitch + Discord, and the schedule for what airs each night of the week.',

    // Card Nights section content
    'Monday, Tuesday, and Wednesday at 8PM EST. Pokemon and anime, opened live — sealed product, pull boxes, and pack battles. Yu-Gi-Oh inventory is on the way; we\'ll roll it into the rotation as it lands.'
        => 'Monday, Tuesday, and Wednesday at 8PM EST live on <strong>Whatnot</strong>. Pokemon and anime sealed product opened live, singles auctions, and group breaks. Catch the shows at <a href="https://whatnot.com/user/itzenzottv" target="_blank" rel="noopener noreferrer">whatnot.com/user/itzenzottv</a>. Yu-Gi-Oh inventory is on the way; we\'ll roll it into the rotation as it lands.',
    'Monday, Tuesday, and Wednesday at 8PM EST. Pokemon and anime, opened live — sealed product, pull boxes, and pack battles. Yu-Gi-Oh inventory is on the way; we&#8217;ll roll it into the rotation as it lands.'
        => 'Monday, Tuesday, and Wednesday at 8PM EST live on <strong>Whatnot</strong>. Pokemon and anime sealed product opened live, singles auctions, and group breaks. Catch the shows at <a href="https://whatnot.com/user/itzenzottv" target="_blank" rel="noopener noreferrer">whatnot.com/user/itzenzottv</a>. Yu-Gi-Oh inventory is on the way; we&#8217;ll roll it into the rotation as it lands.',

    // --- How It Works — Livestream (slug: how-it-works-livestream) ---

    // Hero subtitle
    'How buying, pack battles, and duck races play out in real time during a card night.'
        => 'How buying, pack openings, and giveaways play out in real time during our live shows on Whatnot.',

    // Buying during a Livestream content
    'The main way to buy during a livestream is the website at <a href="https://itzenzo.tv">itzenzo.tv</a> — same shop, same checkout, same shipping math. Most viewers buy from the website while watching the stream.'
        => 'The main way to buy during a live show is on <a href="https://whatnot.com/user/itzenzottv" target="_blank" rel="noopener noreferrer">Whatnot</a>, where the broadcast happens and the auctions run in real time. The itzenzo.tv catalog stays available 24/7 for browsing, Buy Now on sealed product, and Make-an-Offer or Request-to-See on singles.',

    'Discord is the alternative path. The bot drops products, pull boxes, and pack battles in real time with Buy Now buttons in the server. Clicking a button generates a personalized Stripe checkout: it knows your shipping status, applies the correct rate (or skips it if you\'ve bought before), and prefills your email if you\'ve bought before. Same Stripe destination as the website — just a different door.'
        => 'Discord is the coordination layer for live shows. The bot drops show announcements, links, and notifications in the server in real time. Sales themselves happen on Whatnot (live auctions) or itzenzo.tv (catalog Buy Now / Make-an-Offer / Request-to-See).',
    'Discord is the alternative path. The bot drops products, pull boxes, and pack battles in real time with Buy Now buttons in the server. Clicking a button generates a personalized Stripe checkout: it knows your shipping status, applies the correct rate (or skips it if you&#8217;ve bought before), and prefills your email if you&#8217;ve bought before. Same Stripe destination as the website — just a different door.'
        => 'Discord is the coordination layer for live shows. The bot drops show announcements, links, and notifications in the server in real time. Sales themselves happen on Whatnot (live auctions) or itzenzo.tv (catalog Buy Now / Make-an-Offer / Request-to-See).',

    // Pack Battles content — repurpose as paused
    'Multiple buyers each purchase a pack at full retail. Every pack opens live on stream, and the highest-value card wins ALL the cards from ALL the packs. Real competition, real stakes.'
        => 'Pack battles in their current itzenzo.tv form are <strong>paused during the Whatnot transition</strong>. Live pack openings, including competitive multi-buyer formats, now happen on Whatnot via their native Group Break tooling.',

    'No shipping is collected at buy-in — only the winner pays shipping after the battle is declared. Losers pay nothing for shipping.'
        => 'Whatnot collects shipping upfront as part of each slot price — different model from the itzenzo.tv speculative-shipping flow, but no surprises for the buyer.',

    // Duck Races content
    'Every card product purchase automatically enters you in the nightly duck race. One entry per unique buyer regardless of how many items you bought — the more you spend doesn\'t help your odds, but the more often you buy across nights does. The winner takes home the night\'s prize — what it is varies stream to stream (a pack, a card from the catalog, a playmat, a coupon — operator\'s call based on the stash that night).'
        => 'Every card product purchase during a live show automatically enters you in the nightly duck race. One entry per unique buyer regardless of how many items you bought — the more you spend doesn\'t help your odds, but the more often you buy across nights does. The winner takes home the night\'s prize — what it is varies show to show (a pack, a card from the catalog, a playmat, a coupon — operator\'s call based on the stash that night).',
    'Every card product purchase automatically enters you in the nightly duck race. One entry per unique buyer regardless of how many items you bought — the more you spend doesn&#8217;t help your odds, but the more often you buy across nights does. The winner takes home the night&#8217;s prize — what it is varies stream to stream (a pack, a card from the catalog, a playmat, a coupon — operator&#8217;s call based on the stash that night).'
        => 'Every card product purchase during a live show automatically enters you in the nightly duck race. One entry per unique buyer regardless of how many items you bought — the more you spend doesn&#8217;t help your odds, but the more often you buy across nights does. The winner takes home the night&#8217;s prize — what it is varies show to show (a pack, a card from the catalog, a playmat, a coupon — operator&#8217;s call based on the stash that night).',

    // --- How It Works — Buying (slug: how-it-works-buying) ---

    // Buying from the Shop
    'Every order — whether placed from the website or during a livestream — is queued for the next ship day and confirmed by email — a Stripe receipt lands the moment payment clears, and a follow-up email confirms shipping when the label is printed. Linked Discord accounts also get the confirmation as a DM.'
        => 'Every order — whether placed from the website or during a Whatnot show — is queued for the next ship day and confirmed by email. A Stripe receipt lands the moment payment clears, and a follow-up email confirms shipping when the label is printed. Linked Discord accounts also get the confirmation as a DM.',

    // Card Singles Catalog — RTS line
    'Drop your email and I\'ll feature it during the next card night so you can see edges, surface, and holo shift in real time — no commitment.'
        => 'Drop your email and I\'ll feature it during the next live show on Whatnot so you can see edges, surface, and holo shift in real time — no commitment.',
    'Drop your email and I&#8217;ll feature it during the next card night so you can see edges, surface, and holo shift in real time — no commitment.'
        => 'Drop your email and I&#8217;ll feature it during the next live show on Whatnot so you can see edges, surface, and holo shift in real time — no commitment.',

    // Hand-inspected section — RTS line
    'Hit <strong>Request to See</strong> on the listing and I\'ll feature it on the next card night so you can inspect it on stream — no commitment.'
        => 'Hit <strong>Request to See</strong> on the listing and I\'ll feature it on the next live show on Whatnot so you can inspect it during the broadcast — no commitment.',
    'Hit <strong>Request to See</strong> on the listing and I&#8217;ll feature it on the next card night so you can inspect it on stream — no commitment.'
        => 'Hit <strong>Request to See</strong> on the listing and I&#8217;ll feature it on the next live show on Whatnot so you can inspect it during the broadcast — no commitment.',

    // Request to see header + body
    'Request to see any card on stream'
        => 'Request to see any card during a live show',

    'Drop your email (and Discord username if you have one), and I\'ll feature the card during the next card night so you can see edges, surface, and holo shift in real time.'
        => 'Drop your email (and Discord username if you have one), and I\'ll feature the card during the next live show on Whatnot so you can see edges, surface, and holo shift in real time.',
    'Drop your email (and Discord username if you have one), and I&#8217;ll feature the card during the next card night so you can see edges, surface, and holo shift in real time.'
        => 'Drop your email (and Discord username if you have one), and I&#8217;ll feature the card during the next live show on Whatnot so you can see edges, surface, and holo shift in real time.',

    // Discord Account Linking
    'your name in the live queue and duck race rosters'
        => 'your name on duck race rosters during live shows',

    // --- How It Works — Shipping (slug: how-it-works-shipping) ---

    // Held Inventory section title
    'Held Inventory &amp; Items Opened on Stream'
        => 'Held Inventory &amp; Items Opened During Live Shows',
    'Held Inventory & Items Opened on Stream'
        => 'Held Inventory & Items Opened During Live Shows',

    // Held Inventory body — "opened on stream so you see what you pulled"
    'Your card is opened on stream so you see what you pulled. After the stream, you choose what happens next:'
        => 'Your card is opened during our live show on Whatnot so you see what you pulled. After the show, you choose what happens next:',

    // Held Inventory body — "DM at the end of stream"
    'If your Discord account is linked to your purchase email, you\'ll receive a DM at the end of stream with a one-click checkout link.'
        => 'If your Discord account is linked to your purchase email, you\'ll receive a DM at the end of the live show with a one-click checkout link.',
    'If your Discord account is linked to your purchase email, you&#8217;ll receive a DM at the end of stream with a one-click checkout link.'
        => 'If your Discord account is linked to your purchase email, you&#8217;ll receive a DM at the end of the live show with a one-click checkout link.',

    // --- How It Works — Refund Policy (slug: how-it-works-refund-policy) ---

    // Concerned about a card before buying section
    'Use the <strong>Request to See</strong> button on any card listing — I\'ll feature it on the next card night so you can inspect edges, surface, centering, and holo shift live on stream before you buy. No commitment, no awkward conversation later. If something looks off, just don\'t add it to your cart.'
        => 'Use the <strong>Request to See</strong> button on any card listing — I\'ll feature it on the next live show on Whatnot so you can inspect edges, surface, centering, and holo shift in real time before you buy. No commitment, no awkward conversation later. If something looks off, just don\'t add it to your cart.',
    'Use the <strong>Request to See</strong> button on any card listing — I&#8217;ll feature it on the next card night so you can inspect edges, surface, centering, and holo shift live on stream before you buy. No commitment, no awkward conversation later. If something looks off, just don&#8217;t add it to your cart.'
        => 'Use the <strong>Request to See</strong> button on any card listing — I&#8217;ll feature it on the next live show on Whatnot so you can inspect edges, surface, centering, and holo shift in real time before you buy. No commitment, no awkward conversation later. If something looks off, just don&#8217;t add it to your cart.',

    // The on-stream review is the right place
    'The on-stream review is the right place to settle condition questions, so I don\'t cover post-purchase partial refunds for condition concerns in this policy. Anything genuinely wrong with what you received still gets handled — just DM me and I\'ll work it out.'
        => 'The live-show review is the right place to settle condition questions, so I don\'t cover post-purchase partial refunds for condition concerns in this policy. Anything genuinely wrong with what you received still gets handled — just DM me and I\'ll work it out.',
    'The on-stream review is the right place to settle condition questions, so I don&#8217;t cover post-purchase partial refunds for condition concerns in this policy. Anything genuinely wrong with what you received still gets handled — just DM me and I&#8217;ll work it out.'
        => 'The live-show review is the right place to settle condition questions, so I don&#8217;t cover post-purchase partial refunds for condition concerns in this policy. Anything genuinely wrong with what you received still gets handled — just DM me and I&#8217;ll work it out.',

    // Pack battles & live event purchases — mark paused
    'Pack battle buy-ins are refundable up until the battle starts on stream. Once packs are being opened, the buy-in is locked — the result is what it is, and I can\'t un-open the cards.'
        => 'Pack battles in their original itzenzo.tv form are <strong>paused during the Whatnot transition</strong>. Live pack openings (including competitive multi-buyer formats) now run on Whatnot under Whatnot\'s native rules and refund policy.',
    'Pack battle buy-ins are refundable up until the battle starts on stream. Once packs are being opened, the buy-in is locked — the result is what it is, and I can&#8217;t un-open the cards.'
        => 'Pack battles in their original itzenzo.tv form are <strong>paused during the Whatnot transition</strong>. Live pack openings (including competitive multi-buyer formats) now run on Whatnot under Whatnot&#8217;s native rules and refund policy.',

    'If you can\'t make a battle you signed up for, DM me before it runs and I\'ll refund or roll your entry to the next one.'
        => 'For any current Whatnot show signups or refund questions, DM me on Discord or reply to your Stripe receipt email.',
    'If you can&#8217;t make a battle you signed up for, DM me before it runs and I&#8217;ll refund or roll your entry to the next one.'
        => 'For any current Whatnot show signups or refund questions, DM me on Discord or reply to your Stripe receipt email.',

    // --- Gaming (slug: gaming) ---

    'Card nights and gaming nights share the same audience and the same energy. After the After Dark segment wraps on a card night, the stream often transitions into a gaming session — Minecraft, Fortnite, or whatever the community wants. The TCG side and the gaming side feed each other: viewers come for the packs, stay for the squads.'
        => 'Card nights (live on Whatnot) and gaming nights (live on Twitch + Discord) share the same audience and the same energy. After the live card show wraps, the community often shifts into a gaming session — Minecraft, Fortnite, or whatever the community wants. The TCG side and the gaming side feed each other: viewers come for the packs, stay for the squads.',

    // --- Community (slug: community) ---

    'itzenzo.tv is a livestream shop, but the community lives in Discord. <strong>Over 1,000 members</strong> show up for card nights, pack battles, duck races, Minecraft realms, and weekend tournaments. Every live queue, flash sale, pack battle entry, and tracking update flows through email and Discord — when you join the server, you join everything.'
        => 'itzenzo.tv is the catalog. Whatnot is where the live shows happen. Discord is where the community lives — <strong>over 1,000 members</strong> show up for live show prep, pre-show announcements, duck race rosters, Minecraft realms, and weekend tournaments. Every order confirmation, shipping update, and live-show notification flows through email and Discord — when you join the server, you join everything.',

    // Card nights in Discord
    'Monday, Tuesday, and Wednesday at 8 PM EST, Discord lights up. The live queue posts in real time, pack battles and duck races run in their own channels, and the bot drops products as they hit the table. See <a href="/how-it-works/livestream">how livestream buying works</a> for the full flow.'
        => 'Monday, Tuesday, and Wednesday at 8 PM EST, the live show kicks off on Whatnot and Discord lights up around it. Pre-show announcements drop in #announcements, duck race rosters fill in real time, and the bot posts product links and show notifications as the show runs. See <a href="/how-it-works/livestream">how live shows work</a> for the full flow.',
];

$pages = (array) get_field('itzenzo_pages', 'option');
if (empty($pages)) {
    echo "ABORT: itzenzo_pages option is empty — refusing to write.\n";
    return;
}

$changes = [];
foreach ($pages as $i => $p) {
    if (!isset($p['sections']) || !is_array($p['sections'])) {
        continue;
    }
    $slug = $p['slug'] ?? '(unknown)';

    // Also sweep page-level hero_subtitle and the section titles
    foreach (['hero_subtitle', 'hero_title'] as $page_field) {
        if (!isset($p[$page_field]) || !is_string($p[$page_field])) continue;
        $before = $p[$page_field];
        $after = $before;
        foreach ($replacements as $find => $replace) {
            $after = str_replace($find, $replace, $after);
        }
        if ($after !== $before) {
            $pages[$i][$page_field] = $after;
            $changes[] = "{$slug} / page-{$page_field}";
        }
    }

    foreach ($p['sections'] as $j => $s) {
        // Sweep section titles too (e.g., "Held Inventory & Items Opened on Stream"
        // → "... During Live Shows")
        if (isset($s['title']) && is_string($s['title'])) {
            $before = $s['title'];
            $after = $before;
            foreach ($replacements as $find => $replace) {
                $after = str_replace($find, $replace, $after);
            }
            if ($after !== $before) {
                $pages[$i]['sections'][$j]['title'] = $after;
                $changes[] = "{$slug} / section-title: " . substr($before, 0, 50);
            }
        }

        if (!isset($s['content']) || !is_string($s['content'])) {
            continue;
        }
        $before = $s['content'];
        $after = $before;
        foreach ($replacements as $find => $replace) {
            $after = str_replace($find, $replace, $after);
        }
        if ($after !== $before) {
            $pages[$i]['sections'][$j]['content'] = $after;
            $changes[] = "{$slug} / " . ($s['title'] ?? '(untitled)');
        }
    }
}

if (empty($changes)) {
    echo "No-op: every target string already in the new form (or not found). Pages were not written.\n";
    return;
}

update_field('itzenzo_pages', $pages, 'option');

echo "Applied " . count($changes) . " edit(s):\n";
foreach ($changes as $c) {
    echo "  - {$c}\n";
}
echo "\nDone. Don't forget:\n";
echo "  1. wp cache flush\n";
echo "  2. curl https://itzenzo.tv/api/revalidate (flush Next.js cache)\n";
