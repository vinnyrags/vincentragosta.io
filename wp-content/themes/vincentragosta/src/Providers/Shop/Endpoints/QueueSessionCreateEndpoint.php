<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop\Endpoints;

use ChildTheme\Providers\Shop\Support\QueueRepository;
use Mythus\Support\Rest\Endpoint;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Open a new queue session. Bot-secret authenticated.
 *
 * Refuses if there is already an open or racing session — only one queue
 * window is active at a time. Returns the newly created session.
 */
class QueueSessionCreateEndpoint extends Endpoint
{
    public function __construct(private readonly QueueRepository $repository)
    {
    }

    public function getRoute(): string
    {
        return '/queue/sessions';
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
            'channel_message_id' => [
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ];
    }

    public function callback(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $existing = $this->repository->findActiveSession();
        if ($existing) {
            return new WP_Error(
                'session_exists',
                'A queue session is already open. Close it before opening a new one.',
                ['status' => 409, 'session' => QueueRepository::serializeSession($existing)]
            );
        }

        $id = $this->repository->createSession([
            'status'             => 'open',
            'channel_message_id' => $request->get_param('channel_message_id'),
        ]);

        $session = $this->repository->findSession($id);

        return new WP_REST_Response([
            'session' => QueueRepository::serializeSession($session),
        ], 201);
    }
}
