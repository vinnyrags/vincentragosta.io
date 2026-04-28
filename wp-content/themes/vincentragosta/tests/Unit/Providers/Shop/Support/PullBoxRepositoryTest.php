<?php

namespace ChildTheme\Tests\Unit\Providers\Shop\Support;

use ChildTheme\Providers\Shop\Support\PullBoxRepository;
use PHPUnit\Framework\TestCase;

class PullBoxRepositoryTest extends TestCase
{
    public function testSerializeBoxExposesPublicFieldsWithoutInternalIds(): void
    {
        $row = [
            'id'                 => 7,
            'name'               => 'Vintage VMAX Box',
            'tier'               => 'vmax',
            'price_cents'        => 200,
            'stripe_price_id'    => 'price_abc',
            'total_slots'        => 100,
            'status'             => 'open',
            'discord_message_id' => '1234567890',
            'created_at'         => '2026-04-28 09:00:00',
            'closed_at'          => null,
        ];

        $serialized = PullBoxRepository::serializeBox($row, [
            ['slotNumber' => 17, 'claimStatus' => 'confirmed', 'displayLabel' => '@vinnyrags'],
        ]);

        $this->assertSame(7, $serialized['id']);
        $this->assertSame('vmax', $serialized['tier']);
        $this->assertSame(200, $serialized['priceCents']);
        $this->assertSame(100, $serialized['totalSlots']);
        $this->assertSame('open', $serialized['status']);
        $this->assertSame([
            ['slotNumber' => 17, 'claimStatus' => 'confirmed', 'displayLabel' => '@vinnyrags'],
        ], $serialized['claimedSlots']);
        $this->assertNull($serialized['closedAt']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $serialized['createdAt']);
    }

    public function testSerializeSlotClaimRedactsInternalDiscordId(): void
    {
        $row = $this->slotRow([
            'discord_handle'  => null,
            'discord_user_id' => '862139045974638612',
            'customer_email'  => 'buyer@example.com',
        ]);

        // No handle but email → falls through to redacted email
        $serialized = PullBoxRepository::serializeSlotClaim($row);
        $this->assertSame('b•••@example.com', $serialized['displayLabel']);

        // Handle present → preferred over everything else
        $row['discord_handle'] = 'vinnyrags';
        $serialized = PullBoxRepository::serializeSlotClaim($row);
        $this->assertSame('@vinnyrags', $serialized['displayLabel']);
    }

    public function testSlotClaimDisplayLabelFallsBackToGuest(): void
    {
        $row = $this->slotRow([
            'discord_handle'  => null,
            'discord_user_id' => null,
            'customer_email'  => null,
        ]);
        $serialized = PullBoxRepository::serializeSlotClaim($row);
        $this->assertSame('Guest', $serialized['displayLabel']);
    }

    public function testTierAndStatusConstantsAreFrozen(): void
    {
        $this->assertSame(['v', 'vmax'], PullBoxRepository::TIERS);
        $this->assertSame(['open', 'closed'], PullBoxRepository::BOX_STATUSES);
        $this->assertSame(['pending', 'confirmed'], PullBoxRepository::CLAIM_STATUSES);
    }

    private function slotRow(array $overrides): array
    {
        return array_merge([
            'id'                => 1,
            'pull_box_id'       => 7,
            'slot_number'       => 17,
            'claim_status'      => 'pending',
            'discord_user_id'   => null,
            'discord_handle'    => null,
            'customer_email'    => null,
            'stripe_session_id' => null,
            'claimed_at'        => '2026-04-28 09:00:00',
            'confirmed_at'      => null,
        ], $overrides);
    }
}
