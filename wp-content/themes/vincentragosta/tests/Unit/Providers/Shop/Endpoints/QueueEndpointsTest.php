<?php

namespace ChildTheme\Tests\Unit\Providers\Shop\Endpoints;

use ChildTheme\Providers\Shop\Endpoints\QueueEntryCreateEndpoint;
use ChildTheme\Providers\Shop\Endpoints\QueueEntryUpdateEndpoint;
use ChildTheme\Providers\Shop\Endpoints\QueueSessionCreateEndpoint;
use ChildTheme\Providers\Shop\Endpoints\QueueSessionUpdateEndpoint;
use ChildTheme\Providers\Shop\Endpoints\QueueSnapshotEndpoint;
use Mythus\Support\Rest\Endpoint;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class QueueEndpointsTest extends TestCase
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
            'snapshot'        => [QueueSnapshotEndpoint::class, '/queue', 'GET'],
            'session create'  => [QueueSessionCreateEndpoint::class, '/queue/sessions', 'POST'],
            'session update'  => [QueueSessionUpdateEndpoint::class, '/queue/sessions/(?P<id>\d+)', ['PATCH', 'POST']],
            'entry create'    => [QueueEntryCreateEndpoint::class, '/queue/entries', 'POST'],
            'entry update'    => [QueueEntryUpdateEndpoint::class, '/queue/entries/(?P<id>\d+)', ['PATCH', 'POST']],
        ];
    }

    public function testSnapshotEndpointIsPublic(): void
    {
        $reflection = new ReflectionClass(QueueSnapshotEndpoint::class);
        $endpoint = $reflection->newInstanceWithoutConstructor();
        $request = new \WP_REST_Request('GET', '/shop/v1/queue');
        $this->assertTrue($endpoint->getPermission($request));
    }

    public function testWriteEndpointsRequireBotSecret(): void
    {
        $writeEndpoints = [
            QueueSessionCreateEndpoint::class,
            QueueSessionUpdateEndpoint::class,
            QueueEntryCreateEndpoint::class,
            QueueEntryUpdateEndpoint::class,
        ];

        if (!defined('LIVESTREAM_SECRET')) {
            define('LIVESTREAM_SECRET', 'test-secret-queue');
        }

        foreach ($writeEndpoints as $class) {
            $reflection = new ReflectionClass($class);
            $endpoint = $reflection->newInstanceWithoutConstructor();

            $missingHeader = new \WP_REST_Request('POST', '/shop/v1/queue/anything');
            $this->assertFalse($endpoint->getPermission($missingHeader), "$class should reject missing secret");

            $wrongHeader = new \WP_REST_Request('POST', '/shop/v1/queue/anything');
            $wrongHeader->set_header('X-Bot-Secret', 'wrong');
            $this->assertFalse($endpoint->getPermission($wrongHeader), "$class should reject wrong secret");

            $correctHeader = new \WP_REST_Request('POST', '/shop/v1/queue/anything');
            $correctHeader->set_header('X-Bot-Secret', 'test-secret-queue');
            $this->assertTrue($endpoint->getPermission($correctHeader), "$class should accept correct secret");
        }
    }

    public function testEntryCreateRequiresTypeAndSource(): void
    {
        $reflection = new ReflectionClass(QueueEntryCreateEndpoint::class);
        $endpoint = $reflection->newInstanceWithoutConstructor();
        $args = $reflection->getMethod('getArgs')->invoke($endpoint);

        $this->assertArrayHasKey('type', $args);
        $this->assertTrue($args['type']['required']);
        $this->assertArrayHasKey('source', $args);
        $this->assertTrue($args['source']['required']);
        $this->assertArrayHasKey('external_ref', $args);
        $this->assertFalse($args['external_ref']['required']);
    }

    public function testEntryUpdateAcceptsStatusTransition(): void
    {
        $reflection = new ReflectionClass(QueueEntryUpdateEndpoint::class);
        $endpoint = $reflection->newInstanceWithoutConstructor();
        $args = $reflection->getMethod('getArgs')->invoke($endpoint);

        $this->assertArrayHasKey('status', $args);
        $this->assertFalse($args['status']['required']);
    }
}
