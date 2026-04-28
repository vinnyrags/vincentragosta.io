<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop\Hooks;

use Mythus\Contracts\Hook;

/**
 * Ensures the unified pull-box tables exist.
 *
 * Two tables: pull_boxes (one row per pull box opened on stream) and
 * pull_box_slots (one row per claimed slot). The UNIQUE constraint on
 * (pull_box_id, slot_number) is what makes slot claims atomic — a
 * concurrent attempt to claim the same slot fails at the DB layer.
 */
class PullBoxMigration implements Hook
{
    private const OPTION_KEY = 'shop_pull_box_schema_version';
    private const SCHEMA_VERSION = '1';

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
        $boxesTable = self::boxesTable();
        $slotsTable = self::slotsTable();
        $charsetCollate = $wpdb->get_charset_collate();

        $boxesSql = "CREATE TABLE {$boxesTable} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            tier VARCHAR(20) NOT NULL,
            price_cents INT UNSIGNED NOT NULL,
            stripe_price_id VARCHAR(255) NULL,
            total_slots INT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'open',
            discord_message_id VARCHAR(50) NULL,
            created_at DATETIME NOT NULL,
            closed_at DATETIME NULL,
            PRIMARY KEY  (id),
            KEY idx_tier_status (tier, status),
            KEY idx_status_created (status, created_at)
        ) {$charsetCollate};";

        $slotsSql = "CREATE TABLE {$slotsTable} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            pull_box_id BIGINT UNSIGNED NOT NULL,
            slot_number INT UNSIGNED NOT NULL,
            claim_status VARCHAR(20) NOT NULL DEFAULT 'pending',
            discord_user_id VARCHAR(50) NULL,
            discord_handle VARCHAR(100) NULL,
            customer_email VARCHAR(191) NULL,
            stripe_session_id VARCHAR(255) NULL,
            claimed_at DATETIME NOT NULL,
            confirmed_at DATETIME NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uq_box_slot (pull_box_id, slot_number),
            KEY idx_box_status (pull_box_id, claim_status),
            KEY idx_stripe_session (stripe_session_id)
        ) {$charsetCollate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($boxesSql);
        dbDelta($slotsSql);

        update_option(self::OPTION_KEY, self::SCHEMA_VERSION, false);
    }

    public static function boxesTable(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'pull_boxes';
    }

    public static function slotsTable(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'pull_box_slots';
    }
}
