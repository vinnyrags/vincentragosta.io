<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop\Hooks;

use Mythus\Contracts\Hook;

/**
 * One-shot drop of the legacy `wp_card_view_requests` table.
 *
 * The dedicated RTS table was created by `CardRequestsMigration` (now
 * deleted). RTS is now stored as a regular `wp_queue_entries` row with
 * `type=rts` — single source of truth, no parallel ledger.
 *
 * This hook runs once per environment on `init`, drops the legacy table,
 * cleans up the old version option, and marks itself complete via a new
 * option key. Safe to leave registered indefinitely — subsequent boots
 * short-circuit immediately.
 *
 * After the drop has rolled out to all environments (dev → staging →
 * production), this class and its registration in ShopProvider can be
 * deleted in a follow-up commit.
 */
class CardRequestsTableDrop implements Hook
{
    private const DROPPED_OPTION = 'shop_card_requests_dropped';
    private const LEGACY_VERSION_OPTION = 'shop_card_requests_schema_version';

    public function register(): void
    {
        add_action('init', [$this, 'maybeDrop']);
    }

    public function maybeDrop(): void
    {
        if (get_option(self::DROPPED_OPTION) === '1') {
            return;
        }

        global $wpdb;

        $tableName = $wpdb->prefix . 'card_view_requests';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query("DROP TABLE IF EXISTS {$tableName}");

        delete_option(self::LEGACY_VERSION_OPTION);
        update_option(self::DROPPED_OPTION, '1', false);
    }
}
