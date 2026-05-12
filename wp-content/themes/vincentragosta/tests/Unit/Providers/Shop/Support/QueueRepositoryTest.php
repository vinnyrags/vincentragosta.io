<?php

namespace ChildTheme\Tests\Unit\Providers\Shop\Support;

use ChildTheme\Providers\Shop\Support\QueueRepository;
use PHPUnit\Framework\TestCase;

class QueueRepositoryTest extends TestCase
{
    public function testSerializeEntryProducesPublicShape(): void
    {
        $row = [
            'id'                => 42,
            'session_id'        => 1,
            'type'              => 'pull_box',
            'source'            => 'discord',
            'status'            => 'queued',
            'discord_user_id'   => '12345',
            'discord_handle'    => 'vinnyrags',
            'customer_email'    => null,
            'order_number'      => null,
            'display_name'      => null,
            'detail_label'      => '$2 tier',
            'detail_data'       => '{"tier":2}',
            'stripe_session_id' => null,
            'external_ref'      => null,
            'created_at'        => '2026-04-27 10:21:03',
            'completed_at'      => null,
        ];

        $serialized = QueueRepository::serializeEntry($row, 3);

        $this->assertSame('q_42', $serialized['id']);
        $this->assertSame(3, $serialized['position']);
        $this->assertSame('queued', $serialized['status']);
        $this->assertSame('pull_box', $serialized['type']);
        $this->assertSame('discord', $serialized['source']);
        $this->assertSame('discord_handle', $serialized['identifier']['kind']);
        $this->assertSame('@vinnyrags', $serialized['identifier']['label']);
        $this->assertSame('$2 tier', $serialized['detail']['label']);
        $this->assertSame(['tier' => 2], $serialized['detail']['data']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $serialized['createdAt']);
    }

    public function testIdentifierFallbackChainPrefersEmailOverInternalDiscordId(): void
    {
        // discord_user_id alone (no handle) — must NOT surface as @<numeric>;
        // fall through to email if available so the public homepage doesn't
        // leak internal Discord snowflakes.
        $emailRow = $this->baseRow([
            'discord_user_id' => '862139045974638612',
            'customer_email'  => 'buyer@example.com',
        ]);
        $emailSerialized = QueueRepository::serializeEntry($emailRow);
        $this->assertSame('customer_email', $emailSerialized['identifier']['kind']);
        $this->assertSame('b•••@example.com', $emailSerialized['identifier']['label']);

        $orderRow = $this->baseRow(['order_number' => '1247']);
        $orderSerialized = QueueRepository::serializeEntry($orderRow);
        $this->assertSame('order_number', $orderSerialized['identifier']['kind']);
        $this->assertSame('Order #1247', $orderSerialized['identifier']['label']);

        $nameRow = $this->baseRow(['display_name' => 'D. Patel']);
        $nameSerialized = QueueRepository::serializeEntry($nameRow);
        $this->assertSame('display_name', $nameSerialized['identifier']['kind']);
        $this->assertSame('D. Patel', $nameSerialized['identifier']['label']);

        $guestRow = $this->baseRow([]);
        $guestSerialized = QueueRepository::serializeEntry($guestRow);
        $this->assertSame('display_name', $guestSerialized['identifier']['kind']);
        $this->assertSame('Guest', $guestSerialized['identifier']['label']);
    }

    public function testDiscordHandleStripsLeadingAtSymbol(): void
    {
        $row = $this->baseRow(['discord_handle' => '@vinnyrags']);
        $serialized = QueueRepository::serializeEntry($row);
        $this->assertSame('@vinnyrags', $serialized['identifier']['label']);
    }

    public function testInvalidJsonInDetailDataYieldsNull(): void
    {
        $row = $this->baseRow(['detail_data' => 'not json']);
        $serialized = QueueRepository::serializeEntry($row);
        $this->assertNull($serialized['detail']['data']);
    }

    public function testSerializeSessionExposesPublicFields(): void
    {
        $row = [
            'id'                       => 7,
            'status'                   => 'open',
            'channel_message_id'       => '1234567890',
            'duck_race_winner_user_id' => '99887766',
            'created_at'               => '2026-04-27 09:00:00',
            'closed_at'                => null,
        ];

        $serialized = QueueRepository::serializeSession($row);

        $this->assertSame(7, $serialized['id']);
        $this->assertSame('open', $serialized['status']);
        $this->assertSame('1234567890', $serialized['channelMessageId']);
        $this->assertSame('99887766', $serialized['duckRaceWinnerUserId']);
        $this->assertNull($serialized['closedAt']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $serialized['createdAt']);
    }

