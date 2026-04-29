<?php

declare(strict_types=1);

namespace ChildTheme\Tests\Integration\Providers\Shop;

use ChildTheme\Providers\Shop\Endpoints\CatalogStripeProductDeactivatedEndpoint;
use WorDBless\BaseTestCase;
use WP_REST_Request;

/**
 * Shape + permission tests for the catalog cleanup endpoint.
 *
 * The endpoint's actual DB-touching behavior (postmeta query +
 * update/delete) isn't covered here because WorDBless mocks
 * update_post_meta/get_post_meta in memory and doesn't write to the
 * real wp_postmeta table — direct $wpdb queries against postmeta
 * return zero rows in this test environment. Behavior gets verified
 * end-to-end against production via the Nous webhook smoke path.
 *
 * Same convention as the rest of the Shop endpoint test suite (see
 * StockDecrementEndpointTest, QueueResetEndpointTest).
 */
class CatalogStripeProductDeactivatedEndpointTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!defined('LIVESTREAM_SECRET')) {
            define('LIVESTREAM_SECRET', 'test-secret-catalog');
        }
    }

    private function buildRequest(string $stripeProductId, ?string $secret = null): WP_REST_Request
    {
        $request = new WP_REST_Request('POST', '/shop/v1/catalog/stripe-product-deactivated');
        $request->set_param('stripeProductId', $stripeProductId);
        if ($secret !== null) {
            $request->set_header('X-Bot-Secret', $secret);
        }
        return $request;
    }

    public function testRouteAndMethod(): void
    {
        $endpoint = new CatalogStripeProductDeactivatedEndpoint();
        $this->assertSame('/catalog/stripe-product-deactivated', $endpoint->getRoute());
        $this->assertSame('POST', $endpoint->getMethods());
    }

    public function testRequiresStripeProductIdArg(): void
    {
        $endpoint = new CatalogStripeProductDeactivatedEndpoint();
        $args = $endpoint->getArgs();
        $this->assertArrayHasKey('stripeProductId', $args);
        $this->assertTrue($args['stripeProductId']['required']);
        $this->assertSame('string', $args['stripeProductId']['type']);
    }

    public function testPermissionDeniedWithoutBotSecret(): void
    {
        $endpoint = new CatalogStripeProductDeactivatedEndpoint();
        $request = $this->buildRequest('prod_x');
        $this->assertFalse($endpoint->getPermission($request));
    }

    public function testPermissionDeniedWithWrongSecret(): void
    {
        $endpoint = new CatalogStripeProductDeactivatedEndpoint();
        $request = $this->buildRequest('prod_x', 'wrong-secret');
        $this->assertFalse($endpoint->getPermission($request));
    }

    public function testPermissionGrantedWithCorrectSecret(): void
    {
        $endpoint = new CatalogStripeProductDeactivatedEndpoint();
        $request = $this->buildRequest('prod_x', LIVESTREAM_SECRET);
        $this->assertTrue($endpoint->getPermission($request));
    }

    public function testEmptyStripeProductIdReturnsZeroWithoutQuery(): void
    {
        $endpoint = new CatalogStripeProductDeactivatedEndpoint();
        $response = $endpoint->callback($this->buildRequest(''));

        $data = $response->get_data();
        $this->assertSame(0, $data['matched']);
        $this->assertSame(0, $data['updated']);
    }
}
