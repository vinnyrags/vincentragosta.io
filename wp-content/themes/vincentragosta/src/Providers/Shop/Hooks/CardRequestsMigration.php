<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop\Hooks;

use Mythus\Contracts\Hook;

/**
 * Ensures the card view requests table exists.
 *
 * Runs dbDelta() under an option version check so new columns/indexes
 * applied later can roll out automatically. Backed by the standard
 * WordPress $wpdb prefix so multisite installs stay isolated.
 */
class CardRequestsMigration implements Hook
{
    private const OPTION_KEY = 'shop_card_requests_schema_version';
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

        $tableName = self::tableName();
        $charsetCollate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$tableName} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            card_post_id BIGINT UNSIGNED NOT NULL,
            requester_email VARCHAR(191) NOT NULL,
            discord_username VARCHAR(100) NULL,
            requested_at DATETIME NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            shown_at DATETIME NULL,
            notes TEXT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uq_card_email_status (card_post_id, requester_email, status),
            KEY idx_status_requested_at (status, requested_at),
            KEY idx_card_post_id (card_post_id)
        ) {$charsetCollate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        update_option(self::OPTION_KEY, self::SCHEMA_VERSION, false);
    }

    public static function tableName(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'card_view_requests';
    }
}
