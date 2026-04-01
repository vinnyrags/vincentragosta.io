<?php

namespace ChildTheme\Tests\Unit\Providers\Shop\Endpoints;

use ChildTheme\Providers\Shop\Endpoints\CancelCheckoutEndpoint;
use Mythus\Support\Rest\Endpoint;
use PHPUnit\Framework\TestCase;

class CancelCheckoutEndpointTest extends TestCase
{
    public function testExtendsEndpoint(): void
    {
        $this->assertTrue(is_subclass_of(CancelCheckoutEndpoint::class, Endpoint::class));
    }

    public function testRouteIsCancelCheckout(): void
    {
        $reflection = new \ReflectionClass(CancelCheckoutEndpoint::class);
        $method = $reflection->getMethod('getRoute');
        $endpoint = $reflection->newInstanceWithoutConstructor();
        $this->assertEquals('/cancel-checkout', $method->invoke($endpoint));
    }

    public function testMethodIsGet(): void
    {
        $reflection = new \ReflectionClass(CancelCheckoutEndpoint::class);
        $method = $reflection->getMethod('getMethods');
        $endpoint = $reflection->newInstanceWithoutConstructor();
        $this->assertEquals('GET', $method->invoke($endpoint));
    }

    public function testPermissionIsPublic(): void
    {
        $reflection = new \ReflectionClass(CancelCheckoutEndpoint::class);
        $method = $reflection->getMethod('getPermission');
        $endpoint = $reflection->newInstanceWithoutConstructor();
        $request = $this->createMock(\WP_REST_Request::class);
        $this->assertTrue($method->invoke($endpoint, $request));
    }

    public function testArgsRequireToken(): void
    {
        $reflection = new \ReflectionClass(CancelCheckoutEndpoint::class);
        $method = $reflection->getMethod('getArgs');
        $endpoint = $reflection->newInstanceWithoutConstructor();
        $args = $method->invoke($endpoint);

        $this->assertArrayHasKey('token', $args);
        $this->assertTrue($args['token']['required']);
    }
}
