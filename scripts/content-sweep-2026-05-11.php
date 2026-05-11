<?php
/**
 * One-shot surgical content sweep for the email-and-Discord
 * dual-channel rewrite (Phase 4 of the no-Discord buyer plan).
 *
 * Replaces 5 specific Discord-only phrases in the itzenzo_pages
 * ACF repeater with email-and-Discord parallel copy. Idempotent:
 * each replacement is a literal str_replace, so running twice is
 * a no-op on the second pass.
 *
 * Run via: wp eval-file scripts/content-sweep-2026-05-11.php --allow-root
 *
 * Pre-flight: wp db export /tmp/before-content-sweep-*.sql
 */

$replacements = [
    'is queued for the next ship day and confirmed in Discord.'
        => 'is queued for the next ship day and confirmed by email — a Stripe receipt lands the moment payment clears, and a follow-up email confirms shipping when the label is printed. Linked Discord accounts also get the confirmation as a DM.',

    // Production wpautop-emitted variant: paragraphs separated by literal \n
    'and tracking DMs sent directly to you.</p>' . "\n" . '<p>You can buy without linking — the experience is just better when you do.'
        => 'and tracking DMs sent directly to you.</p>' . "\n" . '<p>Discord linking is optional. Every order ships with email tracking by default — Discord just adds a faster real-time channel for status pings, queue callouts, and ship-day notifications. You can buy without linking; the experience is just richer when you do.',
    // Seed-file variant (no whitespace between paragraphs) — covers fresh-env seeds
    'and tracking DMs sent directly to you.</p><p>You can buy without linking — the experience is just better when you do.'
        => 'and tracking DMs sent directly to you.</p><p>Discord linking is optional. Every order ships with email tracking by default — Discord just adds a faster real-time channel for status pings, queue callouts, and ship-day notifications. You can buy without linking; the experience is just richer when you do.',

    'Flat-rate shipping, a predictable schedule, and tracking DMs that land the moment a label is printed.'
        => 'Flat-rate shipping, a predictable schedule, and tracking updates by email and Discord DM the moment a label is printed.',

    'The moment a shipping label is purchased, a tracking number is automatically posted to your Discord DMs along with a link to follow your package. No need to ask — it just shows up.'
        => 'The moment a shipping label is purchased, a tracking number is automatically emailed to you and (if you&#8217;ve linked Discord) posted to your DMs, along with a link to follow your package. No need to ask — it just shows up.',

    'Every live queue, flash sale, pack battle entry, and tracking DM flows through Discord — when you join the server, you join everything.'
        => 'Every live queue, flash sale, pack battle entry, and tracking update flows through email and Discord — when you join the server, you join everything.',
];

$pages = (array) get_field('itzenzo_pages', 'option');
if (empty($pages)) {
    echo "ABORT: itzenzo_pages option is empty — refusing to write.\n";
    return;
}

$changes = [];
$skipped = [];

foreach ($pages as $i => $p) {
    $slug = $p['slug'] ?? '(unknown)';

    foreach (['hero_title', 'hero_subtitle'] as $field) {
        if (!isset($p[$field]) || !is_string($p[$field])) {
            continue;
        }
        $before = $p[$field];
        $after = $before;
        foreach ($replacements as $find => $replace) {
            $after = str_replace($find, $replace, $after);
        }
        if ($after !== $before) {
            $pages[$i][$field] = $after;
            $changes[] = "{$slug} {$field}";
        }
    }

    if (!isset($p['sections']) || !is_array($p['sections'])) {
        continue;
    }

    foreach ($p['sections'] as $j => $s) {
        if (!isset($s['content']) || !is_string($s['content'])) {
            continue;
        }
        $before = $s['content'];
        $after = $before;
        foreach ($replacements as $find => $replace) {
            $after = str_replace($find, $replace, $after);
        }
        $title = $s['title'] ?? '(untitled section)';
        if ($after !== $before) {
            $pages[$i]['sections'][$j]['content'] = $after;
            $changes[] = "{$slug} / {$title}";
        }
    }
}

if (empty($changes)) {
    echo "No-op: every target string already in dual-channel form. Pages were not written.\n";
    return;
}

update_field('itzenzo_pages', $pages, 'option');

echo "Applied " . count($changes) . " edit(s):\n";
foreach ($changes as $c) {
    echo "  - {$c}\n";
}
echo "\nDone. Flush Next.js cache on itzenzo.tv to see changes immediately.\n";
