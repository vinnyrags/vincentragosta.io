<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop\Endpoints;

use ChildTheme\Providers\Shop\Support\QueueRepository;
use Mythus\Support\Rest\Endpoint;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Update a queue entry — typically status transitions
 * (queued → active → completed) but also identifier/detail edits.
 * Bot-secret authenticated.
 */
class QueueEntryUpdateEndpoint extends Endpoint
{
    public function __construct(private readonly QueueRepository $repository)
    {
    }

    public function getRoute(): string
    {
        return '/queue/entries/(?P<id>\d+)';
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
            'discord_handle' => [
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
        ];
    }

    public function callback(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id = (int) $request->get_param('id');
        $entry = $this->repository->findEntry($id);
        if (!$entry) {
            return new WP_Error('not_found', 'Entry not found.', ['status' => 404]);
        }

        $update = [];
        if ($request->has_param('status')) {
            $status = (string) $request->get_param('status');
            if (!in_array($status, QueueRepository::ENTRY_STATUSES, true)) {
                return new WP_Error('invalid_status', 'Invalid entry status.', ['status' => 400]);
            }
            $update['status'] = $status;
        }
        foreach (['discord_handle', 'display_name', 'detail_label'] as $field) {
            if ($request->has_param($field)) {
                $update[$field] = $request->get_param($field);
            }
        }
        if ($request->has_param('detail_data')) {
            $detailData = $request->get_param('detail_data');
            if (is_string($detailData) && $detailData !== '') {
                $decoded = json_decode($detailData, true);
                if (is_array($decoded)) {
                    $detailData = $decoded;
                }
            }
            $update['detail_data'] = $detailData;
        }

        if (empty($update)) {
            return new WP_Error('no_changes', 'No fields to update.', ['status' => 400]);
        }

        $this->repository->updateEntry($id, $update);
        $updated = $this->repository->findEntry($id);

        do_action('shop_queue_entry_updated', $updated, $entry);

        return new WP_REST_Response([
            'entry' => QueueRepository::serializeEntry($updated),
        ]);
    }
}
