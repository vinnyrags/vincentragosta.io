<?php

namespace ChildTheme\Tests\Unit\Providers\Shop\Endpoints;

use ChildTheme\Providers\Shop\Endpoints\StripeWebhookEndpoint;
use Mythus\Support\Rest\Endpoint;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the StripeWebhookEndpoint.
 */
class StripeWebhookEndpointTest extends TestCase
{
    public function testExtendsEndpoint(): void
    {
        $this->assertTrue(is_subclass_of(StripeWebhookEndpoint::class, Endpoint::class));
    }

    public function testRouteIsWebhook(): void
    {
        $reflection = new \ReflectionClass(StripeWebhookEndpoint::class);
        $method = $reflection->getMethod('getRoute');

        $endpoint = $reflection->newInstanceWithoutConstructor();
        $this->assertEquals('/webhook', $method->invoke($endpoint));
    }

    public function testMethodIsPost(): void
    {
        $reflection = new \ReflectionClass(StripeWebhookEndpoint::class);
        $method = $reflection->getMethod('getMethods');

        $endpoint = $reflection->newInstanceWithoutConstructor();
        $this->assertEquals('POST', $method->invoke($endpoint));
    }

    public function testPermissionIsPublic(): void
    {
        $reflection = new \ReflectionClass(StripeWebhookEndpoint::class);
        $method = $reflection->getMethod('getPermission');

        $endpoint = $reflection->newInstanceWithoutConstructor();
        $request = $this->createMock(\WP_REST_Request::class);
        $this->assertTrue($method->invoke($endpoint, $request));
    }
}
