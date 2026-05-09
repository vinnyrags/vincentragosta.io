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
            'name'               => 'Vintage Pull Box',
            'price_cents'        => 500,
            'stripe_price_id'    => 'price_abc',
            'total_slots'        => 50,
            'status'             => 'open',
            'discord_message_id' => '1234567890',
            'created_at'         => '2026-04-28 09:00:00',
            'closed_at'          => null,
        ];

        $serialized = PullBoxRepository::serializeBox($row, [
            ['slotNumber' => 17, 'claimStatus' => 'confirmed', 'displayLabel' => '@vinnyrags'],
        ]);

        $this->assertSame(7, $serialized['id']);
        $this->assertArrayNotHasKey('tier', $serialized);
        $this->assertSame(500, $serialized['priceCents']);
        $this->assertSame(50, $serialized['totalSlots']);
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

    public function testStatusConstantsAreFrozen(): void
    {
        $this->assertSame(['open', 'closed'], PullBoxRepository::BOX_STATUSES);
        $this->assertSame(['pending', 'confirmed'], PullBoxRepository::CLAIM_STATUSES);
    }

    public function testFindOrCreateActiveBoxAndResetActiveBoxExist(): void
    {
        // Pin the perpetual-single-box surface — both methods are the
        // entry points for the auto-create + chase-reset operator flow.
        // findOrCreateActiveBox is what the homepage slot picker hits
        // (via PullBoxActiveEndpoint) so it MUST NOT return null on a
        // configured environment; resetActiveBox is what /pull reset
        // and the WP admin button call.
        $this->assertTrue(
            method_exists(PullBoxRepository::class, 'findOrCreateActiveBox'),
            'findOrCreateActiveBox is the auto-create entry point for the homepage slot picker'
        );
        $this->assertTrue(
            method_exists(PullBoxRepository::class, 'resetActiveBox'),
            'resetActiveBox is the chase-prize-hit entry point for /pull reset and the WP admin button'
        );
    }

    public function testResetActiveBoxClosesThenCreatesViaSourceShape(): void
    {
        // The reset MUST close the existing box (if any) BEFORE calling
        // findOrCreateActiveBox — otherwise the find half sees the
        // not-yet-closed box and returns it instead of creating a new
        // one. Pin the close-then-create order via source inspection
        // since WorDBless can't simulate the $wpdb update + insert
        // sequence meaningfully.
        $source = file_get_contents(
            __DIR__ . '/../../../../../src/Providers/Shop/Support/PullBoxRepository.php'
        );

        // The reset method must call updateBox with status=closed THEN
        // findOrCreateActiveBox, in that order.
        $resetMatch = preg_match(
            '/function resetActiveBox.*?updateBox.*?status.*?closed.*?findOrCreateActiveBox/s',
            $source
        );
        $this->assertSame(
            1,
            $resetMatch,
            'resetActiveBox must close the existing box before calling findOrCreateActiveBox'
        );
    }

    public function testFindOrCreateBoxFallsBackToFiveDollarsWhenStripeUnreachable(): void
    {
        // When the Stripe price lookup fails (transient API outage),
        // the box still gets created with a sensible default ($5 ==
        // 500 cents) instead of blocking the buyer's slot picker. The
        // atomic checkout still re-validates the live Stripe price, so
        // a stale price_cents here can't cause overselling — it's
        // purely cosmetic for the embed + dollar tile.
        $source = file_get_contents(
            __DIR__ . '/../../../../../src/Providers/Shop/Support/PullBoxRepository.php'
        );
        $this->assertStringContainsString(
            'resolvePriceCentsFromStripe($priceId, 500)',
            $source,
            'Fallback price must be 500 cents ($5) when Stripe is unreachable — anything else and the embed lies about the price during an outage'
        );
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
