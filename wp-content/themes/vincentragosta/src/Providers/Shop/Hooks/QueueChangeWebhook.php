<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop\Hooks;

use ChildTheme\Providers\Shop\Support\QueueRepository;
use Mythus\Contracts\Hook;

/**
 * Forwards queue changes to the Nous bot so it can broadcast SSE events
 * to itzenzo.tv homepage subscribers and keep the Discord channel embed
 * fresh. Non-blocking — Nous outage cannot delay or fail a queue write.
 *
 * Action coverage:
 *   shop_queue_entry_created   → entry.added
 *   shop_queue_entry_updated   → entry.advanced (when status changes) /
 *                                entry.completed (when status='completed')
 *   shop_queue_session_created → session.opened
 *   shop_queue_session_updated → session.updated (status, winner, etc.)
 */
class QueueChangeWebhook implements Hook
{
    public function register(): void
    {
        add_action('shop_queue_entry_created', [$this, 'onEntryCreated'], 10, 1);
        add_action('shop_queue_entry_updated', [$this, 'onEntryUpdated'], 10, 2);
        add_action('shop_queue_session_created', [$this, 'onSessionCreated'], 10, 1);
        add_action('shop_queue_session_updated', [$this, 'onSessionUpdated'], 10, 2);
    }

    public function onEntryCreated(array $entry): void
    {
        $this->dispatch('entry.added', [
            'entry'   => QueueRepository::serializeEntry($entry),
            'rawEntry' => QueueRepository::serializeEntryRaw($entry),
        ]);
    }

    public function onEntryUpdated(array $entry, ?array $before): void
    {
        $statusChanged = $before === null || ($before['status'] ?? null) !== ($entry['status'] ?? null);
        $event = 'entry.updated';
        if ($statusChanged) {
            $event = $entry['status'] === 'completed' ? 'entry.completed' : 'entry.advanced';
        }

        $this->dispatch($event, [
            'entry'    => QueueRepository::serializeEntry($entry),
            'rawEntry' => QueueRepository::serializeEntryRaw($entry),
            'previousStatus' => $before['status'] ?? null,
        ]);
    }

    public function onSessionCreated(array $session): void
    {
        $this->dispatch('session.opened', [
            'session' => QueueRepository::serializeSession($session),
        ]);
    }

    public function onSessionUpdated(array $session, ?array $before): void
    {
        $this->dispatch('session.updated', [
            'session'        => QueueRepository::serializeSession($session),
            'previousStatus' => $before['status'] ?? null,
        ]);
    }

    private function dispatch(string $event, array $payload): void
    {
        $endpoint = defined('NOUS_BOT_URL') ? NOUS_BOT_URL : 'http://127.0.0.1:3100';
        $url = rtrim($endpoint, '/') . '/webhooks/queue-changed';

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
