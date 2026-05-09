<?php

namespace ChildTheme\Tests\Unit\Providers\Shop\Endpoints;

use ChildTheme\Providers\Shop\Endpoints\PullBoxResetEndpoint;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class PullBoxResetEndpointTest extends TestCase
{
    public function testRouteAndMethod(): void
    {
        $endpoint = (new ReflectionClass(PullBoxResetEndpoint::class))->newInstanceWithoutConstructor();
        $this->assertSame('/pull-boxes/reset', $endpoint->getRoute());
        $this->assertSame('POST', $endpoint->getMethods());
    }

    public function testNoArgsRequired(): void
    {
        $endpoint = (new ReflectionClass(PullBoxResetEndpoint::class))->newInstanceWithoutConstructor();
        $this->assertSame([], $endpoint->getArgs());
    }
}
