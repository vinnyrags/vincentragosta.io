<?php
/**
 * One-shot surgical content sweep — generalize the duck race prize
 * copy. Was: "nightly duck race for a free pack. One entry per buyer".
 * Now: "nightly duck race. One entry per unique buyer..." + a new
 * sentence about the prize varying stream to stream.
 *
 * Same pattern as content-sweep-2026-05-11.php — surgical str_replace
 * on the exact target string. Idempotent: no-op if the source has
 * already been updated.
 *
 * Run via: wp eval-file scripts/content-sweep-duck-race-prize-2026-05-13.php --allow-root
 */

$replacements = [
    'Every card product purchase automatically enters you in the nightly duck race for a free pack. One entry per buyer regardless of how many items you bought — the more you spend doesn&#8217;t help your odds, but the more often you buy across nights does.'
        => 'Every card product purchase automatically enters you in the nightly duck race. One entry per unique buyer regardless of how many items you bought — the more you spend doesn&#8217;t help your odds, but the more often you buy across nights does. The winner takes home the night&#8217;s prize — what it is varies stream to stream (a pack, a card from the catalog, a playmat, a coupon — operator&#8217;s call based on the stash that night).',

    // wpautop-emitted alternative (regular apostrophes instead of &#8217;).
    "Every card product purchase automatically enters you in the nightly duck race for a free pack. One entry per buyer regardless of how many items you bought — the more you spend doesn't help your odds, but the more often you buy across nights does."
        => "Every card product purchase automatically enters you in the nightly duck race. One entry per unique buyer regardless of how many items you bought — the more you spend doesn't help your odds, but the more often you buy across nights does. The winner takes home the night's prize — what it is varies stream to stream (a pack, a card from the catalog, a playmat, a coupon — operator's call based on the stash that night).",
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
    foreach ($p['sections'] as $j => $s) {
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
    echo "No-op: every target string already in the new form. Pages were not written.\n";
    return;
}

update_field('itzenzo_pages', $pages, 'option');

echo "Applied " . count($changes) . " edit(s):\n";
foreach ($changes as $c) {
    echo "  - {$c}\n";
}
echo "\nDone. Flush Next.js cache on itzenzo.tv to see changes immediately.\n";
