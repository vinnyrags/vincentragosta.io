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
 *   shop_queue_entry_created   → entry.added [+ roster.updated]
 *   shop_queue_entry_updated   → entry.advanced (when status changes) /
 *                                entry.completed (when status='completed')
 *                                [+ roster.updated when roster shape may have changed]
 *   shop_queue_session_created → session.opened
 *   shop_queue_session_updated → session.updated (status, winner, etc.)
 */
class QueueChangeWebhook implements Hook
{
    public function __construct(private readonly QueueRepository $repository)
    {
    }

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

        // Any new entry MIGHT change the roster (new buyer joins the duck
        // race). Dispatch a roster.updated with the fresh roster so the
        // homepage panel + #duck-race embed both reflect it without
        // re-fetching. Skipped/refunded entries don't go into uniqueBuyers,
        // so dispatching here is safe even for those statuses.
        $this->dispatchRosterUpdate((int) $entry['session_id']);
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

        // Status transitions to/from skipped/refunded change which entries
        // count toward uniqueBuyers. Dispatch roster.updated only when the
        // transition crosses the eligibility boundary — pure queued→active
        // and active→completed don't shift the roster, so skip those.
        if ($statusChanged) {
            $excluded = ['skipped', 'refunded'];
            $wasExcluded = in_array($before['status'] ?? '', $excluded, true);
            $isExcluded = in_array($entry['status'] ?? '', $excluded, true);
            if ($wasExcluded !== $isExcluded) {
                $this->dispatchRosterUpdate((int) $entry['session_id']);
            }
        }
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

    /**
     * Snapshots the current roster and dispatches a roster.updated event.
     * Used by entry-side hooks; the homepage Duck Race panel + the
     * #duck-race Discord embed both consume this. Payload mirrors the
     * GraphQL DuckRaceSnapshot shape minus the per-call winner/status
     * (those flow through session.updated already — keeping the payload
     * roster-scoped here avoids ambiguity for subscribers).
     */
    private function dispatchRosterUpdate(int $sessionId): void
    {
        $buyers = $this->repository->uniqueBuyers($sessionId);
        $this->dispatch('roster.updated', [
            'sessionId'   => $sessionId,
            'rosterCount' => count($buyers),
            'roster'      => array_map(static function ($row) {
                return [
                    'buyer'       => (string) $row['buyer'],
                    'firstSeenAt' => QueueRepository::toIso8601((string) ($row['first_seen'] ?? '')),
                ];
            }, $buyers),
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
