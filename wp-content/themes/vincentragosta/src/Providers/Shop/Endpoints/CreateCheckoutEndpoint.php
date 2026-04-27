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
        // Only items[] and email are actually consumed. Everything else
        // (international, shipping_covered, country_known, discord_linked)
        // used to be passed through from the cart but is now derived
        // server-side via lookupShipping(). Keeping the API surface small
        // makes it harder to accidentally re-trust those fields later.
        return [
            'items' => [
                'required' => true,
                'type'     => 'array',
            ],
            'email' => [
                'required'          => false,
                'type'              => 'string',
                'default'           => '',
                'sanitize_callback' => 'sanitize_email',
            ],
        ];
    }

    public function callback(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $items = $request->get_param('items');

        $customerEmail = $request->get_param('email') ?: null;

        // Shipping coverage and country must be authoritative — never trusted
        // from the client. A hostile cart could otherwise pass shipping_covered=true
        // to skip the shipping charge or international=false to pay the domestic
        // rate while shipping abroad. We re-derive both server-side from the bot's
        // shipping lookup, falling back to the safest default (charge full domestic
        // shipping) if no email is provided or the bot is unreachable.
        $lookup = $this->lookupShipping($customerEmail);
        $isInternational = $lookup['international'];
        $countryKnown    = $lookup['country_known'];
        $shippingCovered = $lookup['covered'];
        $discordLinked   = $lookup['known'];

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
     * Look up shipping coverage for the buyer via the Nous bot.
     *
     * Returns authoritative server-side answers for whether shipping is
     * already paid for the period, whether the buyer is international, and
     * whether their country is known. Any client-supplied flags are ignored.
     *
     * Falls back to safe defaults (uncovered, domestic, country unknown) when:
     *   - no email is supplied,
     *   - the bot is unreachable,
     *   - the bot returns a malformed response.
     *
     * "Safe" here means the buyer is *charged* shipping — never skipped — so
     * a missing lookup never costs us money.
     *
     * @return array{covered: bool, international: bool, country_known: bool, known: bool}
     */
    private function lookupShipping(?string $email): array
    {
        $defaults = [
            'covered'       => false,
            'international' => false,
            'country_known' => false,
            'known'         => false,
        ];

        if (!$email || !is_email($email)) {
            return $defaults;
        }

        $response = wp_remote_get(
            'http://127.0.0.1:3100/shipping/lookup?' . http_build_query(['email' => $email]),
            ['timeout' => 5]
        );

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return $defaults;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($body)) {
            return $defaults;
        }

        return [
            'covered'       => (bool) ($body['covered']       ?? false),
            'international' => (bool) ($body['international'] ?? false),
            'country_known' => (bool) ($body['countryKnown']  ?? false),
            'known'         => (bool) ($body['known']         ?? false),
        ];
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
