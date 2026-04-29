<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop\Endpoints;

use Mythus\Support\Rest\Endpoint;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Real-time catalog cleanup when a Stripe product becomes inactive
 * (or is deleted). Called by Nous's stripe webhook handler in
 * response to product.updated / product.deleted / price.updated /
 * price.deleted events. Bot-secret auth.
 *
 * Behavior: find every published WP post with the given
 * stripe_product_id in postmeta, set stock_quantity=0, and clear
 * the stale stripe_price_id / stripe_product_id meta. Idempotent —
 * re-running with the same ID is a no-op.
 *
 * Pairs with the pre-flight check in CreateCheckoutEndpoint:
 *   - Pre-flight catches drift at checkout time (synchronous, slow path)
 *   - This endpoint catches drift at Stripe-side change time (async,
 *     real-time, eliminates the time gap that pre-flight has to cover)
 */
class CatalogStripeProductDeactivatedEndpoint extends Endpoint
{
    public function getRoute(): string
    {
        return '/catalog/stripe-product-deactivated';
    }

    public function getMethods(): string
    {
        return 'POST';
    }

    public function getPermission(WP_REST_Request $request): bool
    {
        if (current_user_can('manage_options')) {
            return true;
        }
        $secret = (string) $request->get_header('X-Bot-Secret');
        $expected = defined('LIVESTREAM_SECRET') ? LIVESTREAM_SECRET : '';
        return $expected !== '' && hash_equals($expected, $secret);
    }

    public function getArgs(): array
    {
        return [
            'stripeProductId' => [
                'required' => true,
                'type'     => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ];
    }

    public function callback(WP_REST_Request $request): WP_REST_Response
    {
        $stripeProductId = (string) $request->get_param('stripeProductId');
        if ($stripeProductId === '') {
            return new WP_REST_Response(['matched' => 0, 'updated' => 0]);
        }

        global $wpdb;
        $postIds = $wpdb->get_col($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta}
             WHERE meta_key = %s AND meta_value = %s",
            'stripe_product_id',
            $stripeProductId
        ));

        $matched = count($postIds);
        $updated = 0;

        foreach ($postIds as $postId) {
            $postId = (int) $postId;
            update_post_meta($postId, 'stock_quantity', 0);
            delete_post_meta($postId, 'stripe_price_id');
            delete_post_meta($postId, 'stripe_product_id');
            clean_post_cache($postId);
            $updated++;
        }

        return new WP_REST_Response([
            'matched' => $matched,
            'updated' => $updated,
        ]);
    }
}
