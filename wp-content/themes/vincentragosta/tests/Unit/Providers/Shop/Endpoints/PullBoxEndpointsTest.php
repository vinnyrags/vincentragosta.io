<?php

namespace ChildTheme\Tests\Unit\Providers\Shop\Endpoints;

use ChildTheme\Providers\Shop\Endpoints\PullBoxActiveEndpoint;
use ChildTheme\Providers\Shop\Endpoints\PullBoxClaimEndpoint;
use ChildTheme\Providers\Shop\Endpoints\PullBoxCreateEndpoint;
use ChildTheme\Providers\Shop\Endpoints\PullBoxUpdateEndpoint;
use Mythus\Support\Rest\Endpoint;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class PullBoxEndpointsTest extends TestCase
{
    /**
     * @dataProvider endpointShapes
     */
    public function testEndpointStructure(string $class, string $route, string|array $methods): void
    {
        $this->assertTrue(is_subclass_of($class, Endpoint::class));

        $reflection = new ReflectionClass($class);
        $endpoint = $reflection->newInstanceWithoutConstructor();

        $this->assertSame($route, $reflection->getMethod('getRoute')->invoke($endpoint));
        $this->assertSame($methods, $reflection->getMethod('getMethods')->invoke($endpoint));
    }

    public function endpointShapes(): array
    {
        return [
            'active' => [PullBoxActiveEndpoint::class, '/pull-boxes/active', 'GET'],
            'create' => [PullBoxCreateEndpoint::class, '/pull-boxes', 'POST'],
            'update' => [PullBoxUpdateEndpoint::class, '/pull-boxes/(?P<id>\d+)', ['PATCH', 'POST']],
            'claim'  => [PullBoxClaimEndpoint::class, '/pull-boxes/(?P<id>\d+)/claim', 'POST'],
        ];
    }

    public function testActiveIsPublic(): void
    {
        $endpoint = (new ReflectionClass(PullBoxActiveEndpoint::class))->newInstanceWithoutConstructor();
        $this->assertTrue($endpoint->getPermission(new \WP_REST_Request('GET', '/shop/v1/pull-boxes/active')));
    }

    public function testWriteEndpointsRequireBotSecret(): void
    {
        $writeEndpoints = [
            PullBoxCreateEndpoint::class,
            PullBoxUpdateEndpoint::class,
            PullBoxClaimEndpoint::class,
        ];

        if (!defined('LIVESTREAM_SECRET')) {
            define('LIVESTREAM_SECRET', 'test-secret-queue');
        }

        foreach ($writeEndpoints as $class) {
            $endpoint = (new ReflectionClass($class))->newInstanceWithoutConstructor();

            $missing = new \WP_REST_Request('POST', '/shop/v1/pull-boxes/x');
            $this->assertFalse($endpoint->getPermission($missing), "$class should reject missing secret");

            $wrong = new \WP_REST_Request('POST', '/shop/v1/pull-boxes/x');
            $wrong->set_header('X-Bot-Secret', 'nope');
            $this->assertFalse($endpoint->getPermission($wrong), "$class should reject wrong secret");

            $right = new \WP_REST_Request('POST', '/shop/v1/pull-boxes/x');
            $right->set_header('X-Bot-Secret', 'test-secret-queue');
            $this->assertTrue($endpoint->getPermission($right), "$class should accept correct secret");
        }
    }

    public function testCreateRequiresNameTierPriceAndTotalSlots(): void
    {
        $endpoint = (new ReflectionClass(PullBoxCreateEndpoint::class))->newInstanceWithoutConstructor();
        $args = (new ReflectionClass(PullBoxCreateEndpoint::class))->getMethod('getArgs')->invoke($endpoint);

        foreach (['name', 'tier', 'price_cents', 'total_slots'] as $required) {
            $this->assertArrayHasKey($required, $args);
            $this->assertTrue($args[$required]['required'], "$required must be a required arg");
        }
    }

    public function testClaimRequiresSlotsArray(): void
    {
        $endpoint = (new ReflectionClass(PullBoxClaimEndpoint::class))->newInstanceWithoutConstructor();
        $args = (new ReflectionClass(PullBoxClaimEndpoint::class))->getMethod('getArgs')->invoke($endpoint);

        $this->assertArrayHasKey('slots', $args);
        $this->assertTrue($args['slots']['required']);
    }
}