    public function testTypeAndSourceConstantsCoverExpectedValues(): void
    {
        $this->assertSame(['order', 'pack_battle', 'pull_box', 'rts'], QueueRepository::TYPES);
        $this->assertSame(['discord', 'shop'], QueueRepository::SOURCES);
        $this->assertSame(['queued', 'active', 'completed', 'skipped', 'refunded'], QueueRepository::ENTRY_STATUSES);
        $this->assertSame(['open', 'closed', 'racing', 'complete'], QueueRepository::SESSION_STATUSES);
    }

    public function testSerializeIncludesQueueNumberWhenPresent(): void
    {
        $row = $this->baseRow(['queue_number' => 5]);
        $serialized = QueueRepository::serializeEntry($row);
        $this->assertSame(5, $serialized['queueNumber']);

        $rawSerialized = QueueRepository::serializeEntryRaw($row);
        $this->assertSame(5, $rawSerialized['queueNumber']);
    }

    public function testSerializeQueueNumberNullWhenAbsent(): void
    {
        $row = $this->baseRow([]);
        unset($row['queue_number']);
        $serialized = QueueRepository::serializeEntry($row);
        $this->assertNull($serialized['queueNumber']);
    }

    private function baseRow(array $overrides): array
    {
        return array_merge([
            'id'                => 1,
            'session_id'        => 1,
            'queue_number'      => 1,
            'type'              => 'order',
            'source'            => 'shop',
            'status'            => 'queued',
            'discord_user_id'   => null,
            'discord_handle'    => null,
            'customer_email'    => null,
            'order_number'      => null,
            'display_name'      => null,
            'detail_label'      => null,
            'detail_data'       => null,
            'stripe_session_id' => null,
            'external_ref'      => null,
            'created_at'        => '2026-04-27 10:00:00',
            'completed_at'      => null,
        ], $overrides);
    }

    public function testUniqueBuyersSqlGroupsByCustomerEmailWithFallback(): void
    {
        // Duck race roster bug discovered 2026-05-12: queue #1 showed
        // "Duck race roster (1)" when three buyers had purchased. Root
        // cause was an `ORDER BY MIN(created_at)` aggregate without a
        // GROUP BY in uniqueBuyers — strict MySQL modes collapsed the
        // whole result set into one row.
        //
        // Pin the fix via source inspection: WorDBless can't simulate
        // $wpdb->get_results, so this is the cheapest reliable contract
        // assertion. The two failure modes to catch:
        //
        //   1. Missing GROUP BY → broken aggregate, collapses to 1 row.
        //   2. Wrong COALESCE order on the dedup key → buyers with both
        //      a discord_user_id and an email get counted twice (once
        //      per identifier) when they should dedup to one row.
        $source = file_get_contents(
            __DIR__ . '/../../../../../src/Providers/Shop/Support/QueueRepository.php'
        );

        // Dedup key must lead with customer_email — that's the most
        // universal identifier (set on every checkout via Stripe). If
        // it's flipped back to discord_user_id-first, a buyer who
        // RTS-submitted (no user_id) + later checked out (with user_id)
        // gets counted as two distinct duck race entries.
        $this->assertMatchesRegularExpression(
            '/GROUP\s+BY\s+COALESCE\(customer_email,\s*discord_user_id,\s*discord_handle\)/i',
            $source,
            'uniqueBuyers must GROUP BY customer_email as the canonical dedup key'
        );

        // Display column must prefer discord_user_id → discord_handle →
        // customer_email so the embed renders mentions when possible,
        // handles when only the username is known, and falls back to
        // email only when nothing else exists.
        $this->assertMatchesRegularExpression(
            '/COALESCE\(MAX\(discord_user_id\),\s*MAX\(discord_handle\),\s*MAX\(customer_email\)\)/i',
            $source,
            'uniqueBuyers must prefer discord_user_id → discord_handle → customer_email for display'
        );
    }
}
