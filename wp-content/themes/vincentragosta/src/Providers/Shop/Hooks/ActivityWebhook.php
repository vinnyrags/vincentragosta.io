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
 * WP-side producers (right now): pull-box slot confirmation. Other
 * activity producers (battles, coupons, community goals, low-stock,
 * pull-box lifecycle) live in Nous and broadcast directly.
 *
 * Non-blocking — Nous outage cannot delay or fail a slot confirmation.
 *
 * Action coverage:
 *   shop_pull_box_slot_claimed → activity.pull_box.claim
 */
class ActivityWebhook implements Hook
{
    public function register(): void
    {
        add_action('shop_pull_box_slot_claimed', [$this, 'onPullBoxSlotClaimed'], 10, 3);
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
                'tier'    => (string) ($box['tier'] ?? ''),
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
