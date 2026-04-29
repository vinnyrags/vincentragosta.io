<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop\Support;

use ChildTheme\Providers\Shop\Hooks\PullBoxMigration;

/**
 * Encapsulates $wpdb access for pull boxes.
 *
 * Slot claims are atomic via the UNIQUE(pull_box_id, slot_number)
 * constraint on the slots table — a concurrent claim of the same slot
 * triggers a duplicate-key error which surfaces as a return value of
 * false, letting the caller surface a "slot taken" message without a
 * race window. Pending claims older than the TTL are reaped lazily on
 * read so abandoned Stripe checkouts don't permanently lock slots.
 */
class PullBoxRepository
{
    public const TIERS = ['v', 'vmax'];
    public const BOX_STATUSES = ['open', 'closed'];
    public const CLAIM_STATUSES = ['pending', 'confirmed'];

    /**
     * Stale-pending TTL in minutes. After this, an unpaid claim is
     * eligible for sweep so the slot can be claimed by someone else.
     * Matches the Stripe checkout session expiry by default.
     */
    public const PENDING_TTL_MINUTES = 30;

    public function findActiveBox(?string $tier = null): ?array
    {
        global $wpdb;
        $table = PullBoxMigration::boxesTable();

        if ($tier !== null) {
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE status = 'open' AND tier = %s ORDER BY created_at DESC LIMIT 1",
                    $tier
                ),
                ARRAY_A
            );
        } else {
            $row = $wpdb->get_row(
                "SELECT * FROM {$table} WHERE status = 'open' ORDER BY created_at DESC LIMIT 1",
                ARRAY_A
            );
        }

        return $row ?: null;
    }

    public function findBox(int $id): ?array
    {
        global $wpdb;
        $table = PullBoxMigration::boxesTable();

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id),
            ARRAY_A
        );
        return $row ?: null;
    }

    public function createBox(array $data): int
    {
        global $wpdb;
        $table = PullBoxMigration::boxesTable();

        $wpdb->insert(
            $table,
            [
                'name'               => (string) $data['name'],
                'tier'               => (string) $data['tier'],
                'price_cents'        => (int) $data['price_cents'],
                'stripe_price_id'    => $data['stripe_price_id'] ?? null,
                'total_slots'        => (int) $data['total_slots'],
                'status'             => $data['status'] ?? 'open',
                'discord_message_id' => $data['discord_message_id'] ?? null,
                'created_at'         => current_time('mysql'),
            ],
            ['%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s']
        );

        $id = (int) $wpdb->insert_id;
        $row = $this->findBox($id);
        if ($row) {
            do_action('shop_pull_box_created', $row);
        }
        return $id;
    }

    public function updateBox(int $id, array $data): bool
    {
        global $wpdb;
        $table = PullBoxMigration::boxesTable();

        $update = [];
        $format = [];

        if (isset($data['status'])) {
            $update['status'] = (string) $data['status'];
            $format[] = '%s';
            if ($data['status'] === 'closed' && empty($data['closed_at'])) {
                $update['closed_at'] = current_time('mysql');
                $format[] = '%s';
            }
        }
        if (isset($data['total_slots'])) {
            $update['total_slots'] = (int) $data['total_slots'];
            $format[] = '%d';
        }
        if (array_key_exists('discord_message_id', $data)) {
            $update['discord_message_id'] = $data['discord_message_id'];
            $format[] = '%s';
        }

        if (empty($update)) {
            return false;
        }

        $before = $this->findBox($id);
        $result = $wpdb->update($table, $update, ['id' => $id], $format, ['%d']);
        if ($result !== false) {
            $after = $this->findBox($id);
            if ($after) {
                do_action('shop_pull_box_updated', $after, $before);
            }
        }
        return $result !== false;
    }

    /**
     * Atomically claim a set of slots in a box. All-or-nothing — if any
     * slot is taken, no claims are recorded and the function returns
     * false. Pending claims older than the TTL are reaped first so an
     * abandoned checkout doesn't lock slots forever.
     *
     * @param int      $boxId        Pull box id.
     * @param int[]    $slotNumbers  1-based slot numbers to claim.
     * @param array    $buyerInfo    discord_user_id, discord_handle, customer_email, stripe_session_id
     * @return int[]|false  IDs of newly inserted slot rows on success; false on conflict.
     */
    public function claimSlots(int $boxId, array $slotNumbers, array $buyerInfo)
    {
        global $wpdb;
        $slotsTable = PullBoxMigration::slotsTable();
        $boxesTable = PullBoxMigration::boxesTable();

        $box = $this->findBox($boxId);
        if (!$box || $box['status'] !== 'open') {
            return false;
        }

        $totalSlots = (int) $box['total_slots'];
        foreach ($slotNumbers as $n) {
            $n = (int) $n;
            if ($n < 1 || $n > $totalSlots) {
                return false;
            }
        }

        // Sweep stale pending claims for any of the requested slots so an
        // abandoned checkout from a previous buyer doesn't block this one.
        $this->sweepStalePendingClaims($boxId, $slotNumbers);

        $now = current_time('mysql');
        $insertedIds = [];

        $wpdb->query('START TRANSACTION');
        try {
            foreach ($slotNumbers as $n) {
                $result = $wpdb->insert(
                    $slotsTable,
                    [
                        'pull_box_id'       => $boxId,
                        'slot_number'       => (int) $n,
                        'claim_status'      => 'pending',
                        'discord_user_id'   => $buyerInfo['discord_user_id'] ?? null,
                        'discord_handle'    => $buyerInfo['discord_handle'] ?? null,
                        'customer_email'    => $buyerInfo['customer_email'] ?? null,
                        'stripe_session_id' => $buyerInfo['stripe_session_id'] ?? null,
                        'claimed_at'        => $now,
                    ],
                    ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s']
                );
                if ($result === false) {
                    $wpdb->query('ROLLBACK');
                    return false;
                }
                $insertedIds[] = (int) $wpdb->insert_id;
            }
            $wpdb->query('COMMIT');
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');
            return false;
        }

        do_action('shop_pull_box_slots_claimed', $boxId, $slotNumbers, $buyerInfo, $insertedIds);
        return $insertedIds;
    }

    /**
     * Confirm previously-pending slot claims after the Stripe payment
     * has succeeded. Looks up by stripe_session_id so the webhook can
     * upgrade exactly the rows it created at session-create time.
     *
     * Fires `shop_pull_box_slot_claimed` with the box, slot numbers, and
     * buyer info so the Activity Feed bridge can broadcast the claim to
     * the homepage.
     */
    public function confirmClaimsByStripeSession(string $stripeSessionId): int
    {
        global $wpdb;
        $table = PullBoxMigration::slotsTable();

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE stripe_session_id = %s AND claim_status = 'pending'",
                $stripeSessionId
            ),
            ARRAY_A
        );

        if (empty($rows)) {
            return 0;
        }

        $confirmed = (int) $wpdb->update(
            $table,
            [
                'claim_status' => 'confirmed',
                'confirmed_at' => current_time('mysql'),
            ],
            [
                'stripe_session_id' => $stripeSessionId,
                'claim_status'      => 'pending',
            ],
            ['%s', '%s'],
            ['%s', '%s']
        );

        if ($confirmed > 0) {
            $boxId = (int) $rows[0]['pull_box_id'];
            $box = $this->findBox($boxId);
            $slotNumbers = array_map(static fn ($r) => (int) $r['slot_number'], $rows);
            $buyerInfo = [
                'discord_user_id' => $rows[0]['discord_user_id'] ?? null,
                'discord_handle'  => $rows[0]['discord_handle'] ?? null,
                'customer_email'  => $rows[0]['customer_email'] ?? null,
            ];
            do_action('shop_pull_box_slot_claimed', $box, $slotNumbers, $buyerInfo);
        }

        return $confirmed;
    }

    public function releaseClaimsByStripeSession(string $stripeSessionId): int
    {
        global $wpdb;
        $table = PullBoxMigration::slotsTable();
        return (int) $wpdb->delete(
            $table,
            ['stripe_session_id' => $stripeSessionId, 'claim_status' => 'pending'],
            ['%s', '%s']
        );
    }

    /**
     * All claimed slot numbers for a box, including pending claims
     * that haven't been swept yet. Used to render the slot grid.
     */
    public function getClaimedSlotNumbers(int $boxId): array
    {
        global $wpdb;
        $table = PullBoxMigration::slotsTable();
        $rows = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT slot_number FROM {$table} WHERE pull_box_id = %d ORDER BY slot_number ASC",
                $boxId
            )
        );
        return array_map('intval', $rows);
    }

    /**
     * Full slot claim rows for a box — used by the on-stream Discord
     * embed and the homepage modal so we can render the buyer handle
     * inside each claimed slot.
     */
    public function getSlotClaims(int $boxId): array
    {
        global $wpdb;
        $table = PullBoxMigration::slotsTable();
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE pull_box_id = %d ORDER BY slot_number ASC",
                $boxId
            ),
            ARRAY_A
        );
        return $rows ?: [];
    }

    private function sweepStalePendingClaims(int $boxId, array $slotNumbers): void
    {
        global $wpdb;
        $table = PullBoxMigration::slotsTable();
        if (empty($slotNumbers)) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($slotNumbers), '%d'));
        $params = array_merge(
            [$boxId, self::PENDING_TTL_MINUTES],
            array_map('intval', $slotNumbers)
        );
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table}
             WHERE pull_box_id = %d
               AND claim_status = 'pending'
               AND claimed_at < (NOW() - INTERVAL %d MINUTE)
               AND slot_number IN ({$placeholders})",
            ...$params
        ));
    }

    public static function serializeBox(array $row, ?array $claimedSlots = null): array
    {
        return [
            'id'               => (int) $row['id'],
            'name'             => (string) $row['name'],
            'tier'             => (string) $row['tier'],
            'priceCents'       => (int) $row['price_cents'],
            'stripePriceId'    => $row['stripe_price_id'] !== null ? (string) $row['stripe_price_id'] : null,
            'totalSlots'       => (int) $row['total_slots'],
            'status'           => (string) $row['status'],
            'discordMessageId' => $row['discord_message_id'] !== null ? (string) $row['discord_message_id'] : null,
            'claimedSlots'     => $claimedSlots,
            'createdAt'        => self::toIso8601($row['created_at']),
            'closedAt'         => $row['closed_at'] !== null ? self::toIso8601($row['closed_at']) : null,
        ];
    }

    public static function serializeSlotClaim(array $row): array
    {
        return [
            'id'              => (int) $row['id'],
            'pullBoxId'       => (int) $row['pull_box_id'],
            'slotNumber'      => (int) $row['slot_number'],
            'claimStatus'     => (string) $row['claim_status'],
            'discordUserId'   => $row['discord_user_id'] !== null ? (string) $row['discord_user_id'] : null,
            'discordHandle'   => $row['discord_handle'] !== null ? (string) $row['discord_handle'] : null,
            'displayLabel'    => self::buyerLabel($row),
            'claimedAt'       => self::toIso8601($row['claimed_at']),
            'confirmedAt'     => $row['confirmed_at'] !== null ? self::toIso8601($row['confirmed_at']) : null,
        ];
    }

    private static function buyerLabel(array $row): string
    {
        if (!empty($row['discord_handle'])) {
            return '@' . ltrim((string) $row['discord_handle'], '@');
        }
        if (!empty($row['customer_email'])) {
            $email = (string) $row['customer_email'];
            $atPos = strpos($email, '@');
            if ($atPos > 0) {
                return $email[0] . '•••@' . substr($email, $atPos + 1);
            }
            return $email;
        }
        return 'Guest';
    }

    private static function toIso8601(string $mysqlDatetime): string
    {
        $tz = wp_timezone();
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $mysqlDatetime, $tz);
        if (!$dt) {
            return $mysqlDatetime;
        }
        return $dt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }
}
