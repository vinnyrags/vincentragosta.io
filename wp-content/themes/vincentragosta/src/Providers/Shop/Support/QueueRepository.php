<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop\Support;

use ChildTheme\Providers\Shop\Hooks\QueueMigration;

/**
 * Encapsulates $wpdb access for the unified queue.
 *
 * Position is computed at read time from created_at order, never stored.
 * Snapshots return the active session, the in-flight entry, and the top
 * N upcoming queued entries in a stable serialized shape.
 */
class QueueRepository
{
    public const TYPES = ['order', 'pack_battle', 'pull_box', 'rts'];
    public const SOURCES = ['discord', 'shop'];
    public const ENTRY_STATUSES = ['queued', 'active', 'completed', 'skipped'];
    public const SESSION_STATUSES = ['open', 'closed', 'racing', 'complete'];

    public const DEFAULT_UPCOMING_LIMIT = 10;

    public function findActiveSession(): ?array
    {
        global $wpdb;
        $table = QueueMigration::sessionsTable();

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE status IN ('open', 'racing') ORDER BY created_at DESC LIMIT 1"
            ),
            ARRAY_A
        );

        return $row ?: null;
    }

    public function findSession(int $id): ?array
    {
        global $wpdb;
        $table = QueueMigration::sessionsTable();

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id),
            ARRAY_A
        );

        return $row ?: null;
    }

    public function createSession(array $data): int
    {
        global $wpdb;
        $table = QueueMigration::sessionsTable();

        $wpdb->insert(
            $table,
            [
                'status'             => $data['status'] ?? 'open',
                'channel_message_id' => $data['channel_message_id'] ?? null,
                'created_at'         => current_time('mysql'),
            ],
            ['%s', '%s', '%s']
        );

        $id = (int) $wpdb->insert_id;
        $row = $this->findSession($id);
        if ($row) {
            do_action('shop_queue_session_created', $row);
        }
        return $id;
    }

    public function updateSession(int $id, array $data): bool
    {
        global $wpdb;
        $table = QueueMigration::sessionsTable();

        $update = [];
        $format = [];

        if (isset($data['status'])) {
            $update['status'] = (string) $data['status'];
            $format[] = '%s';

            if (in_array($data['status'], ['closed', 'complete'], true) && empty($data['closed_at'])) {
                $update['closed_at'] = current_time('mysql');
                $format[] = '%s';
            }
        }

        if (array_key_exists('channel_message_id', $data)) {
            $update['channel_message_id'] = $data['channel_message_id'];
            $format[] = '%s';
        }

        if (array_key_exists('duck_race_winner_user_id', $data)) {
            $update['duck_race_winner_user_id'] = $data['duck_race_winner_user_id'];
            $format[] = '%s';
        }

        if (empty($update)) {
            return false;
        }

        $before = $this->findSession($id);
        $result = $wpdb->update($table, $update, ['id' => $id], $format, ['%d']);
        if ($result !== false) {
            $after = $this->findSession($id);
            if ($after) {
                do_action('shop_queue_session_updated', $after, $before);
            }
        }
        return $result !== false;
    }

    public function findEntry(int $id): ?array
    {
        global $wpdb;
        $table = QueueMigration::entriesTable();

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id),
            ARRAY_A
        );

        return $row ?: null;
    }

    public function findEntryByExternalRef(string $externalRef): ?array
    {
        global $wpdb;
        $table = QueueMigration::entriesTable();

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE external_ref = %s LIMIT 1", $externalRef),
            ARRAY_A
        );

        return $row ?: null;
    }

    public function findEntryByStripeSession(string $stripeSessionId): ?array
    {
        global $wpdb;
        $table = QueueMigration::entriesTable();

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE stripe_session_id = %s LIMIT 1", $stripeSessionId),
            ARRAY_A
        );

        return $row ?: null;
    }

    public function createEntry(array $data): int
    {
        global $wpdb;
        $table = QueueMigration::entriesTable();

        $detailData = $data['detail_data'] ?? null;
        if (is_array($detailData)) {
            $detailData = wp_json_encode($detailData);
        }

        $sessionId = (int) $data['session_id'];

        // Assign a permanent queue_number that follows the entry through
        // its whole lifecycle (queued → active → completed). The homepage
        // displays this as the "deli ticket" number — entry #5 stays #5
        // whether it's currently up or already done.
        $nextNumber = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(MAX(queue_number), 0) + 1 FROM {$table} WHERE session_id = %d",
            $sessionId
        ));

        $wpdb->insert(
            $table,
            [
                'session_id'        => $sessionId,
                'queue_number'      => $nextNumber,
                'type'              => (string) $data['type'],
                'source'            => (string) $data['source'],
                'status'            => $data['status'] ?? 'queued',
                'discord_user_id'   => $data['discord_user_id'] ?? null,
                'discord_handle'    => $data['discord_handle'] ?? null,
                'customer_email'    => $data['customer_email'] ?? null,
                'order_number'      => $data['order_number'] ?? null,
                'display_name'      => $data['display_name'] ?? null,
                'detail_label'      => $data['detail_label'] ?? null,
                'detail_data'       => $detailData,
                'stripe_session_id' => $data['stripe_session_id'] ?? null,
                'external_ref'      => $data['external_ref'] ?? null,
                'created_at'        => current_time('mysql'),
            ],
            ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        $id = (int) $wpdb->insert_id;
        $row = $this->findEntry($id);
        if ($row) {
            do_action('shop_queue_entry_created', $row);
        }
        return $id;
    }

    public function updateEntry(int $id, array $data): bool
    {
        global $wpdb;
        $table = QueueMigration::entriesTable();

        $update = [];
        $format = [];

        if (isset($data['status'])) {
            $update['status'] = (string) $data['status'];
            $format[] = '%s';

            if ($data['status'] === 'completed' && empty($data['completed_at'])) {
                $update['completed_at'] = current_time('mysql');
                $format[] = '%s';
            }
        }

        foreach (['discord_handle', 'display_name', 'detail_label'] as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = $data[$field];
                $format[] = '%s';
            }
        }

        if (array_key_exists('detail_data', $data)) {
            $detailData = $data['detail_data'];
            if (is_array($detailData)) {
                $detailData = wp_json_encode($detailData);
            }
            $update['detail_data'] = $detailData;
            $format[] = '%s';
        }

        if (empty($update)) {
            return false;
        }

        $before = $this->findEntry($id);
        $result = $wpdb->update($table, $update, ['id' => $id], $format, ['%d']);
        if ($result !== false) {
            $after = $this->findEntry($id);
            if ($after) {
                do_action('shop_queue_entry_updated', $after, $before);
            }
        }
        return $result !== false;
    }

    public const DEFAULT_COMPLETED_LIMIT = 5;

    /**
     * Get a snapshot of the queue for a session: active entry + upcoming
     * queued + recent completed + total. The completed list is bounded
     * (most-recent-first) so the homepage can show a short "already
     * opened on stream" timeline above NOW SERVING for narrative context.
     */
    public function snapshot(
        int $sessionId,
        int $upcomingLimit = self::DEFAULT_UPCOMING_LIMIT,
        int $completedLimit = self::DEFAULT_COMPLETED_LIMIT
    ): array {
        global $wpdb;
        $table = QueueMigration::entriesTable();

        $active = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE session_id = %d AND status = 'active' ORDER BY created_at ASC LIMIT 1",
                $sessionId
            ),
            ARRAY_A
        );

        $upcoming = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE session_id = %d AND status = 'queued' ORDER BY created_at ASC LIMIT %d",
                $sessionId,
                $upcomingLimit
            ),
            ARRAY_A
        );

        $completed = $completedLimit > 0
            ? $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE session_id = %d AND status = 'completed' ORDER BY completed_at DESC LIMIT %d",
                    $sessionId,
                    $completedLimit
                ),
                ARRAY_A
            )
            : [];

        $total = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE session_id = %d AND status IN ('queued', 'active')",
                $sessionId
            )
        );

        return [
            'active'    => $active ?: null,
            'upcoming'  => $upcoming ?: [],
            'completed' => $completed ?: [],
            'total'     => $total,
        ];
    }

    /**
     * Convert a row from the entries table into the public API shape.
     */
    public static function serializeEntry(array $row, ?int $position = null): array
    {
        $detailData = null;
        if (!empty($row['detail_data'])) {
            $decoded = json_decode((string) $row['detail_data'], true);
            $detailData = is_array($decoded) ? $decoded : null;
        }

        $identifierKind = self::identifierKind($row);
        $identifierLabel = self::identifierLabel($row, $identifierKind);

        return [
            'id'          => 'q_' . (int) $row['id'],
            'queueNumber' => isset($row['queue_number']) ? (int) $row['queue_number'] : null,
            'position'    => $position,
            'status'      => (string) $row['status'],
            'type'        => (string) $row['type'],
            'source'      => (string) $row['source'],
            'identifier'  => [
                'kind'  => $identifierKind,
                'label' => $identifierLabel,
            ],
            'detail'      => [
                'label' => $row['detail_label'] !== null ? (string) $row['detail_label'] : null,
                'data'  => $detailData,
            ],
            'createdAt'   => self::toIso8601($row['created_at']),
        ];
    }

    /**
     * Raw camelCase serialization — used by Nous and admin tooling that
     * needs underlying field access (discord_user_id for mentions, etc.).
     * The homepage uses serializeEntry() instead.
     */
    public static function serializeEntryRaw(array $row, ?int $position = null): array
    {
        $detailData = null;
        if (!empty($row['detail_data'])) {
            $decoded = json_decode((string) $row['detail_data'], true);
            $detailData = is_array($decoded) ? $decoded : null;
        }

        return [
            'id'              => (int) $row['id'],
            'sessionId'       => (int) $row['session_id'],
            'queueNumber'     => isset($row['queue_number']) ? (int) $row['queue_number'] : null,
            'position'        => $position,
            'type'            => (string) $row['type'],
            'source'          => (string) $row['source'],
            'status'          => (string) $row['status'],
            'discordUserId'   => $row['discord_user_id'] !== null ? (string) $row['discord_user_id'] : null,
            'discordHandle'   => $row['discord_handle'] !== null ? (string) $row['discord_handle'] : null,
            'customerEmail'   => $row['customer_email'] !== null ? (string) $row['customer_email'] : null,
            'orderNumber'     => $row['order_number'] !== null ? (string) $row['order_number'] : null,
            'displayName'     => $row['display_name'] !== null ? (string) $row['display_name'] : null,
            'detailLabel'     => $row['detail_label'] !== null ? (string) $row['detail_label'] : null,
            'detailData'      => $detailData,
            'stripeSessionId' => $row['stripe_session_id'] !== null ? (string) $row['stripe_session_id'] : null,
            'externalRef'     => $row['external_ref'] !== null ? (string) $row['external_ref'] : null,
            'createdAt'       => self::toIso8601($row['created_at']),
            'completedAt'     => $row['completed_at'] !== null ? self::toIso8601($row['completed_at']) : null,
        ];
    }

    public static function serializeSession(array $row): array
    {
        return [
            'id'                   => (int) $row['id'],
            'status'               => (string) $row['status'],
            'channelMessageId'     => $row['channel_message_id'] !== null ? (string) $row['channel_message_id'] : null,
            'duckRaceWinnerUserId' => $row['duck_race_winner_user_id'] !== null ? (string) $row['duck_race_winner_user_id'] : null,
            'createdAt'            => self::toIso8601($row['created_at']),
            'closedAt'             => $row['closed_at'] !== null ? self::toIso8601($row['closed_at']) : null,
        ];
    }

    /**
     * Get all entries for a session (used by duck race roster).
     * Includes status filter; defaults to all non-skipped.
     */
    public function allEntries(int $sessionId, ?string $status = null): array
    {
        global $wpdb;
        $table = QueueMigration::entriesTable();

        if ($status !== null) {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE session_id = %d AND status = %s ORDER BY created_at ASC",
                    $sessionId,
                    $status
                ),
                ARRAY_A
            );
        } else {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE session_id = %d AND status != 'skipped' ORDER BY created_at ASC",
                    $sessionId
                ),
                ARRAY_A
            );
        }

        return $rows ?: [];
    }

    /**
     * Unique buyers in a session — Discord user ID preferred, falls back to email.
     * Returns array of ['buyer' => identifier] for compatibility with the existing
     * Nous duck race shape.
     */
    public function uniqueBuyers(int $sessionId): array
    {
        global $wpdb;
        $table = QueueMigration::entriesTable();

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DISTINCT COALESCE(discord_user_id, customer_email) AS buyer
                 FROM {$table}
                 WHERE session_id = %d
                   AND status != 'skipped'
                   AND COALESCE(discord_user_id, customer_email) IS NOT NULL
                 ORDER BY MIN(created_at) ASC",
                $sessionId
            ),
            ARRAY_A
        );

        return $rows ?: [];
    }

    private static function identifierKind(array $row): string
    {
        if (!empty($row['discord_handle'])) {
            return 'discord_handle';
        }
        // Numeric Discord IDs are internal — never surface them to the public
        // homepage. Prefer email (redacted), then order number, then display
        // name as the fallback chain.
        if (!empty($row['customer_email'])) {
            return 'customer_email';
        }
        if (!empty($row['order_number'])) {
            return 'order_number';
        }
        return 'display_name';
    }

    private static function identifierLabel(array $row, string $kind): string
    {
        return match ($kind) {
            'discord_handle' => '@' . ltrim((string) $row['discord_handle'], '@'),
            'customer_email' => self::redactEmail((string) $row['customer_email']),
            'order_number'   => 'Order #' . (string) $row['order_number'],
            default          => (string) ($row['display_name'] ?? 'Guest'),
        };
    }

    /**
     * Public-safe email rendering — keep it recognizable to the buyer
     * without leaking the full address to passers-by.
     *   "buyer@example.com" → "b•••@example.com"
     */
    private static function redactEmail(string $email): string
    {
        if (!str_contains($email, '@')) {
            return $email;
        }
        [$local, $domain] = explode('@', $email, 2);
        if ($local === '') {
            return $email;
        }
        return $local[0] . '•••@' . $domain;
    }

    private static function toIso8601(string $mysqlDatetime): string
    {
        // Stored times are in WP timezone (current_time('mysql') returns localized).
        // Convert to UTC ISO 8601 for API consumers.
        $tz = wp_timezone();
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $mysqlDatetime, $tz);
        if (!$dt) {
            return $mysqlDatetime;
        }
        return $dt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }
}
