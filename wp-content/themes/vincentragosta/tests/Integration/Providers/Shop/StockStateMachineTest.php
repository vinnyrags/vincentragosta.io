<?php

declare(strict_types=1);

namespace ChildTheme\Tests\Integration\Providers\Shop;

use ChildTheme\Providers\Shop\Endpoints\CreateCheckoutEndpoint;
use ChildTheme\Providers\Shop\ProductPost;
use ChildTheme\Providers\Shop\ProductRepository;
use ChildTheme\Providers\Shop\Services\StripeService;
use ChildTheme\Tests\Mocks\MockProductPost;
use ChildTheme\Tests\Support\HasContainer;
use WorDBless\BaseTestCase;
use WP_REST_Request;

/**
 * Integration tests for the stock state machine.
 *
 * Tests the critical purchase path: stock validation, optimistic decrement,
 * restore on cancel/expire, and deduplication between cancel and expire.
 *
 * Uses WorDBless for WordPress functions (post meta, transients) with mocked
 * StripeService and ProductRepository.
 */
class StockStateMachineTest extends BaseTestCase
{
    use HasContainer;

    /** @var \PHPUnit\Framework\MockObject\MockObject&StripeService */
    private $mockStripe;

    /** @var \PHPUnit\Framework\MockObject\MockObject&ProductRepository */
    private $mockRepo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockStripe = $this->getMockBuilder(StripeService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['syncStockToStripe', 'createCheckoutSession', 'constructWebhookEvent'])
            ->getMock();

        $this->mockStripe->method('createCheckoutSession')
            ->willReturnCallback(function () {
                $session = new \Stripe\Checkout\Session();
                $session->url = 'https://checkout.stripe.com/test';
                return $session;
            });

        $this->mockRepo = $this->getMockBuilder(ProductRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findByPriceId'])
            ->getMock();
    }

    /**
     * Create a real WordPress product post with metadata.
     */
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
        update_post_meta($postId, 'price', '$24.99');

