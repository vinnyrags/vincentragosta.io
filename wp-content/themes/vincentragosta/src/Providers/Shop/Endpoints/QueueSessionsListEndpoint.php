<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop\Endpoints;

use ChildTheme\Providers\Shop\Hooks\QueueMigration;
use ChildTheme\Providers\Shop\Support\QueueRepository;
use Mythus\Support\Rest\Endpoint;
use WP_REST_Request;
use WP_REST_Response;

/**
 * List recent queue sessions (for `!queue history` and admin tooling).
 * Public read, includes per-session totals.
 */
class QueueSessionsListEndpoint extends Endpoint
{
    public function getRoute(): string
    {
        return '/queue/sessions';
    }

    public function getMethods(): string
    {
        return 'GET';
    }

    public function getPermission(WP_REST_Request $request): bool
    {
        return true;
    }

    public function getArgs(): array
    {
        return [
            'limit' => [
                'required' => false,
                'type'     => 'integer',
                'default'  => 10,
            ],
            'status' => [
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ];
    }

    public function callback(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $sessionsTable = QueueMigration::sessionsTable();
        $entriesTable = QueueMigration::entriesTable();

        $limit = max(1, min(100, (int) $request->get_param('limit')));
        $statusFilter = (string) $request->get_param('status');

        $where = '';
        $args = [];
        if ($statusFilter !== '' && in_array($statusFilter, QueueRepository::SESSION_STATUSES, true)) {
            $where = ' WHERE status = %s';
            $args[] = $statusFilter;
        }

        $sql = "SELECT * FROM {$sessionsTable}{$where} ORDER BY created_at DESC LIMIT %d";
        $args[] = $limit;
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A);

        $sessions = [];
        foreach (($rows ?: []) as $row) {
            $session = QueueRepository::serializeSession($row);
            $session['totalEntries'] = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$entriesTable} WHERE session_id = %d",
                    (int) $row['id']
                )
            );
            $sessions[] = $session;
        }

        return new WP_REST_Response(['sessions' => $sessions]);
    }
}
