<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop\Endpoints;

use ChildTheme\Providers\Shop\Hooks\CardRequestsMigration;
use ChildTheme\Providers\Shop\Support\QueueRepository;
use Mythus\Support\Rest\Endpoint;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Create a "Request to See" entry for a card single.
 *
 * Storefront calls this endpoint anonymously to queue a card to be
 * featured on stream. Idempotent — re-submitting the same email + card
 * while the status is `pending` returns the existing row.
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
        $email = $request->get_param('email');
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

        global $wpdb;
        $table = CardRequestsMigration::tableName();

        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE card_post_id = %d AND requester_email = %s AND status = %s LIMIT 1",
                $cardId,
                $email,
                'pending'
            ),
            ARRAY_A
        );

        if ($existing) {
            $this->notifyBot($card, (int) $existing['id'], $email, $discord, true);
            return new WP_REST_Response([
                'id'      => (int) $existing['id'],
                'status'  => 'duplicate',
                'message' => "You've already requested this card — we'll still get to it.",
            ]);
        }

        $inserted = $wpdb->insert(
            $table,
            [
                'card_post_id'     => $cardId,
                'requester_email'  => $email,
                'discord_username' => $discord !== '' ? $discord : null,
                'requested_at'     => current_time('mysql'),
                'status'           => 'pending',
            ],
            ['%d', '%s', '%s', '%s', '%s']
        );

        if (!$inserted) {
            return new WP_Error(
                'server_error',
                'Could not save your request. Try again in a moment.',
                ['status' => 500]
            );
        }

        $requestId = (int) $wpdb->insert_id;

        $this->mirrorToQueue($card, $requestId, $email, $discord);
        $this->notifyBot($card, $requestId, $email, $discord, false);

        return new WP_REST_Response([
            'id'      => $requestId,
            'status'  => 'pending',
            'message' => "Your request is in! We'll call you out when it's shown.",
        ]);
    }

    /**
     * Mirror this RTS request into the unified queue so it shows up in the
     * itzenzo.tv LIVE QUEUE section. Failure is logged, never thrown — the
     * card request itself has already succeeded by the time this runs.
     */
    private function mirrorToQueue(\WP_Post $card, int $requestId, string $email, string $discord): void
    {
        try {
            $session = $this->queueRepository->findActiveSession();
            if (!$session) {
                return;
            }

            $this->queueRepository->createEntry([
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
                'external_ref'    => 'rts:' . $requestId,
            ]);
        } catch (\Throwable $e) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('CardRequestEndpoint queue mirror failed: ' . $e->getMessage());
        }
    }

    private function notifyBot(\WP_Post $card, int $requestId, string $email, string $discord, bool $duplicate): void
    {
        $endpoint = defined('NOUS_BOT_URL') ? NOUS_BOT_URL : 'http://127.0.0.1:3100';
        $url = rtrim($endpoint, '/') . '/webhooks/card-request-notify';

        $body = [
            'request_id'       => $requestId,
            'card_id'          => $card->ID,
            'card_title'       => $card->post_title,
            'card_slug'        => $card->post_name,
            'email'            => $email,
            'discord_username' => $discord,
            'duplicate'        => $duplicate,
        ];

        $secret = defined('LIVESTREAM_SECRET') ? LIVESTREAM_SECRET : '';

        wp_remote_post($url, [
            'timeout'  => 2,
            'blocking' => false,
            'headers'  => [
                'Content-Type'  => 'application/json',
                'X-Bot-Secret'  => $secret,
            ],
            'body'     => wp_json_encode($body),
        ]);
    }
}
