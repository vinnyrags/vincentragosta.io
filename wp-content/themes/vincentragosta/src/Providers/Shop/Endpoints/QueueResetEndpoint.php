<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop\Endpoints;

use ChildTheme\Providers\Shop\Hooks\QueueMigration;
use Mythus\Support\Rest\Endpoint;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Test-only: wipe all queue sessions and entries.
 *
 * Called by Nous's `/reset` slash command after the legacy SQLite tables are
 * cleared, so the WP source-of-truth queue starts fresh too. Bot-secret
 * authenticated. Returns row counts so the calling command can report
 * what was cleared.
 *
 * RTS entries are queue rows (`type=rts`) and are wiped along with the
 * rest — no separate carve-out needed.
 */
class QueueResetEndpoint extends Endpoint
{
    public function getRoute(): string
    {
        return '/queue/reset';
    }

    public function getMethods(): string
    {
        return 'POST';
    }

    public function getPermission(WP_REST_Request $request): bool
    {
        if (current_user_can('manage_options')) {
            return true;
        }
        $secret = (string) $request->get_header('X-Bot-Secret');
        $expected = defined('LIVESTREAM_SECRET') ? LIVESTREAM_SECRET : '';
        return $expected !== '' && hash_equals($expected, $secret);
    }

    public function callback(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $sessions = QueueMigration::sessionsTable();
        $entries = QueueMigration::entriesTable();

        // Entries first (foreign-key-ish even though the schema doesn't
        // enforce it — the snapshot endpoint joins on session_id).
        $entriesDeleted = $wpdb->query("DELETE FROM {$entries}");
        $sessionsDeleted = $wpdb->query("DELETE FROM {$sessions}");

        return new WP_REST_Response([
            'entriesDeleted'  => (int) $entriesDeleted,
            'sessionsDeleted' => (int) $sessionsDeleted,
        ]);
    }
}
