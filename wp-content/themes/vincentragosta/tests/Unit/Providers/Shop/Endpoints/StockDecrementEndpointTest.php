<?php

namespace ChildTheme\Tests\Unit\Providers\Shop\Endpoints;

use ChildTheme\Providers\Shop\Endpoints\StockDecrementEndpoint;
use Mythus\Support\Rest\Endpoint;
use PHPUnit\Framework\TestCase;

class StockDecrementEndpointTest extends TestCase
{
    public function testExtendsEndpoint(): void
    {
        $this->assertTrue(is_subclass_of(StockDecrementEndpoint::class, Endpoint::class));
    }

    public function testRouteIsDecrementStock(): void
    {
        $reflection = new \ReflectionClass(StockDecrementEndpoint::class);
        $method = $reflection->getMethod('getRoute');
        $endpoint = $reflection->newInstanceWithoutConstructor();
        $this->assertEquals('/decrement-stock', $method->invoke($endpoint));
    }

    public function testMethodIsPost(): void
    {
        $reflection = new \ReflectionClass(StockDecrementEndpoint::class);
        $method = $reflection->getMethod('getMethods');
        $endpoint = $reflection->newInstanceWithoutConstructor();
        $this->assertEquals('POST', $method->invoke($endpoint));
    }

    public function testArgsRequirePriceIdAndSecret(): void
    {
        $reflection = new \ReflectionClass(StockDecrementEndpoint::class);
        $method = $reflection->getMethod('getArgs');
        $endpoint = $reflection->newInstanceWithoutConstructor();
        $args = $method->invoke($endpoint);

        $this->assertArrayHasKey('price_id', $args);
        $this->assertTrue($args['price_id']['required']);
        $this->assertArrayHasKey('secret', $args);
        $this->assertTrue($args['secret']['required']);
        $this->assertArrayHasKey('quantity', $args);
        $this->assertFalse($args['quantity']['required']);
        $this->assertEquals(1, $args['quantity']['default']);
    }
}
