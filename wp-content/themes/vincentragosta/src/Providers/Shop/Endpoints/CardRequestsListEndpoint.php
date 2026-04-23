<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop\Endpoints;

use ChildTheme\Providers\Shop\Hooks\CardRequestsMigration;
use Mythus\Support\Rest\Endpoint;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * List card view requests.
 *
 * Consumed by the Nous bot (via shared secret) and by the WordPress
 * admin screen (via edit_posts capability).
 */
class CardRequestsListEndpoint extends Endpoint
{
    public function getRoute(): string
    {
        return '/card-requests';
    }

    public function getMethods(): string
    {
        return 'GET';
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
            'status' => [
                'required' => false,
                'type'     => 'string',
                'default'  => 'pending',
                'enum'     => ['pending', 'shown', 'skipped', 'all'],
            ],
            'card_id' => [
                'required' => false,
                'type'     => 'integer',
            ],
            'limit' => [
                'required' => false,
                'type'     => 'integer',
                'default'  => 50,
            ],
            'order' => [
                'required' => false,
                'type'     => 'string',
                'default'  => 'oldest',
                'enum'     => ['oldest', 'newest'],
            ],
        ];
    }

    public function callback(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        global $wpdb;
        $table = CardRequestsMigration::tableName();

        $status = (string) $request->get_param('status');
        $cardId = (int) $request->get_param('card_id');
        $limit = max(1, min(500, (int) $request->get_param('limit')));
        $order = $request->get_param('order') === 'newest' ? 'DESC' : 'ASC';

        $where = [];
        $args = [];

        if ($status !== 'all') {
            $where[] = 'status = %s';
            $args[] = $status;
        }

        if ($cardId > 0) {
            $where[] = 'card_post_id = %d';
            $args[] = $cardId;
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $sql = "SELECT * FROM {$table} {$whereSql} ORDER BY requested_at {$order} LIMIT %d";
        $args[] = $limit;

        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A);

        $results = array_map(static function (array $row): array {
            $cardId = (int) $row['card_post_id'];
            $card = get_post($cardId);

            return [
                'id'               => (int) $row['id'],
                'card_id'          => $cardId,
                'card_title'       => $card ? $card->post_title : null,
                'card_slug'        => $card ? $card->post_name : null,
                'email'            => $row['requester_email'],
                'discord_username' => $row['discord_username'],
                'requested_at'     => $row['requested_at'],
                'status'           => $row['status'],
                'shown_at'         => $row['shown_at'],
                'notes'            => $row['notes'],
            ];
        }, $rows ?: []);

        return new WP_REST_Response([
            'count'    => count($results),
            'requests' => $results,
        ]);
    }
}