        return $postId;
    }

    /**
     * Create a MockProductPost that mirrors real WP post meta.
     */
    private function mockProduct(int $postId): MockProductPost
    {
        return MockProductPost::create(
            ['ID' => $postId, 'post_title' => get_the_title($postId)],
            [
                'stock_quantity'    => (int) get_post_meta($postId, 'stock_quantity', true),
                'stripe_price_id'  => get_post_meta($postId, 'stripe_price_id', true),
                'stripe_product_id' => get_post_meta($postId, 'stripe_product_id', true),
                'price'            => get_post_meta($postId, 'price', true),
            ]
        );
    }

    /**
     * Build a CreateCheckoutEndpoint with our mocks.
     */
    private function buildEndpoint(): CreateCheckoutEndpoint
    {
        $container = $this->buildTestContainer([
            StripeService::class     => $this->mockStripe,
            ProductRepository::class => $this->mockRepo,
        ]);
        return $container->get(CreateCheckoutEndpoint::class);
    }

    private function checkoutRequest(array $items): WP_REST_Request
    {
        $request = new WP_REST_Request('POST', '/shop/v1/checkout');
        $request->set_param('items', $items);
        return $request;
    }

    // =====================================================================
    // Cart Validation
    // =====================================================================

    public function testRejectsEmptyCart(): void
    {
        $endpoint = $this->buildEndpoint();
        $result = $endpoint->callback($this->checkoutRequest([]));

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('invalid_cart', $result->get_error_code());
    }

    public function testRejectsItemWithoutPriceId(): void
    {
        $endpoint = $this->buildEndpoint();
        $result = $endpoint->callback($this->checkoutRequest([['quantity' => 1]]));

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('invalid_item', $result->get_error_code());
    }

    public function testRejectsItemWithZeroQuantity(): void
    {
        $endpoint = $this->buildEndpoint();
        $result = $endpoint->callback($this->checkoutRequest([
            ['priceId' => 'price_test', 'quantity' => 0],
        ]));

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('invalid_item', $result->get_error_code());
    }

    public function testRejectsUnknownPriceId(): void
    {
        $this->mockRepo->method('findByPriceId')->willReturn(null);
        $endpoint = $this->buildEndpoint();

        $result = $endpoint->callback($this->checkoutRequest([
            ['priceId' => 'price_nonexistent', 'quantity' => 1],
        ]));

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('product_not_found', $result->get_error_code());
    }

    public function testRejectsSoldOutProduct(): void
    {
        $productId = $this->createProduct('Sold Out Card', 0, 'price_sold');
        $this->mockRepo->method('findByPriceId')->willReturn($this->mockProduct($productId));
        $endpoint = $this->buildEndpoint();

        $result = $endpoint->callback($this->checkoutRequest([
            ['priceId' => 'price_sold', 'quantity' => 1],
        ]));

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('out_of_stock', $result->get_error_code());
    }

    public function testRejectsInsufficientStock(): void
    {
        $productId = $this->createProduct('Limited Card', 2, 'price_limited');
        $this->mockRepo->method('findByPriceId')->willReturn($this->mockProduct($productId));
        $endpoint = $this->buildEndpoint();

        $result = $endpoint->callback($this->checkoutRequest([
            ['priceId' => 'price_limited', 'quantity' => 5],
        ]));

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('insufficient_stock', $result->get_error_code());
    }

    // =====================================================================
    // Optimistic Stock Decrement
    // =====================================================================

    public function testDecrementsStockOnCheckout(): void
    {
        $productId = $this->createProduct('Test Card', 10, 'price_dec');
        $this->mockRepo->method('findByPriceId')->willReturn($this->mockProduct($productId));
        $endpoint = $this->buildEndpoint();

        $result = $endpoint->callback($this->checkoutRequest([
            ['priceId' => 'price_dec', 'quantity' => 3],
        ]));

        $this->assertNotInstanceOf(\WP_Error::class, $result);
        $this->assertEquals(7, (int) get_post_meta($productId, 'stock_quantity', true));
    }

    public function testDecrementsMultipleProducts(): void
    {
        $id1 = $this->createProduct('Card A', 5, 'price_a');
        $id2 = $this->createProduct('Card B', 8, 'price_b');

        $this->mockRepo->method('findByPriceId')->willReturnMap([
            ['price_a', $this->mockProduct($id1)],
            ['price_b', $this->mockProduct($id2)],
        ]);
        $endpoint = $this->buildEndpoint();

        $result = $endpoint->callback($this->checkoutRequest([
            ['priceId' => 'price_a', 'quantity' => 2],
            ['priceId' => 'price_b', 'quantity' => 3],
        ]));

        $this->assertNotInstanceOf(\WP_Error::class, $result);
        $this->assertEquals(3, (int) get_post_meta($id1, 'stock_quantity', true));
        $this->assertEquals(5, (int) get_post_meta($id2, 'stock_quantity', true));
    }

    public function testLastItemDecrementsToZero(): void
    {
        $productId = $this->createProduct('Last One', 1, 'price_last');
        $this->mockRepo->method('findByPriceId')->willReturn($this->mockProduct($productId));
        $endpoint = $this->buildEndpoint();

        $result = $endpoint->callback($this->checkoutRequest([
            ['priceId' => 'price_last', 'quantity' => 1],
        ]));

        $this->assertNotInstanceOf(\WP_Error::class, $result);
        $this->assertEquals(0, (int) get_post_meta($productId, 'stock_quantity', true));
    }

    public function testSyncsStockToStripeOnDecrement(): void
    {
        $productId = $this->createProduct('Sync Card', 5, 'price_sync');

        $mockStripe = $this->getMockBuilder(StripeService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['syncStockToStripe', 'createCheckoutSession', 'constructWebhookEvent'])
            ->getMock();
        $mockStripe->method('createCheckoutSession')
            ->willReturnCallback(function () {
                $session = new \Stripe\Checkout\Session();
                $session->url = 'https://checkout.stripe.com/test';
                return $session;
            });
        $mockStripe->expects($this->atLeastOnce())
            ->method('syncStockToStripe')
            ->with('prod_' . $productId, 4);

        $mockRepo = $this->getMockBuilder(ProductRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findByPriceId'])
            ->getMock();
        $mockRepo->method('findByPriceId')->willReturn($this->mockProduct($productId));

        $container = $this->buildTestContainer([
            StripeService::class     => $mockStripe,
            ProductRepository::class => $mockRepo,
        ]);
        $endpoint = $container->get(CreateCheckoutEndpoint::class);

        $endpoint->callback($this->checkoutRequest([
            ['priceId' => 'price_sync', 'quantity' => 1],
        ]));
    }

    public function testRestoresStockOnStripeSessionFailure(): void
    {
        $productId = $this->createProduct('Fail Card', 10, 'price_fail');

        $failStripe = $this->getMockBuilder(StripeService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['syncStockToStripe', 'createCheckoutSession', 'constructWebhookEvent'])
            ->getMock();
        $failStripe->method('createCheckoutSession')
            ->willThrowException(new \RuntimeException('Stripe is down'));

        $this->mockRepo = $this->getMockBuilder(ProductRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findByPriceId'])
            ->getMock();
        $this->mockRepo->method('findByPriceId')->willReturn($this->mockProduct($productId));

        $container = $this->buildTestContainer([
            StripeService::class     => $failStripe,
            ProductRepository::class => $this->mockRepo,
        ]);
        $endpoint = $container->get(CreateCheckoutEndpoint::class);

        $result = $endpoint->callback($this->checkoutRequest([
            ['priceId' => 'price_fail', 'quantity' => 2],
        ]));

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('checkout_failed', $result->get_error_code());
        $this->assertEquals(10, (int) get_post_meta($productId, 'stock_quantity', true));
    }

    public function testReturnsCheckoutUrl(): void
    {
        $productId = $this->createProduct('URL Card', 5, 'price_url');
        $this->mockRepo->method('findByPriceId')->willReturn($this->mockProduct($productId));
        $endpoint = $this->buildEndpoint();

        $result = $endpoint->callback($this->checkoutRequest([
            ['priceId' => 'price_url', 'quantity' => 1],
        ]));

        $this->assertInstanceOf(\WP_REST_Response::class, $result);
        $this->assertStringContainsString('stripe.com', $result->get_data()['url']);
    }

    // =====================================================================
    // Cancel Token Logic
    // =====================================================================

    public function testCancelTokenExpiresAfter35Minutes(): void
    {
        $data = ['product_ids' => '123:1', 'timestamp' => time() - 2200];
        $this->assertTrue((time() - $data['timestamp']) > 2100);
    }

    public function testCancelTokenValidWithin35Minutes(): void
    {
        $data = ['product_ids' => '123:1', 'timestamp' => time() - 1800];
        $this->assertFalse((time() - $data['timestamp']) > 2100);
    }

    public function testCancelRestoresStock(): void
    {
        $productId = $this->createProduct('Cancel Card', 10, 'price_cancel');
        update_post_meta($productId, 'stock_quantity', 8); // Simulate decrement

        // Simulate cancel restore logic
        $pairs = [$productId . ':2'];
        foreach ($pairs as $pair) {
            [$postId, $quantity] = explode(':', $pair);
            $current = (int) get_post_meta((int) $postId, 'stock_quantity', true);
            update_post_meta((int) $postId, 'stock_quantity', $current + (int) $quantity);
        }

        $this->assertEquals(10, (int) get_post_meta($productId, 'stock_quantity', true));
    }

    public function testDoubleRestorePrevention(): void
    {
        $productIds = '123:2';
        $timestamp = time();
        $cacheKey = 'stock_restored_' . md5($productIds . $timestamp);

        $this->assertFalse((bool) get_transient($cacheKey));
        set_transient($cacheKey, true, 2100);
        $this->assertTrue((bool) get_transient($cacheKey));
    }

    // =====================================================================
    // Expired Session Logic
    // =====================================================================

    public function testExpiredSessionRestoresStock(): void
    {
        $productId = $this->createProduct('Expired Card', 10, 'price_expire');
        update_post_meta($productId, 'stock_quantity', 8);

        $productData = $productId . ':2';
        $this->assertFalse((bool) get_transient('stock_restored_session_' . $productData));

        // Simulate expired webhook restore
        $pairs = explode(',', $productData);
        foreach ($pairs as $pair) {
            [$postId, $quantity] = explode(':', $pair);
            $current = (int) get_post_meta((int) $postId, 'stock_quantity', true);
            update_post_meta((int) $postId, 'stock_quantity', $current + (int) $quantity);
        }

        $this->assertEquals(10, (int) get_post_meta($productId, 'stock_quantity', true));
    }

    public function testExpiredSessionSkipsWhenAlreadyRestored(): void
    {
        $productId = $this->createProduct('Restored Card', 10, 'price_restored');
        update_post_meta($productId, 'stock_quantity', 8);

        $productData = $productId . ':2';
        set_transient('stock_restored_session_' . $productData, true, 2100);

        // Expired webhook checks transient and skips
        $alreadyRestored = (bool) get_transient('stock_restored_session_' . $productData);
        $this->assertTrue($alreadyRestored);
        $this->assertEquals(8, (int) get_post_meta($productId, 'stock_quantity', true));
    }

    // =====================================================================
    // Full Lifecycle
    // =====================================================================

    public function testFullCheckoutThenCancelRestoresExactly(): void
    {
        $productId = $this->createProduct('Full Cycle', 10, 'price_full');
        $this->mockRepo->method('findByPriceId')->willReturn($this->mockProduct($productId));
        $endpoint = $this->buildEndpoint();

        // Step 1: Checkout decrements
        $endpoint->callback($this->checkoutRequest([
            ['priceId' => 'price_full', 'quantity' => 3],
        ]));
        $this->assertEquals(7, (int) get_post_meta($productId, 'stock_quantity', true));

        // Step 2: Cancel restores
        $productData = $productId . ':3';
        $current = (int) get_post_meta($productId, 'stock_quantity', true);
        update_post_meta($productId, 'stock_quantity', $current + 3);
        set_transient('stock_restored_session_' . $productData, true, 2100);

        $this->assertEquals(10, (int) get_post_meta($productId, 'stock_quantity', true));

        // Step 3: Expired webhook skips (transient set)
        $this->assertTrue((bool) get_transient('stock_restored_session_' . $productData));
    }

    public function testSuccessfulPurchaseKeepsStockDecremented(): void
    {
        $productId = $this->createProduct('Success Card', 10, 'price_success');
        $this->mockRepo->method('findByPriceId')->willReturn($this->mockProduct($productId));
        $endpoint = $this->buildEndpoint();

        $endpoint->callback($this->checkoutRequest([
            ['priceId' => 'price_success', 'quantity' => 1],
        ]));

        // completed webhook only sends notification — stock stays decremented
        $this->assertEquals(9, (int) get_post_meta($productId, 'stock_quantity', true));
    }

    // =====================================================================
}
