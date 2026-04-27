<?php

namespace ChildTheme\Tests\Unit\Providers\Shop\Endpoints;

use ChildTheme\Providers\Shop\Endpoints\CreateCheckoutEndpoint;
use Mythus\Support\Rest\Endpoint;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the CreateCheckoutEndpoint.
 */
class CreateCheckoutEndpointTest extends TestCase
{
    public function testExtendsEndpoint(): void
    {
        $this->assertTrue(is_subclass_of(CreateCheckoutEndpoint::class, Endpoint::class));
    }

    public function testRouteIsCheckout(): void
    {
        $reflection = new \ReflectionClass(CreateCheckoutEndpoint::class);
        $method = $reflection->getMethod('getRoute');

        $endpoint = $reflection->newInstanceWithoutConstructor();
        $this->assertEquals('/checkout', $method->invoke($endpoint));
    }

    public function testMethodIsPost(): void
    {
        $reflection = new \ReflectionClass(CreateCheckoutEndpoint::class);
        $method = $reflection->getMethod('getMethods');

        $endpoint = $reflection->newInstanceWithoutConstructor();
        $this->assertEquals('POST', $method->invoke($endpoint));
    }

    public function testPermissionIsPublic(): void
    {
        $reflection = new \ReflectionClass(CreateCheckoutEndpoint::class);
        $method = $reflection->getMethod('getPermission');

        $endpoint = $reflection->newInstanceWithoutConstructor();
        $request = $this->createMock(\WP_REST_Request::class);
        $this->assertTrue($method->invoke($endpoint, $request));
    }

    public function testArgsRequireItems(): void
    {
        $reflection = new \ReflectionClass(CreateCheckoutEndpoint::class);
        $method = $reflection->getMethod('getArgs');

        $endpoint = $reflection->newInstanceWithoutConstructor();
        $args = $method->invoke($endpoint);

        $this->assertArrayHasKey('items', $args);
        $this->assertTrue($args['items']['required']);
    }

    public function testArgsDoNotIncludeShippingFlagsOrCountryHints(): void
    {
        // Shipping coverage, international, country_known, and discord_linked
        // used to be accepted from the cart and passed straight through to
        // Stripe. They're now derived server-side via lookupShipping() so
        // a hostile client can't send shipping_covered=true to skip the
        // shipping charge. The fields must NOT be on the API surface.
        $reflection = new \ReflectionClass(CreateCheckoutEndpoint::class);
        $method = $reflection->getMethod('getArgs');

        $endpoint = $reflection->newInstanceWithoutConstructor();
        $args = $method->invoke($endpoint);

        $this->assertArrayNotHasKey('international', $args);
        $this->assertArrayNotHasKey('shipping_covered', $args);
        $this->assertArrayNotHasKey('country_known', $args);
        $this->assertArrayNotHasKey('discord_linked', $args);
    }
}
