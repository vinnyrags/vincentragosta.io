<?php

namespace ChildTheme\Tests\Unit\Providers\Shop\Endpoints;

use ChildTheme\Providers\Shop\Endpoints\BundleCheckoutEndpoint;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class BundleCheckoutEndpointTest extends TestCase
{
    public function testRouteAndMethod(): void
    {
        $endpoint = (new ReflectionClass(BundleCheckoutEndpoint::class))->newInstanceWithoutConstructor();
        $this->assertSame('/bundle-checkout', $endpoint->getRoute());
        $this->assertSame('POST', $endpoint->getMethods());
    }

    public function testRequiresPriceIdArg(): void
    {
        $endpoint = (new ReflectionClass(BundleCheckoutEndpoint::class))->newInstanceWithoutConstructor();
        $args = $endpoint->getArgs();
        $this->assertArrayHasKey('priceId', $args);
        $this->assertTrue($args['priceId']['required']);
    }
}
