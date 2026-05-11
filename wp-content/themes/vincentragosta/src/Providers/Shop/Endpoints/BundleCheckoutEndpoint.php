<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop\Endpoints;

use ChildTheme\Providers\Shop\Services\StripeService;
use ChildTheme\Providers\Shop\ShopProvider;
use Mythus\Support\Rest\Endpoint;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Creates a Stripe Checkout Session for the homepage English Bundle widget.
 *
 * The bundle is a one-off content offering — not a regular product CPT
 * entry, not in the catalog, not in the sealed-product grid. State lives
 * in two ACF settings fields: `bundle_stripe_price_id` (which Stripe price
 * to charge) and `bundle_stock` (remaining count).
 *
 * Stock is decremented atomically *before* the Stripe session is created,
 * matching the same race-safety pattern the rest of the shop uses (just
 * keyed on wp_options instead of postmeta). Cancel/expire restores stock
 * via the Stripe webhook handler in Nous.
 */
class BundleCheckoutEndpoint extends Endpoint
{
    public function __construct(private readonly StripeService $stripe)
    {
    }

    public function getRoute(): string
    {
        return '/bundle-checkout';
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
            'priceId' => [
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ];
    }

    public function callback(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $submittedPriceId = (string) $request->get_param('priceId');
        $configuredPriceId = (string) get_field('bundle_stripe_price_id', 'option');

        if ($configuredPriceId === '') {
            return new WP_Error(
                'bundle_unconfigured',
                'Bundle price ID has not been configured in shop settings.',
                ['status' => 503]
            );
        }

        if ($submittedPriceId !== $configuredPriceId) {
            return new WP_Error(
                'invalid_price_id',
                'Price ID is not the configured bundle.',
                ['status' => 400]
            );
        }

        // ACF stores the number field's value in wp_options as a plain
        // numeric string (e.g. "60"). The atomic UPDATE casts to UNSIGNED
        // for the WHERE so two concurrent buyers can't both succeed when
        // only one slot remains — only the first wins, second sees 0
        // affected rows and gets a 409.
        if (!$this->decrementBundleStock()) {
            return new WP_Error(
                'bundle_unavailable',
                'The English Bundle is sold out.',
                ['status' => 409]
            );
        }

        try {
            $successUrl = ShopProvider::frontendUrl() . '/thank-you?session_id={CHECKOUT_SESSION_ID}';
            $cancelUrl  = ShopProvider::frontendUrl() . '/?cancelled=1';

            $session = $this->stripe->createCheckoutSession(
                [['price' => $configuredPriceId, 'quantity' => 1]],
                $successUrl,
                $cancelUrl,
                ['source' => 'bundle', 'price_id' => $configuredPriceId],
                false, // skipShipping — bundle is shipped, ride the same weekly batch as orders
                false,
                null,
                false,
                false,
            );

            // Fire after the Stripe session is in hand so a downstream
            // listener can't see "stock decremented but checkout failed."
            // Listeners decide whether the new stock count is feed-worthy
            // (low or sold out) — the action itself stays general.
            $remaining = (int) get_field('bundle_stock', 'option');
            do_action('shop_bundle_purchased', [
                'remaining' => $remaining,
                'price_id'  => $configuredPriceId,
            ]);

            return new WP_REST_Response(['url' => $session->url]);
        } catch (\Throwable $e) {
            // Roll the stock decrement back so the buyer doesn't see a
            // sold-out widget after a transient Stripe failure.
            $this->incrementBundleStock();
            return new WP_Error(
                'checkout_failed',
                'Failed to create checkout session.',
                ['status' => 500]
            );
        }
    }

    /**
     * Atomic stock decrement against the wp_options row backing the ACF
     * `bundle_stock` number field. Returns true if the decrement
     * succeeded, false if stock was already zero.
     */
    private function decrementBundleStock(): bool
    {
        global $wpdb;
        $optionName = 'options_bundle_stock';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rowsAffected = $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->options}
             SET option_value = CAST(option_value AS UNSIGNED) - 1
             WHERE option_name = %s
               AND CAST(option_value AS UNSIGNED) > 0",
            $optionName
        ));

        // Bust the option cache so subsequent reads see the new value.
        wp_cache_delete($optionName, 'options');
        wp_cache_delete('alloptions', 'options');

        return $rowsAffected === 1;
    }

    /**
     * Restore one slot of stock — used to undo a decrement when Stripe
     * session creation fails after we've already pre-claimed.
     */
    private function incrementBundleStock(): void
    {
        global $wpdb;
        $optionName = 'options_bundle_stock';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->options}
             SET option_value = CAST(option_value AS UNSIGNED) + 1
             WHERE option_name = %s",
            $optionName
        ));

        wp_cache_delete($optionName, 'options');
        wp_cache_delete('alloptions', 'options');
    }
}
