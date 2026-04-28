<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop\Hooks;

use Mythus\Contracts\Hook;

/**
 * Ensures the unified queue tables exist.
 *
 * Two tables: queue_sessions (one row per livestream queue window) and
 * queue_entries (orders, pack battles, pull box entries, RTS requests).
 * Position is computed at read time from created_at order — never stored.
 */
class QueueMigration implements Hook
{
    private const OPTION_KEY = 'shop_queue_schema_version';
    private const SCHEMA_VERSION = '3';

    public function register(): void
    {
        add_action('init', [$this, 'maybeInstall']);
    }

    public function maybeInstall(): void
    {
        if (get_option(self::OPTION_KEY) === self::SCHEMA_VERSION) {
            return;
        }

        global $wpdb;

        $sessionsTable = self::sessionsTable();
        $entriesTable = self::entriesTable();
        $charsetCollate = $wpdb->get_charset_collate();

        $sessionsSql = "CREATE TABLE {$sessionsTable} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            status VARCHAR(20) NOT NULL DEFAULT 'open',
            channel_message_id VARCHAR(50) NULL,
            duck_race_winner_user_id VARCHAR(50) NULL,
            created_at DATETIME NOT NULL,
            closed_at DATETIME NULL,
            PRIMARY KEY  (id),
            KEY idx_status (status),
            KEY idx_created_at (created_at)
        ) {$charsetCollate};";

        $entriesSql = "CREATE TABLE {$entriesTable} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id BIGINT UNSIGNED NOT NULL,
            queue_number INT UNSIGNED NULL,
            type VARCHAR(20) NOT NULL,
            source VARCHAR(20) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'queued',
            discord_user_id VARCHAR(50) NULL,
            discord_handle VARCHAR(100) NULL,
            customer_email VARCHAR(191) NULL,
            order_number VARCHAR(50) NULL,
            display_name VARCHAR(100) NULL,
            detail_label VARCHAR(255) NULL,
            detail_data LONGTEXT NULL,
            stripe_session_id VARCHAR(255) NULL,
            external_ref VARCHAR(255) NULL,
            created_at DATETIME NOT NULL,
            completed_at DATETIME NULL,
            PRIMARY KEY  (id),
            KEY idx_session_status_created (session_id, status, created_at),
            KEY idx_session_queue_number (session_id, queue_number),
            KEY idx_stripe_session (stripe_session_id),
            KEY idx_external_ref (external_ref),
            KEY idx_type_source (type, source)
        ) {$charsetCollate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sessionsSql);
        dbDelta($entriesSql);

        // Backfill queue_number for existing rows that predate v3. Each
        // session gets entries numbered 1..N in created_at order — the
        // permanent "deli ticket" number that follows the entry through
        // its whole lifecycle (queued → active → completed).
        $this->backfillQueueNumbers($entriesTable);

        update_option(self::OPTION_KEY, self::SCHEMA_VERSION, false);
    }

    private function backfillQueueNumbers(string $entriesTable): void
    {
        global $wpdb;
        $sessionIds = $wpdb->get_col(
            "SELECT DISTINCT session_id FROM {$entriesTable} WHERE queue_number IS NULL"
        );
        foreach ($sessionIds as $sessionId) {
            $entryIds = $wpdb->get_col($wpdb->prepare(
                "SELECT id FROM {$entriesTable} WHERE session_id = %d AND queue_number IS NULL ORDER BY created_at ASC, id ASC",
                (int) $sessionId
            ));
            foreach ($entryIds as $i => $entryId) {
                $wpdb->update(
                    $entriesTable,
                    ['queue_number' => $i + 1],
                    ['id' => (int) $entryId],
                    ['%d'],
                    ['%d']
                );
            }
        }
    }

    public static function sessionsTable(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'queue_sessions';
    }

    public static function entriesTable(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'queue_entries';
    }
}
