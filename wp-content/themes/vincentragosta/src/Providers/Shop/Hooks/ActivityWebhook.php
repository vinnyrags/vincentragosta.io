<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop\Hooks;

use ChildTheme\Providers\Shop\Support\PullBoxRepository;
use Mythus\Contracts\Hook;

/**
 * Forwards display-ready "activity" events to Nous so the homepage
 * Activity Feed panel can show a chronological log of livestream
 * happenings — siblings of the queue/session events that already
 * flow through QueueChangeWebhook.
 *
 * WP-side producers: pull-box slot confirmation, plus pull-box
 * lifecycle (open/close) — these used to broadcast only from Nous's
 * `/pull` slash command, missing the WP-side paths (admin "Reset Pull
 * Box" button, auto-create on first homepage hit).
 *
 * Non-blocking — Nous outage cannot delay or fail any of the underlying
 * write paths.
 *
 * Action coverage:
 *   shop_pull_box_slot_claimed → activity.pull_box.claim
 *   shop_pull_box_created      → activity.pull_box.opened
 *   shop_pull_box_closed       → activity.pull_box.closed
 *
 * Intentionally NOT bridged: shop_pull_box_updated. It fires on every
 * stock decrement, which would flood the feed.
 */
class ActivityWebhook implements Hook
{
    public function register(): void
    {
        add_action('shop_pull_box_slot_claimed', [$this, 'onPullBoxSlotClaimed'], 10, 3);
        add_action('shop_pull_box_created', [$this, 'onPullBoxCreated'], 10, 1);
        add_action('shop_pull_box_closed', [$this, 'onPullBoxClosed'], 10, 1);
    }

    public function onPullBoxCreated(?array $box): void
    {
        if (!$box) {
            return;
        }
        $name = (string) ($box['name'] ?? 'Pull Box');
        $slots = (int) ($box['total_slots'] ?? 0);
        $priceCents = (int) ($box['price_cents'] ?? 0);
        $priceLabel = $priceCents > 0
            ? '$' . number_format($priceCents / 100, 2)
            : '';

        $description = $slots
            ? sprintf('%s — %d slots%s', $name, $slots, $priceLabel ? " at {$priceLabel}" : '')
            : $name;

        $this->dispatch('activity.pull_box.opened', [
            'kind'        => 'pull_box.opened',
            'title'       => 'Pull box opened',
            'description' => $description,
            'color'       => 'sky',
            'icon'        => '🎰',
            'meta'        => [
                'boxId'      => (int) ($box['id'] ?? 0),
                'boxName'    => $name,
                'totalSlots' => $slots,
                'priceCents' => $priceCents,
            ],
        ]);
    }

    public function onPullBoxClosed(?array $box): void
    {
        if (!$box) {
            return;
        }
        $name = (string) ($box['name'] ?? 'Pull Box');
        $this->dispatch('activity.pull_box.closed', [
            'kind'        => 'pull_box.closed',
            'title'       => 'Pull box closed',
            'description' => sprintf('%s — chase prize hit, fresh box opening shortly.', $name),
            'color'       => 'sky',
            'icon'        => '🎰',
            'meta'        => [
                'boxId'   => (int) ($box['id'] ?? 0),
                'boxName' => $name,
            ],
        ]);
    }

    public function onPullBoxSlotClaimed(?array $box, array $slotNumbers, array $buyerInfo): void
    {
        if (!$box) {
            return;
        }

        $buyer = $this->buyerLabel($buyerInfo);
        $slotsLabel = $this->slotsLabel($slotNumbers);
        $boxName = (string) ($box['name'] ?? 'Pull Box');

        $this->dispatch('activity.pull_box.claim', [
            'kind'        => 'pull_box.claim',
            'title'       => 'Pull box slot claimed',
            'description' => sprintf('%s claimed %s in %s', $buyer, $slotsLabel, $boxName),
            'color'       => 'sky',
            'icon'        => '🎰',
            'meta'        => [
                'boxId'   => (int) $box['id'],
                'boxName' => $boxName,
                'slots'   => array_map('intval', $slotNumbers),
                'buyer'   => $buyer,
            ],
        ]);
    }

    private function buyerLabel(array $buyerInfo): string
    {
        if (!empty($buyerInfo['discord_handle'])) {
            return '@' . ltrim((string) $buyerInfo['discord_handle'], '@');
        }
        if (!empty($buyerInfo['customer_email'])) {
            $email = (string) $buyerInfo['customer_email'];
            $atPos = strpos($email, '@');
            if ($atPos > 0) {
                return $email[0] . '•••@' . substr($email, $atPos + 1);
            }
            return $email;
        }
        return 'Someone';
    }

    private function slotsLabel(array $slotNumbers): string
    {
        $count = count($slotNumbers);
        if ($count === 0) {
            return 'a slot';
        }
        if ($count === 1) {
            return 'slot ' . (int) $slotNumbers[0];
        }
        return 'slots ' . implode(', ', array_map('intval', $slotNumbers));
    }

    private function dispatch(string $event, array $payload): void
    {
        $endpoint = defined('NOUS_BOT_URL') ? NOUS_BOT_URL : 'http://127.0.0.1:3100';
        $url = rtrim($endpoint, '/') . '/webhooks/activity-changed';

        $secret = defined('LIVESTREAM_SECRET') ? LIVESTREAM_SECRET : '';
        if ($secret === '') {
            return;
        }

        wp_remote_post($url, [
            'timeout'  => 2,
            'blocking' => false,
            'headers'  => [
                'Content-Type' => 'application/json',
                'X-Bot-Secret' => $secret,
            ],
            'body'     => wp_json_encode([
                'event'     => $event,
                'data'      => $payload,
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            ]),
        ]);
    }
}
