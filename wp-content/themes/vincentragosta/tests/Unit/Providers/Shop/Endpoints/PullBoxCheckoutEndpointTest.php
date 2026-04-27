<?php

namespace ChildTheme\Tests\Unit\Providers\Shop\Endpoints;

use ChildTheme\Providers\Shop\Endpoints\PullBoxCheckoutEndpoint;
use Mythus\Support\Rest\Endpoint;
use PHPUnit\Framework\TestCase;

class PullBoxCheckoutEndpointTest extends TestCase
{
    public function testExtendsEndpoint(): void
    {
        $this->assertTrue(is_subclass_of(PullBoxCheckoutEndpoint::class, Endpoint::class));
    }

    public function testRouteIsPullBoxCheckout(): void
    {
        $reflection = new \ReflectionClass(PullBoxCheckoutEndpoint::class);
        $method = $reflection->getMethod('getRoute');
        $endpoint = $reflection->newInstanceWithoutConstructor();
        $this->assertEquals('/pull-box-checkout', $method->invoke($endpoint));
    }

    public function testMethodIsPost(): void
    {
        $reflection = new \ReflectionClass(PullBoxCheckoutEndpoint::class);
        $method = $reflection->getMethod('getMethods');
        $endpoint = $reflection->newInstanceWithoutConstructor();
        $this->assertEquals('POST', $method->invoke($endpoint));
    }

    public function testArgsRequirePriceId(): void
    {
        $reflection = new \ReflectionClass(PullBoxCheckoutEndpoint::class);
        $method = $reflection->getMethod('getArgs');
        $endpoint = $reflection->newInstanceWithoutConstructor();
        $args = $method->invoke($endpoint);

        $this->assertArrayHasKey('priceId', $args);
        $this->assertTrue($args['priceId']['required']);
    }
}
