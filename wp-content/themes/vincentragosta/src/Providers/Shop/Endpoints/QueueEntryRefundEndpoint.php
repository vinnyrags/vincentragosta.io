<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop\Endpoints;

use ChildTheme\Providers\Shop\Support\QueueRepository;
use Mythus\Support\Rest\Endpoint;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Mark a queue entry refunded by Stripe session id.
 *
 * Called from Nous's refund propagator (lib/refund-propagator.js) on
 * `charge.refunded` and `charge.dispute.*` webhooks AND on manual `!refund`.
 * One round-trip: find by stripe_session_id, set status='refunded', stash
 * refund metadata in detail_data.refund. Idempotent — re-submitting for
 * the same session returns 200 without re-firing change actions.
 */
class QueueEntryRefundEndpoint extends Endpoint
{
    public function __construct(private readonly QueueRepository $repository)
    {
    }

    public function getRoute(): string
    {
        return '/queue/entries/refund';
    }

    public function getMethods(): array
    {
        return ['POST'];
    }

    public function getPermission(WP_REST_Request $request): bool
    {
        if (current_user_can('edit_posts')) {
            return true;
        }
        $secret = (string) $request->get_header('X-Bot-Secret');
        $expected = defined('LIVESTREAM_SECRET') ? LIVESTREAM_SECRET : '';
        return $expected !== '' && hash_equals($expected, $secret);
    }

    public function getArgs(): array
    {
        return [
            'stripe_session_id' => [
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'refund_amount' => [
                'required' => false,
                'type'     => 'integer',
            ],
            'reason' => [
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'is_partial' => [
                'required' => false,
                'type'     => 'boolean',
                'default'  => false,
            ],
        ];
    }

    public function callback(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $stripeSessionId = (string) $request->get_param('stripe_session_id');
        $entry = $this->repository->findEntryByStripeSession($stripeSessionId);
        if (!$entry) {
            return new WP_Error(
                'not_found',
                'No queue entry exists for that Stripe session.',
                ['status' => 404]
            );
        }

        // Already refunded — return the existing entry without re-firing
        // change actions. Lets the propagator be retried safely.
        if (($entry['status'] ?? '') === 'refunded') {
            return new WP_REST_Response([
                'entry'     => QueueRepository::serializeEntry($entry),
                'duplicate' => true,
            ]);
        }

        $existingDetail = [];
        if (!empty($entry['detail_data'])) {
            $decoded = json_decode((string) $entry['detail_data'], true);
            if (is_array($decoded)) {
                $existingDetail = $decoded;
            }
        }

        $existingDetail['refund'] = [
            'amount'     => $request->get_param('refund_amount') !== null
                ? (int) $request->get_param('refund_amount')
                : null,
            'reason'     => $request->get_param('reason') ?: null,
            'is_partial' => (bool) $request->get_param('is_partial'),
            'refunded_at' => current_time('mysql'),
        ];

        $this->repository->updateEntry((int) $entry['id'], [
            'status'      => 'refunded',
            'detail_data' => $existingDetail,
        ]);

        $updated = $this->repository->findEntry((int) $entry['id']);

        return new WP_REST_Response([
            'entry' => QueueRepository::serializeEntry($updated),
        ]);
    }
}
