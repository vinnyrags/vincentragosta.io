<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop\Endpoints;

use ChildTheme\Providers\Shop\ProductRepository;
use ChildTheme\Providers\Shop\Services\StripeService;
use Mythus\Support\Rest\Endpoint;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Creates a Stripe Checkout Session from cart contents.
 *
 * Receives an array of cart items (priceId + quantity), validates them
 * against real WordPress products, and returns a Stripe Checkout URL.
 */
class CreateCheckoutEndpoint extends Endpoint
{
    public function __construct(
        private readonly StripeService $stripe,
        private readonly ProductRepository $repository,
    ) {}

    public function getRoute(): string
    {
        return '/checkout';
    }

    public function getMethods(): string
    {
        return 'POST';
    }

    public function getPermission(WP_REST_Request $request): bool
    {
        return true;
    }

    public function getArgs(): array
    {
        return [
            'items' => [
                'required' => true,
                'type'     => 'array',
            ],
            'live' => [
                'required' => false,
                'type'     => 'boolean',
                'default'  => false,
            ],
            'international' => [
                'required' => false,
                'type'     => 'boolean',
                'default'  => false,
            ],
        ];
    }

    public function callback(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $items = $request->get_param('items');

        // Only honor live=true if the server-side livestream transient is active
        $isLive = (bool) $request->get_param('live') && (bool) get_transient('itzenzo_livestream_active');
        $isInternational = (bool) $request->get_param('international');

        if (!is_array($items) || empty($items)) {
            return new WP_Error(
                'invalid_cart',
                'Cart is empty.',
                ['status' => 400]
            );
        }

        $lineItems = [];
        $productIds = [];

        foreach ($items as $item) {
            $priceId = sanitize_text_field($item['priceId'] ?? '');
            $quantity = (int) ($item['quantity'] ?? 0);

            if (!$priceId || $quantity < 1) {
                return new WP_Error(
                    'invalid_item',
                    'Each item must have a priceId and quantity.',
                    ['status' => 400]
                );
            }

            $product = $this->repository->findByPriceId($priceId);

            if (!$product) {
                return new WP_Error(
                    'product_not_found',
                    sprintf('No product found for price ID: %s', $priceId),
                    ['status' => 404]
                );
            }

            if (!$product->isInStock()) {
                return new WP_Error(
                    'out_of_stock',
                    sprintf('%s is sold out.', $product->title()),
                    ['status' => 409]
                );
            }

            if ($quantity > $product->stockQuantity()) {
                return new WP_Error(
                    'insufficient_stock',
                    sprintf('Only %d of %s available.', $product->stockQuantity(), $product->title()),
                    ['status' => 409]
                );
            }

            $lineItems[] = [
                'price'    => $priceId,
                'quantity' => $quantity,
            ];

            $productIds[] = $product->id . ':' . $quantity;
        }

        // Optimistic stock decrement — reserve stock now, restore on abandoned checkout.
        foreach ($items as $item) {
            $priceId = sanitize_text_field($item['priceId'] ?? '');
            $quantity = (int) ($item['quantity'] ?? 0);
            $product = $this->repository->findByPriceId($priceId);

            if ($product) {
                $currentStock = (int) get_field('stock_quantity', $product->id);
                $newStock = max(0, $currentStock - $quantity);
                update_field('stock_quantity', $newStock, $product->id);

                // Keep Stripe metadata in sync
                $stripeProductId = $product->stripeProductId();
                if ($stripeProductId) {
                    $this->stripe->syncStockToStripe($stripeProductId, $newStock);
                }
            }
        }

        $successUrl = home_url('/shop/thank-you/?session_id={CHECKOUT_SESSION_ID}');

        // Build a cancel token so the cancel endpoint can restore stock immediately
        $cancelToken = base64_encode(json_encode([
            'product_ids' => implode(',', $productIds),
            'timestamp'   => time(),
        ]));
        $cancelUrl = rest_url('shop/v1/cancel-checkout?token=' . urlencode($cancelToken));

        try {
            $metadata = [
                'product_ids' => implode(',', $productIds),
            ];

            if ($isLive) {
                $metadata['live'] = '1';
            }

            $session = $this->stripe->createCheckoutSession(
                $lineItems,
                $successUrl,
                $cancelUrl,
                $metadata,
                $isLive,
                $isInternational,
            );

            return new WP_REST_Response([
                'url' => $session->url,
            ]);
        } catch (\Throwable $e) {
            // Restore stock if session creation failed
            $this->restoreStock($productIds);

            return new WP_Error(
                'checkout_failed',
                'Failed to create checkout session.',
                ['status' => 500]
            );
        }
    }

    /**
     * Restore stock quantities from a product_ids string array.
     *
     * @param string[] $productIds Format: ["123:2", "456:1"]
     */
    private function restoreStock(array $productIds): void
    {
        foreach ($productIds as $pair) {
            [$postId, $quantity] = explode(':', $pair);
            $postId = (int) $postId;
            $quantity = (int) $quantity;

            if ($postId < 1 || $quantity < 1) {
                continue;
            }

            $currentStock = (int) get_field('stock_quantity', $postId);
            update_field('stock_quantity', $currentStock + $quantity, $postId);
        }
    }
}
