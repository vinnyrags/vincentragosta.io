<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop\Endpoints;

use ChildTheme\Providers\Shop\Hooks\CardRequestsMigration;
use Mythus\Support\Rest\Endpoint;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Update a card request's status (shown or skipped).
 *
 * POST /card-requests/(?P<id>\d+)/(?P<action>shown|skip)
 */
class CardRequestStatusEndpoint extends Endpoint
{
    public function getRoute(): string
    {
        return '/card-requests/(?P<id>\d+)/(?P<action>shown|skip)';
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

    public function callback(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id = (int) $request->get_param('id');
        $action = (string) $request->get_param('action');

        if ($id < 1) {
            return new WP_Error(
                'invalid_id',
                'Missing card request id.',
                ['status' => 400]
            );
        }

        global $wpdb;
        $table = CardRequestsMigration::tableName();

        $newStatus = $action === 'shown' ? 'shown' : 'skipped';

        $data = [
            'status' => $newStatus,
        ];
        $format = ['%s'];

        if ($newStatus === 'shown') {
            $data['shown_at'] = current_time('mysql');
            $format[] = '%s';
        }

        $updated = $wpdb->update(
            $table,
            $data,
            ['id' => $id],
            $format,
            ['%d']
        );

        if ($updated === false) {
            return new WP_Error(
                'server_error',
                'Could not update card request.',
                ['status' => 500]
            );
        }

        if ($updated === 0) {
            return new WP_Error(
                'not_found',
                'Card request not found.',
                ['status' => 404]
            );
        }

        return new WP_REST_Response([
            'id'     => $id,
            'status' => $newStatus,
        ]);
    }
}
