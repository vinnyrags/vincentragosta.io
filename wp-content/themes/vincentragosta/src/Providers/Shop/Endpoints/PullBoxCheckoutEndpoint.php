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
 * Creates a Stripe Checkout Session for a pull-box entry.
 *
 * Pull boxes are livestream buy-ins, not catalog SKUs — no WP product, no
 * stock, no shipping line. Winners' cards go in the same weekly shipment as
 * any other order, so we always pass skipShipping=true. Only the Stripe
 * Price IDs configured on the shop-settings options page (V $1 / VMAX $2)
 * are accepted; any other price ID is rejected before we hit Stripe.
 */
class PullBoxCheckoutEndpoint extends Endpoint
{
    public function __construct(
        private readonly StripeService $stripe,
    ) {}

    public function getRoute(): string
    {
        return '/pull-box-checkout';
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
                'required' => true,
                'type'     => 'string',
            ],
        ];
    }

    public function callback(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $priceId = sanitize_text_field((string) $request->get_param('priceId'));

        $allowedPriceIds = $this->allowedPriceIds();

        if (empty($allowedPriceIds)) {
            return new WP_Error(
                'pull_box_unconfigured',
                'Pull box price IDs have not been configured in shop settings.',
                ['status' => 503]
            );
        }

        if (!in_array($priceId, $allowedPriceIds, true)) {
            return new WP_Error(
                'invalid_price_id',
                'Price ID is not a configured pull box.',
                ['status' => 400]
            );
        }

        $successUrl = ShopProvider::frontendUrl() . '/thank-you?session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl  = ShopProvider::frontendUrl() . '/?cancelled=1';

        try {
            $session = $this->stripe->createCheckoutSession(
                [['price' => $priceId, 'quantity' => 1]],
                $successUrl,
                $cancelUrl,
                ['source' => 'pull_box', 'price_id' => $priceId],
                true,
                false,
                null,
                false,
                false,
            );

            return new WP_REST_Response(['url' => $session->url]);
        } catch (\Throwable $e) {
            return new WP_Error(
                'checkout_failed',
                'Failed to create checkout session.',
                ['status' => 500]
            );
        }
    }

    /**
     * @return string[]
     */
    private function allowedPriceIds(): array
    {
        $v    = (string) get_field('pb_v_price_id', 'option');
        $vmax = (string) get_field('pb_vmax_price_id', 'option');

        return array_values(array_filter([$v, $vmax]));
    }
}
