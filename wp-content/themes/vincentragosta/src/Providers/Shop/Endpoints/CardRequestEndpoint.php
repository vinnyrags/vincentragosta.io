<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop\Endpoints;

use ChildTheme\Providers\Shop\Support\QueueRepository;
use Mythus\Support\Rest\Endpoint;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Create a "Request to See" entry for a card single.
 *
 * Storefront calls this endpoint anonymously to queue a card to be
 * featured on stream. The request is stored as a regular `wp_queue_entries`
 * row with `type=rts`, so it shows up alongside orders in the unified queue
 * (homepage Live Queue panel, Activity Feed, /queue Discord embed).
 *
 * Idempotent: re-submitting the same email + card while the entry is still
 * queued/active returns the existing row. Once the entry is shown
 * (status=completed) or skipped, a fresh request for the same card is
 * allowed and creates a new row.
 */
class CardRequestEndpoint extends Endpoint
{
    public function __construct(private readonly QueueRepository $queueRepository)
    {
    }

    public function getRoute(): string
    {
        return '/card-request';
    }

    public function getMethods(): string
    {
        return 'POST';
    }

    public function getPermission(WP_REST_Request $request): bool
    {
        return true;
    }

    public function getArgs(): array
    {
        return [
            'card_id' => [
                'required' => true,
                'type'     => 'integer',
            ],
            'email' => [
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_email',
            ],
            'discord_username' => [
                'required'          => false,
                'type'              => 'string',
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ];
    }

    public function callback(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $cardId = (int) $request->get_param('card_id');
        // Normalize: every email-keyed lookup downstream is case-sensitive
        // in MySQL `utf8mb4_bin` columns and SQLite TEXT, so a `User@Gmail.com`
        // submission would otherwise create a separate identity from the
        // same buyer's prior `user@gmail.com` purchases.
        $email = strtolower(trim((string) $request->get_param('email')));
        $discord = trim((string) $request->get_param('discord_username'));

        if ($cardId < 1) {
            return new WP_Error(
                'invalid_card',
                'Missing or invalid card id.',
                ['status' => 400]
            );
        }

        if (!$email || !is_email($email)) {
            return new WP_Error(
                'invalid_email',
                'Enter a valid email so we can tell you when the card is shown.',
                ['status' => 400]
            );
        }

        $card = get_post($cardId);
        if (!$card || $card->post_type !== 'card' || $card->post_status !== 'publish') {
            return new WP_Error(
                'card_not_found',
                'Card is not available for requests.',
                ['status' => 404]
            );
        }

        $session = $this->queueRepository->findActiveSession();
        if (!$session) {
            // The bot is supposed to keep a queue open between streams
            // (`/offline` opens the next one). If we land here, that
            // invariant is broken — surface it loudly instead of dropping
            // the request silently.
            return new WP_Error(
                'queue_unavailable',
                'Card requests are temporarily unavailable. Try again in a moment.',
                ['status' => 503]
            );
        }

        $externalRef = sprintf('rts:%d:%s', $cardId, $email);

        $existing = $this->queueRepository->findActiveEntryByExternalRef($externalRef);
        if ($existing) {
            return new WP_REST_Response([
                'id'      => (int) $existing['id'],
                'status'  => 'duplicate',
                'message' => "You've already requested this card — we'll still get to it.",
            ]);
        }

        $entryId = $this->queueRepository->createEntry([
            'session_id'      => (int) $session['id'],
            'type'            => 'rts',
            'source'          => 'shop',
            'discord_handle'  => $discord !== '' ? $discord : null,
            'customer_email'  => $email,
            'display_name'    => $discord !== '' ? $discord : $email,
            'detail_label'    => $card->post_title,
            'detail_data'     => [
                'cardId'   => $card->ID,
                'cardSlug' => $card->post_name,
                'cardName' => $card->post_title,
            ],
            'external_ref'    => $externalRef,
        ]);

        // Fire a sibling action to the queue write so notifiers
        // (currently CardRequestEmailNotifier; future hooks can attach)
        // can fan out without this endpoint owning email / Discord
        // logic. Mirrors the shop_card_offer_submitted pattern in
        // CardOfferEndpoint.
        do_action('shop_card_request_submitted', [
            'card_id'          => $cardId,
            'card_title'       => $card->post_title,
            'email'            => $email,
            'discord_username' => $discord,
            'entry_id'         => $entryId,
        ]);

        return new WP_REST_Response([
            'id'      => $entryId,
            'status'  => 'pending',
            'message' => "Your request is in! We'll call you out when it's shown.",
        ]);
    }
}
