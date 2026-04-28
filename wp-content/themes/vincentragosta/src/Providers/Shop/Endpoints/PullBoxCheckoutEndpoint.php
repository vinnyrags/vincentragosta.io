<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop\Endpoints;

use ChildTheme\Providers\Shop\Services\StripeService;
use ChildTheme\Providers\Shop\ShopProvider;
use ChildTheme\Providers\Shop\Support\QueueRepository;
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
        private readonly QueueRepository $queueRepository,
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

    /** Server-side ceiling on a single pull-box checkout. Mirrors Discord. */
    private const MAX_QUANTITY = 20;

    public function getArgs(): array
    {
        return [
            'priceId' => [
                'required' => true,
                'type'     => 'string',
            ],
            'quantity' => [
                'required' => false,
                'type'     => 'integer',
                'default'  => 1,
            ],
        ];
    }

    public function callback(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $priceId = sanitize_text_field((string) $request->get_param('priceId'));
        // Clamp to [1, MAX_QUANTITY] regardless of what the client sends —
        // the modal already enforces this client-side, but the server must
        // not trust it.
        $quantity = max(1, min(self::MAX_QUANTITY, (int) $request->get_param('quantity')));

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

        // Pull boxes are livestream entry tickets — they only make sense
        // when a queue session is open. Without that, the buyer would
        // pay but have nowhere to land and no slot in the duck race.
        // Refuse the checkout up front with a message the frontend
        // surfaces verbatim.
        if (!$this->queueRepository->findActiveSession()) {
            return new WP_Error(
                'no_active_queue',
                "Pull boxes are only available during livestream queues. The queue isn't open right now — check back during the next stream.",
                ['status' => 503]
            );
        }

        $successUrl = ShopProvider::frontendUrl() . '/thank-you?session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl  = ShopProvider::frontendUrl() . '/?cancelled=1';

        // adjustable_quantity lets the buyer tweak +/- on the Stripe page
        // too — same as Discord's pull-box flow does it. Belt and
        // suspenders with the modal stepper on the homepage.
        $lineItem = [
            'price'    => $priceId,
            'quantity' => $quantity,
            'adjustable_quantity' => [
                'enabled' => true,
                'minimum' => 1,
                'maximum' => self::MAX_QUANTITY,
            ],
        ];

        try {
            $session = $this->stripe->createCheckoutSession(
                [$lineItem],
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
