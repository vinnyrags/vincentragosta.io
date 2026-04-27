<?php

namespace ChildTheme\Tests\Unit\Providers\Shop\Endpoints;

use ChildTheme\Providers\Shop\Endpoints\CurrentPackBattleEndpoint;
use Mythus\Support\Rest\Endpoint;
use PHPUnit\Framework\TestCase;

class CurrentPackBattleEndpointTest extends TestCase
{
    public function testExtendsEndpoint(): void
    {
        $this->assertTrue(is_subclass_of(CurrentPackBattleEndpoint::class, Endpoint::class));
    }

    public function testRouteIsCurrentPackBattle(): void
    {
        $reflection = new \ReflectionClass(CurrentPackBattleEndpoint::class);
        $method = $reflection->getMethod('getRoute');
        $endpoint = $reflection->newInstanceWithoutConstructor();
        $this->assertEquals('/current-pack-battle', $method->invoke($endpoint));
    }

    public function testMethodIsPost(): void
    {
        $reflection = new \ReflectionClass(CurrentPackBattleEndpoint::class);
        $method = $reflection->getMethod('getMethods');
        $endpoint = $reflection->newInstanceWithoutConstructor();
        $this->assertEquals('POST', $method->invoke($endpoint));
    }

    public function testArgsRequireSecretAndStatus(): void
    {
        $reflection = new \ReflectionClass(CurrentPackBattleEndpoint::class);
        $method = $reflection->getMethod('getArgs');
        $endpoint = $reflection->newInstanceWithoutConstructor();
        $args = $method->invoke($endpoint);

        $this->assertArrayHasKey('secret', $args);
        $this->assertTrue($args['secret']['required']);
        $this->assertArrayHasKey('status', $args);
        $this->assertTrue($args['status']['required']);

        foreach (['stripe_price_id', 'battle_id', 'buy_url', 'max_entries', 'paid_entries'] as $optional) {
            $this->assertArrayHasKey($optional, $args);
            $this->assertFalse($args[$optional]['required']);
        }
    }

    public function testAllowedStatusesConstant(): void
    {
        $reflection = new \ReflectionClass(CurrentPackBattleEndpoint::class);
        $constant = $reflection->getConstant('ALLOWED_STATUSES');

        $this->assertSame(['idle', 'open', 'in_progress'], $constant);
    }
}
