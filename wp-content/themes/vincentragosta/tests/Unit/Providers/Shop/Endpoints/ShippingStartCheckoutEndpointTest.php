<?php

namespace ChildTheme\Tests\Unit\Providers\Shop\Endpoints;

use ChildTheme\Providers\Shop\Endpoints\ShippingStartCheckoutEndpoint;
use Mythus\Support\Rest\Endpoint;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Structural tests for ShippingStartCheckoutEndpoint. The runtime
 * branches (Nous availability, response parsing) are exercised end-to-
 * end against staging because WorDBless doesn't simulate wp_remote_post.
 * Pin the contract surface here: route, method, args, public permission,
 * ToS gate, and the forwarding target.
 */
class ShippingStartCheckoutEndpointTest extends TestCase
{
    public function testExtendsEndpoint(): void
    {
        $this->assertTrue(is_subclass_of(ShippingStartCheckoutEndpoint::class, Endpoint::class));
    }

    public function testRouteAndMethod(): void
    {
        $endpoint = (new ReflectionClass(ShippingStartCheckoutEndpoint::class))->newInstanceWithoutConstructor();
        $this->assertSame('/shipping/start-checkout', $endpoint->getRoute());
        $this->assertSame('POST', $endpoint->getMethods());
    }

    public function testPermissionIsPublic(): void
    {
        // Buyers submit anonymously from the homepage / shipping page.
        // No auth gate — the ToS checkbox + email validity are the
        // public-facing contract.
        $endpoint = (new ReflectionClass(ShippingStartCheckoutEndpoint::class))->newInstanceWithoutConstructor();
        $request = $this->createMock(\WP_REST_Request::class);
        $this->assertTrue($endpoint->getPermission($request));
    }

    public function testRequiresEmailArg(): void
    {
        $endpoint = (new ReflectionClass(ShippingStartCheckoutEndpoint::class))->newInstanceWithoutConstructor();
        $args = $endpoint->getArgs();
        $this->assertArrayHasKey('email', $args);
        $this->assertTrue($args['email']['required']);
        $this->assertSame('sanitize_email', $args['email']['sanitize_callback']);
    }

    public function testCallsTouAcceptanceBeforeForwardingToNous(): void
    {
        // ToS validation must happen BEFORE the wp_remote_post — a
        // missing/stale terms_version should 400 without burning a
        // network round-trip to Nous. Source-inspection: TouAcceptance
        // call comes before wp_remote_post.
        $source = file_get_contents(
            __DIR__ . '/../../../../../src/Providers/Shop/Endpoints/ShippingStartCheckoutEndpoint.php'
        );
        $touPos = strpos($source, 'TouAcceptance::validate');
        $postPos = strpos($source, 'wp_remote_post');
        $this->assertNotFalse($touPos);
        $this->assertNotFalse($postPos);
        $this->assertLessThan(
            $postPos,
            $touPos,
            'TouAcceptance::validate must run before forwarding to Nous'
        );
    }

    public function testForwardsToNousShippingStartCheckout(): void
    {
        // Endpoint URL pin — changing the Nous-side path orphans this
        // WP proxy until both sides ship together.
        $source = file_get_contents(
            __DIR__ . '/../../../../../src/Providers/Shop/Endpoints/ShippingStartCheckoutEndpoint.php'
        );
        $this->assertStringContainsString(
            '/shipping/start-checkout',
            $source,
            'Proxy must forward to /shipping/start-checkout on the Nous bot'
        );
    }

    public function testForwardsTosMetadataAlongsideEmail(): void
    {
        // Nous receives the ToS audit fields and attaches them to the
        // Stripe session metadata + PaymentIntent metadata. The proxy
        // is the conduit — must pass tos_metadata in the body.
        $source = file_get_contents(
            __DIR__ . '/../../../../../src/Providers/Shop/Endpoints/ShippingStartCheckoutEndpoint.php'
        );
        $this->assertStringContainsString(
            "'tos_metadata' => \$touMetadata",
            $source,
            'WP proxy must forward the validated ToS audit fields to Nous so the Stripe session carries them'
        );
    }
}
