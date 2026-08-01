<?php

namespace ChildTheme\Tests\Unit\Providers\Shop\Endpoints;

use ChildTheme\Providers\Shop\Endpoints\CardOfferEndpoint;
use Mythus\Support\Rest\Endpoint;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Structural tests for CardOfferEndpoint. The runtime branches
 * (validation, personal-collection gate, normalize amount) need ACF
 * which WorDBless doesn't simulate, so the public contract is pinned via
 * reflection + source-shape inspection. Behavior is exercised end-to-end
 * against the live deploy. The Nous outbound webhook is retired — the
 * offer email (OfferEmailNotifier on shop_card_offer_submitted) is the
 * sole notification path.
 */
class CardOfferEndpointTest extends TestCase
{
    public function testExtendsEndpoint(): void
    {
        $this->assertTrue(is_subclass_of(CardOfferEndpoint::class, Endpoint::class));
    }

    public function testRouteIsCardOffer(): void
    {
        $endpoint = (new ReflectionClass(CardOfferEndpoint::class))->newInstanceWithoutConstructor();
        $this->assertSame('/card-offer', $endpoint->getRoute());
    }

    public function testMethodIsPost(): void
    {
        $endpoint = (new ReflectionClass(CardOfferEndpoint::class))->newInstanceWithoutConstructor();
        $this->assertSame('POST', $endpoint->getMethods());
    }

    public function testPermissionIsPublic(): void
    {
        // Buyers submit anonymously from /collection. The auth gate is
        // (a) email validity, (b) card existence, (c) is_personal_collection
        // = true — all in the callback. No role check at the permission layer.
        $endpoint = (new ReflectionClass(CardOfferEndpoint::class))->newInstanceWithoutConstructor();
        $request = $this->createMock(\WP_REST_Request::class);
        $this->assertTrue($endpoint->getPermission($request));
    }

    public function testArgsContractCardIdEmailAmountOptionalFields(): void
    {
        $endpoint = (new ReflectionClass(CardOfferEndpoint::class))->newInstanceWithoutConstructor();
        $args = $endpoint->getArgs();

        $this->assertArrayHasKey('card_id', $args);
        $this->assertTrue($args['card_id']['required']);
        $this->assertSame('integer', $args['card_id']['type']);

        $this->assertArrayHasKey('email', $args);
        $this->assertTrue($args['email']['required']);
        $this->assertSame('sanitize_email', $args['email']['sanitize_callback']);

        $this->assertArrayHasKey('offer_amount', $args);
        $this->assertTrue($args['offer_amount']['required']);
        $this->assertSame('sanitize_text_field', $args['offer_amount']['sanitize_callback']);

        $this->assertArrayHasKey('discord_username', $args);
        $this->assertFalse($args['discord_username']['required']);

        $this->assertArrayHasKey('message', $args);
        $this->assertFalse($args['message']['required']);
        $this->assertSame('sanitize_textarea_field', $args['message']['sanitize_callback']);
    }

    public function testRefusesNonPersonalCollectionCards(): void
    {
        // Pin the most important branch: only is_personal_collection=true
        // cards accept offers. Letting offers through on regular catalog
        // cards would confuse the operator (DM lands for a card the buyer
        // could just have purchased).
        $source = file_get_contents(
            __DIR__ . '/../../../../../src/Providers/Shop/Endpoints/CardOfferEndpoint.php'
        );
        $this->assertStringContainsString(
            "get_field('is_personal_collection', \$card->ID)",
            $source,
            'Endpoint must read is_personal_collection from the card before accepting the offer'
        );
        $this->assertStringContainsString(
            'not_offerable',
            $source,
            'Non-personal cards must return a not_offerable error code'
        );
    }

    public function testFiresShopCardOfferSubmittedAction(): void
    {
        // OfferEmailNotifier subscribes to this action to email the offer
        // to the operator — it's now the sole notification path.
        $source = file_get_contents(
            __DIR__ . '/../../../../../src/Providers/Shop/Endpoints/CardOfferEndpoint.php'
        );
        $this->assertStringContainsString(
            "do_action('shop_card_offer_submitted'",
            $source,
            'Endpoint must fire shop_card_offer_submitted so OfferEmailNotifier can email the offer'
        );
    }

    public function testNousOutboundWebhookIsRetired(): void
    {
        // The Nous outbound webhook is permanently gone. Guard against a
        // regression that reintroduces the dispatch.
        $source = file_get_contents(
            __DIR__ . '/../../../../../src/Providers/Shop/Endpoints/CardOfferEndpoint.php'
        );
        $this->assertStringNotContainsString(
            'dispatchToNous',
            $source,
            'The Nous outbound dispatch is retired and must not return'
        );
        $this->assertStringNotContainsString(
            '/webhooks/card-offer-received',
            $source,
            'The Nous webhook URL is retired and must not return'
        );
    }

    public function testNormalizeOfferAmountAcceptsCommonFormats(): void
    {
        // Pure helper — safe to invoke without WP. Pin the parsing
        // contract so later edits don't accidentally regress on edge
        // cases the form will routinely throw at it.
        $endpoint = (new ReflectionClass(CardOfferEndpoint::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(CardOfferEndpoint::class, 'normalizeOfferAmount');
        $method->setAccessible(true);

        $this->assertSame('$500.00', $method->invoke($endpoint, '500'));
        $this->assertSame('$500.00', $method->invoke($endpoint, '$500'));
        $this->assertSame('$500.00', $method->invoke($endpoint, '$500.00'));
        $this->assertSame('$1,250.00', $method->invoke($endpoint, '$1,250'));
        $this->assertSame('$0.50', $method->invoke($endpoint, '0.50'));

        // Rejected: empty, zero, negative-looking, garbage
        $this->assertNull($method->invoke($endpoint, ''));
        $this->assertNull($method->invoke($endpoint, '0'));
        $this->assertNull($method->invoke($endpoint, 'abc'));
        $this->assertNull($method->invoke($endpoint, '$'));
    }
}
