<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop\Endpoints;

use ChildTheme\Providers\Shop\CardRepository;
use ChildTheme\Providers\Shop\ProductRepository;
use ChildTheme\Providers\Shop\Services\StripeService;
use ChildTheme\Providers\Shop\ShopProvider;
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
        private readonly CardRepository $cardRepository,
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
            'international' => [
                'required' => false,
                'type'     => 'boolean',
                'default'  => false,
            ],
            'email' => [
                'required'          => false,
                'type'              => 'string',
                'default'           => '',
                'sanitize_callback' => 'sanitize_email',
            ],
            'shipping_covered' => [
                'required' => false,
                'type'     => 'boolean',
                'default'  => false,
            ],
            'country_known' => [
                'required' => false,
                'type'     => 'boolean',
                'default'  => true,
            ],
            'discord_linked' => [
                'required' => false,
                'type'     => 'boolean',
                'default'  => false,
            ],
        ];
    }

    public function callback(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $items = $request->get_param('items');

        $isInternational = (bool) $request->get_param('international');
        $countryKnown = (bool) $request->get_param('country_known');
        $customerEmail = $request->get_param('email') ?: null;
        $shippingCovered = (bool) $request->get_param('shipping_covered');
        $discordLinked = (bool) $request->get_param('discord_linked');

        if (!is_array($items) || empty($items)) {
            return new WP_Error(
                'invalid_cart',
                'Cart is empty.',
                ['status' => 400]
            );
        }

        global $wpdb;

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

            $product = $this->repository->findByPriceId($priceId)
                ?? $this->cardRepository->findByPriceId($priceId);

            if (!$product) {
                return new WP_Error(
                    'product_not_found',
                    sprintf('No product found for price ID: %s', $priceId),
                    ['status' => 404]
                );
            }

            // Atomic stock decrement — validates and reserves in one statement.
            // Returns 0 affected rows if insufficient stock, preventing overselling.
            $currentStock = (int) get_post_meta($product->id, 'stock_quantity', true);

            if ($currentStock < $quantity) {
                // Restore any stock already decremented in this request
                $this->restoreStock($productIds);

                if ($currentStock <= 0) {
                    return new WP_Error(
                        'out_of_stock',
                        sprintf('%s is sold out.', $product->title()),
                        ['status' => 409]
                    );
                }

                return new WP_Error(
                    'insufficient_stock',
                    sprintf('Only %d of %s available.', $currentStock, $product->title()),
                    ['status' => 409]
                );
            }

            // Atomic UPDATE — only succeeds if stock hasn't changed underneath us
            $decremented = $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->postmeta}
                 SET meta_value = CAST(meta_value AS SIGNED) - %d
                 WHERE post_id = %d AND meta_key = %s AND CAST(meta_value AS SIGNED) >= %d",
                $quantity,
                $product->id,
                'stock_quantity',
                $quantity,
            ));

            if (!$decremented) {
                $this->restoreStock($productIds);

                return new WP_Error(
                    'out_of_stock',
                    sprintf('%s is sold out.', $product->title()),
                    ['status' => 409]
                );
            }

            // Sync in-memory meta and caches
            $newStock = max(0, $currentStock - $quantity);
            update_post_meta($product->id, 'stock_quantity', $newStock);
            clean_post_cache($product->id);

            // Keep Stripe metadata in sync
            $stripeProductId = $product->stripeProductId();
            if ($stripeProductId) {
                $this->stripe->syncStockToStripe($stripeProductId, $newStock);
            }

            $lineItems[] = [
                'price'    => $priceId,
                'quantity' => $quantity,
            ];

            $productIds[] = $product->id . ':' . $quantity;
        }

        $successUrl = ShopProvider::frontendUrl() . '/thank-you?session_id={CHECKOUT_SESSION_ID}';

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

            $skipShipping = $shippingCovered;

            $session = $this->stripe->createCheckoutSession(
                $lineItems,
                $successUrl,
                $cancelUrl,
                $metadata,
                $skipShipping,
                $isInternational,
                $customerEmail,
                $countryKnown,
                $discordLinked,
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
        global $wpdb;

        foreach ($productIds as $pair) {
            [$postId, $quantity] = explode(':', $pair);
            $postId = (int) $postId;
            $quantity = (int) $quantity;

            if ($postId < 1 || $quantity < 1) {
                continue;
            }

            // Atomic increment in MySQL
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->postmeta}
                 SET meta_value = CAST(meta_value AS SIGNED) + %d
                 WHERE post_id = %d AND meta_key = %s",
                $quantity,
                $postId,
                'stock_quantity',
            ));

            // Sync in-memory meta
            $currentStock = (int) get_post_meta($postId, 'stock_quantity', true);
            update_post_meta($postId, 'stock_quantity', $currentStock + $quantity);
            clean_post_cache($postId);
        }
    }
}
