<?php

declare(strict_types=1);

namespace ChildTheme\Tests\Integration\Providers\Shop;

use ChildTheme\Providers\Shop\Endpoints\StockDecrementEndpoint;
use ChildTheme\Providers\Shop\ProductPost;
use ChildTheme\Providers\Shop\ProductRepository;
use ChildTheme\Tests\Mocks\MockProductPost;
use ChildTheme\Tests\Support\HasContainer;
use WorDBless\BaseTestCase;
use WP_REST_Request;

/**
 * Integration tests for StockDecrementEndpoint (itzenzoBot stock decrement).
 */
class StockDecrementEndpointTest extends BaseTestCase
{
    use HasContainer;

    protected function setUp(): void
    {
        parent::setUp();

        if (!defined('LIVESTREAM_SECRET')) {
            define('LIVESTREAM_SECRET', 'test-secret-123');
        }
    }

    private function createProduct(string $title, int $stock, string $priceId = 'price_test'): int
    {
        $postId = wp_insert_post([
            'post_title'  => $title,
            'post_type'   => ProductPost::POST_TYPE,
            'post_status' => 'publish',
        ]);

        update_post_meta($postId, 'stock_quantity', $stock);
        update_post_meta($postId, 'stripe_price_id', $priceId);
        update_post_meta($postId, 'stripe_product_id', 'prod_' . $postId);

        return $postId;
    }

    private function mockProduct(int $postId): MockProductPost
    {
        return MockProductPost::create(
            ['ID' => $postId, 'post_title' => get_the_title($postId)],
            [
                'stock_quantity'    => (int) get_post_meta($postId, 'stock_quantity', true),
                'stripe_price_id'  => get_post_meta($postId, 'stripe_price_id', true),
                'stripe_product_id' => get_post_meta($postId, 'stripe_product_id', true),
            ]
        );
    }

    private function buildEndpoint(?MockProductPost $product): StockDecrementEndpoint
    {
        $mockRepo = $this->getMockBuilder(ProductRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findByPriceId'])
            ->getMock();
        $mockRepo->method('findByPriceId')->willReturn($product);

        $container = $this->buildTestContainer([
            ProductRepository::class => $mockRepo,
        ]);
        return $container->get(StockDecrementEndpoint::class);
    }

    private function decrementRequest(string $priceId, string $secret, int $quantity = 1): WP_REST_Request
    {
        $request = new WP_REST_Request('POST', '/shop/v1/decrement-stock');
        $request->set_param('price_id', $priceId);
        $request->set_param('secret', $secret);
        $request->set_param('quantity', $quantity);
        return $request;
    }

    public function testRejectsInvalidSecret(): void
    {
        $endpoint = $this->buildEndpoint(null);
        $result = $endpoint->callback($this->decrementRequest('price_x', 'wrong'));

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('unauthorized', $result->get_error_code());
    }

    public function testRejectsUnknownPriceId(): void
    {
        $endpoint = $this->buildEndpoint(null);
        $result = $endpoint->callback($this->decrementRequest('price_none', LIVESTREAM_SECRET));

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('product_not_found', $result->get_error_code());
    }

    public function testDecrementsStockByOne(): void
    {
        $productId = $this->createProduct('Card', 10, 'price_one');
        $endpoint = $this->buildEndpoint($this->mockProduct($productId));

        $result = $endpoint->callback($this->decrementRequest('price_one', LIVESTREAM_SECRET));

        $this->assertInstanceOf(\WP_REST_Response::class, $result);
        $data = $result->get_data();
        $this->assertEquals(10, $data['old_stock']);
        $this->assertEquals(9, $data['new_stock']);
        $this->assertEquals(9, (int) get_post_meta($productId, 'stock_quantity', true));
    }

    public function testDecrementsStockByCustomQuantity(): void
    {
        $productId = $this->createProduct('Card', 10, 'price_multi');
        $endpoint = $this->buildEndpoint($this->mockProduct($productId));

        $result = $endpoint->callback($this->decrementRequest('price_multi', LIVESTREAM_SECRET, 3));

        $data = $result->get_data();
        $this->assertEquals(10, $data['old_stock']);
        $this->assertEquals(7, $data['new_stock']);
    }

    public function testStockFloorsAtZero(): void
    {
        $productId = $this->createProduct('Almost Gone', 1, 'price_floor');
        $endpoint = $this->buildEndpoint($this->mockProduct($productId));

        $result = $endpoint->callback($this->decrementRequest('price_floor', LIVESTREAM_SECRET, 5));

        $data = $result->get_data();
        $this->assertEquals(0, $data['new_stock']);
        $this->assertEquals(0, (int) get_post_meta($productId, 'stock_quantity', true));
    }

    public function testDecrementsAlreadySoldOutProduct(): void
    {
        $productId = $this->createProduct('Sold Out', 0, 'price_sold');
        $endpoint = $this->buildEndpoint($this->mockProduct($productId));

        $result = $endpoint->callback($this->decrementRequest('price_sold', LIVESTREAM_SECRET));

        $data = $result->get_data();
        $this->assertEquals(0, $data['old_stock']);
        $this->assertEquals(0, $data['new_stock']);
    }

    public function testReturnsProductTitle(): void
    {
        $productId = $this->createProduct('Prismatic Evolutions', 5, 'price_title');
        $endpoint = $this->buildEndpoint($this->mockProduct($productId));

        $result = $endpoint->callback($this->decrementRequest('price_title', LIVESTREAM_SECRET));

        $this->assertEquals('Prismatic Evolutions', $result->get_data()['product']);
    }
}
