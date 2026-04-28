<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop\Endpoints;

use ChildTheme\Providers\Shop\Support\PullBoxRepository;
use Mythus\Support\Rest\Endpoint;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Atomically claim a set of slots in a pull box. All-or-nothing — if
 * any of the requested slots are already taken, the request fails and
 * no claims are recorded. Bot-secret only.
 *
 * Called by Nous (or by the WP pull-box-checkout endpoint when a buyer
 * confirms their slot selection on the homepage modal). The claim is
 * marked `pending` until the Stripe webhook flips it to `confirmed`
 * after payment success.
 */
class PullBoxClaimEndpoint extends Endpoint
{
    public function __construct(private readonly PullBoxRepository $repository)
    {
    }

    public function getRoute(): string
    {
        return '/pull-boxes/(?P<id>\d+)/claim';
    }

    public function getMethods(): string
    {
        return 'POST';
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
            'id'                => ['required' => true, 'type' => 'integer'],
            'slots'             => ['required' => true],
            'discord_user_id'   => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'discord_handle'    => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'customer_email'    => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_email'],
            'stripe_session_id' => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
        ];
    }

    public function callback(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $boxId = (int) $request->get_param('id');
        $slots = $request->get_param('slots');

        if (!is_array($slots)) {
            $decoded = is_string($slots) ? json_decode($slots, true) : null;
            $slots = is_array($decoded) ? $decoded : null;
        }
        if (!is_array($slots) || empty($slots)) {
            return new WP_Error('invalid_slots', 'slots must be a non-empty array of slot numbers.', ['status' => 400]);
        }

        $slots = array_values(array_unique(array_map('intval', $slots)));

        $box = $this->repository->findBox($boxId);
        if (!$box) {
            return new WP_Error('not_found', 'Pull box not found.', ['status' => 404]);
        }
        if ($box['status'] !== 'open') {
            return new WP_Error('box_not_open', 'Pull box is not open.', ['status' => 409]);
        }

        $insertedIds = $this->repository->claimSlots($boxId, $slots, [
            'discord_user_id'   => $request->get_param('discord_user_id'),
            'discord_handle'    => $request->get_param('discord_handle'),
            'customer_email'    => $request->get_param('customer_email'),
            'stripe_session_id' => $request->get_param('stripe_session_id'),
        ]);

        if ($insertedIds === false) {
            // Either a slot was already claimed, or out-of-range.
            return new WP_Error(
                'slot_conflict',
                'One or more slots are already claimed or out of range.',
                ['status' => 409, 'claimedSlots' => $this->repository->getClaimedSlotNumbers($boxId)]
            );
        }

        return new WP_REST_Response([
            'claimed' => $slots,
            'ids'     => $insertedIds,
        ], 201);
    }
}
