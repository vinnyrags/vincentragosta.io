<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop\Endpoints;

use ChildTheme\Providers\Shop\Support\QueueRepository;
use Mythus\Support\Rest\Endpoint;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Update a queue session — change status (close/race/complete), set
 * channel message ID, or set duck race winner. Bot-secret authenticated.
 */
class QueueSessionUpdateEndpoint extends Endpoint
{
    public function __construct(private readonly QueueRepository $repository)
    {
    }

    public function getRoute(): string
    {
        return '/queue/sessions/(?P<id>\d+)';
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
            'id' => [
                'required' => true,
                'type'     => 'integer',
            ],
            'status' => [
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'channel_message_id' => [
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'duck_race_winner_user_id' => [
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ];
    }

    public function callback(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id = (int) $request->get_param('id');
        $session = $this->repository->findSession($id);
        if (!$session) {
            return new WP_Error('not_found', 'Session not found.', ['status' => 404]);
        }

        $update = [];
        if ($request->has_param('status')) {
            $status = (string) $request->get_param('status');
            if (!in_array($status, QueueRepository::SESSION_STATUSES, true)) {
                return new WP_Error('invalid_status', 'Invalid session status.', ['status' => 400]);
            }
            $update['status'] = $status;
        }
        if ($request->has_param('channel_message_id')) {
            $update['channel_message_id'] = $request->get_param('channel_message_id');
        }
        if ($request->has_param('duck_race_winner_user_id')) {
            $update['duck_race_winner_user_id'] = (string) $request->get_param('duck_race_winner_user_id');
        }

        if (empty($update)) {
            return new WP_Error('no_changes', 'No fields to update.', ['status' => 400]);
        }

        $this->repository->updateSession($id, $update);
        $updated = $this->repository->findSession($id);

        return new WP_REST_Response([
            'session' => QueueRepository::serializeSession($updated),
        ]);
    }
}
