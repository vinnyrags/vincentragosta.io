<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop\Endpoints;

use ChildTheme\Providers\Shop\Support\PullBoxRepository;
use Mythus\Support\Rest\Endpoint;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Update a pull box — close it, replenish its total_slots, or store
 * the Discord message id of the on-stream embed. Bot-secret only.
 */
class PullBoxUpdateEndpoint extends Endpoint
{
    public function __construct(private readonly PullBoxRepository $repository)
    {
    }

    public function getRoute(): string
    {
        return '/pull-boxes/(?P<id>\d+)';
    }

    public function getMethods(): array
    {
        return ['PATCH', 'POST'];
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
            'id'                 => ['required' => true, 'type' => 'integer'],
            'status'             => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'total_slots'        => ['required' => false, 'type' => 'integer'],
            'discord_message_id' => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
        ];
    }

    public function callback(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id = (int) $request->get_param('id');
        $box = $this->repository->findBox($id);
        if (!$box) {
            return new WP_Error('not_found', 'Pull box not found.', ['status' => 404]);
        }

        $update = [];
        if ($request->has_param('status')) {
            $status = (string) $request->get_param('status');
            if (!in_array($status, PullBoxRepository::BOX_STATUSES, true)) {
                return new WP_Error('invalid_status', 'Invalid status.', ['status' => 400]);
            }
            $update['status'] = $status;
        }
        if ($request->has_param('total_slots')) {
            $newTotal = (int) $request->get_param('total_slots');
            if ($newTotal < 1) {
                return new WP_Error('invalid_total_slots', 'total_slots must be at least 1.', ['status' => 400]);
            }
            // Don't allow shrinking below the highest claimed slot.
            $highestClaimed = max([0, ...$this->repository->getClaimedSlotNumbers($id)]);
            if ($newTotal < $highestClaimed) {
                return new WP_Error(
                    'shrink_below_claimed',
                    "Can't shrink to {$newTotal} — slot {$highestClaimed} is already claimed.",
                    ['status' => 409]
                );
            }
            $update['total_slots'] = $newTotal;
        }
        if ($request->has_param('discord_message_id')) {
            $update['discord_message_id'] = $request->get_param('discord_message_id');
        }

        if (empty($update)) {
            return new WP_Error('no_changes', 'No fields to update.', ['status' => 400]);
        }

        $this->repository->updateBox($id, $update);
        return new WP_REST_Response([
            'box' => PullBoxRepository::serializeBox($this->repository->findBox($id)),
        ]);
    }
}
