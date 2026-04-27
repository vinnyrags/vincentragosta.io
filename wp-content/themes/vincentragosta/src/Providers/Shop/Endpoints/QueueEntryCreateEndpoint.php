<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop\Endpoints;

use ChildTheme\Providers\Shop\Support\QueueRepository;
use Mythus\Support\Rest\Endpoint;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Create a queue entry. Bot-secret authenticated.
 *
 * Accepts type (order|pack_battle|pull_box|rts), source (discord|shop),
 * and identifier/detail fields. Idempotent on `external_ref` — re-submitting
 * the same external_ref returns the existing entry.
 */
class QueueEntryCreateEndpoint extends Endpoint
{
    public function __construct(private readonly QueueRepository $repository)
    {
    }

    public function getRoute(): string
    {
        return '/queue/entries';
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
            'session_id' => [
                'required' => false,
                'type'     => 'integer',
            ],
            'type' => [
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'source' => [
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'discord_user_id' => [
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'discord_handle' => [
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'customer_email' => [
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_email',
            ],
            'order_number' => [
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'display_name' => [
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'detail_label' => [
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'detail_data' => [
                'required' => false,
            ],
            'stripe_session_id' => [
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'external_ref' => [
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ];
    }

    public function callback(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $type = (string) $request->get_param('type');
        $source = (string) $request->get_param('source');

        if (!in_array($type, QueueRepository::TYPES, true)) {
            return new WP_Error('invalid_type', 'Invalid entry type.', ['status' => 400, 'allowed' => QueueRepository::TYPES]);
        }
        if (!in_array($source, QueueRepository::SOURCES, true)) {
            return new WP_Error('invalid_source', 'Invalid entry source.', ['status' => 400, 'allowed' => QueueRepository::SOURCES]);
        }

        $externalRef = trim((string) $request->get_param('external_ref'));
        if ($externalRef !== '') {
            $existing = $this->repository->findEntryByExternalRef($externalRef);
            if ($existing) {
                return new WP_REST_Response([
                    'entry'     => QueueRepository::serializeEntry($existing),
                    'duplicate' => true,
                ]);
            }
        }

        $sessionId = (int) $request->get_param('session_id');
        if ($sessionId < 1) {
            $session = $this->repository->findActiveSession();
            if (!$session) {
                return new WP_Error('no_active_session', 'No queue session is open.', ['status' => 409]);
            }
            $sessionId = (int) $session['id'];
        } else {
            $session = $this->repository->findSession($sessionId);
            if (!$session) {
                return new WP_Error('session_not_found', 'Session not found.', ['status' => 404]);
            }
        }

        $detailData = $request->get_param('detail_data');
        if (is_string($detailData) && $detailData !== '') {
            $decoded = json_decode($detailData, true);
            if (is_array($decoded)) {
                $detailData = $decoded;
            }
        }

        $id = $this->repository->createEntry([
            'session_id'        => $sessionId,
            'type'              => $type,
            'source'            => $source,
            'discord_user_id'   => $request->get_param('discord_user_id'),
            'discord_handle'    => $request->get_param('discord_handle'),
            'customer_email'    => $request->get_param('customer_email'),
            'order_number'      => $request->get_param('order_number'),
            'display_name'      => $request->get_param('display_name'),
            'detail_label'      => $request->get_param('detail_label'),
            'detail_data'       => $detailData,
            'stripe_session_id' => $request->get_param('stripe_session_id'),
            'external_ref'      => $externalRef !== '' ? $externalRef : null,
        ]);

        $entry = $this->repository->findEntry($id);

        return new WP_REST_Response([
            'entry'     => QueueRepository::serializeEntry($entry),
            'duplicate' => false,
        ], 201);
    }
}
