<?php

namespace ChildTheme\Tests\Unit\Providers\Shop\Endpoints;

use ChildTheme\Providers\Shop\Endpoints\LivestreamToggleEndpoint;
use Mythus\Support\Rest\Endpoint;
use PHPUnit\Framework\TestCase;

class LivestreamToggleEndpointTest extends TestCase
{
    public function testExtendsEndpoint(): void
    {
        $this->assertTrue(is_subclass_of(LivestreamToggleEndpoint::class, Endpoint::class));
    }

    public function testRouteIsLivestream(): void
    {
        $reflection = new \ReflectionClass(LivestreamToggleEndpoint::class);
        $method = $reflection->getMethod('getRoute');
        $endpoint = $reflection->newInstanceWithoutConstructor();
        $this->assertEquals('/livestream', $method->invoke($endpoint));
    }

    public function testMethodIsPost(): void
    {
        $reflection = new \ReflectionClass(LivestreamToggleEndpoint::class);
        $method = $reflection->getMethod('getMethods');
        $endpoint = $reflection->newInstanceWithoutConstructor();
        $this->assertEquals('POST', $method->invoke($endpoint));
    }

    public function testArgsRequireActiveAndSecret(): void
    {
        $reflection = new \ReflectionClass(LivestreamToggleEndpoint::class);
        $method = $reflection->getMethod('getArgs');
        $endpoint = $reflection->newInstanceWithoutConstructor();
        $args = $method->invoke($endpoint);

        $this->assertArrayHasKey('active', $args);
        $this->assertTrue($args['active']['required']);
        $this->assertArrayHasKey('secret', $args);
        $this->assertTrue($args['secret']['required']);
    }
}
