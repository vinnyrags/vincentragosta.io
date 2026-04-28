<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop\Endpoints;

use ChildTheme\Providers\Shop\Services\StripeService;
use ChildTheme\Providers\Shop\ShopProvider;
use ChildTheme\Providers\Shop\Support\PullBoxRepository;
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
        private readonly PullBoxRepository $pullBoxRepository,
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
            // Legacy quantity-only flow for clients that haven't shipped
            // the slot-picker UI yet. Ignored when `slots` is provided.
            'quantity' => [
                'required' => false,
                'type'     => 'integer',
                'default'  => 1,
            ],
            // Slot-based flow: explicit slot numbers the buyer chose in
            // the modal. When present, drives the buy quantity (one per
            // slot) and triggers an atomic pre-claim against the active
            // pull box for this tier.
            'slots' => [
                'required' => false,
            ],
            'customer_email' => [
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_email',
            ],
            // Optional Discord identity — when the bot calls this endpoint
            // for a Discord buyer's slot pre-claim, these populate the
            // slot rows' buyer label immediately so the on-stream embed
            // and the homepage grid render the right handle without
            // waiting for the post-payment webhook.
            'discord_user_id' => [
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'discord_handle' => [
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
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

        // Pull boxes are livestream entry tickets — they only make sense
        // when a queue session is open. Without that, the buyer would
        // pay but have nowhere to land and no slot in the duck race.
        if (!$this->queueRepository->findActiveSession()) {
            return new WP_Error(
                'no_active_queue',
                "Pull boxes are only available during livestream queues. The queue isn't open right now — check back during the next stream.",
                ['status' => 503]
            );
        }

        // Resolve which tier this priceId maps to so we can find the
        // matching active pull box.
        $tier = $this->resolveTierForPriceId($priceId);

        // Slot-based flow: buyer picked specific slot numbers in the
        // homepage modal. We claim them in WP before creating the
        // Stripe session so two buyers can't double-claim the same slot.
        $rawSlots = $request->get_param('slots');
        $slots = $this->parseSlots($rawSlots);

        if (!empty($slots)) {
            if ($tier === null) {
                return new WP_Error('invalid_price_id', 'Could not resolve tier for slot-based purchase.', ['status' => 400]);
            }

            $box = $this->pullBoxRepository->findActiveBox($tier);
            if (!$box) {
                return new WP_Error(
                    'no_active_pull_box',
                    "No pull box for the {$tier} tier is open right now. The next box opens at the start of the stream.",
                    ['status' => 503]
                );
            }

            $stripeSessionPlaceholder = 'wp-pending-' . wp_generate_uuid4();

            $claimResult = $this->pullBoxRepository->claimSlots(
                (int) $box['id'],
                $slots,
                [
                    'customer_email'    => (string) $request->get_param('customer_email') ?: null,
                    'discord_user_id'   => (string) $request->get_param('discord_user_id') ?: null,
                    'discord_handle'    => (string) $request->get_param('discord_handle') ?: null,
                    'stripe_session_id' => $stripeSessionPlaceholder,
                ]
            );

            if ($claimResult === false) {
                return new WP_Error(
                    'slot_conflict',
                    'One or more of the slots you picked were just claimed by someone else. The grid will refresh — please pick again.',
                    [
                        'status'        => 409,
                        'claimedSlots' => $this->pullBoxRepository->getClaimedSlotNumbers((int) $box['id']),
                    ]
                );
            }

            $quantity = count($slots);
            $metadata = [
                'source'              => 'pull_box',
                'price_id'            => $priceId,
                'pull_box_id'         => (string) $box['id'],
                'pull_box_slots'      => implode(',', $slots),
                'wp_session_placeholder' => $stripeSessionPlaceholder,
            ];
        } else {
            // Legacy quantity-only flow — the modal hasn't been shipped
            // for this client. Clamp and proceed without a slot claim.
            $quantity = max(1, min(self::MAX_QUANTITY, (int) $request->get_param('quantity')));
            $metadata = ['source' => 'pull_box', 'price_id' => $priceId];
        }

        $successUrl = ShopProvider::frontendUrl() . '/thank-you?session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl  = ShopProvider::frontendUrl() . '/?cancelled=1';

        // adjustable_quantity stays enabled for the legacy flow so Discord
        // buyers (who hit the same Stripe via !pull) can still tweak.
        // For slot-based purchases the buyer can't change the number on
        // the Stripe page without invalidating their slot claims, so
        // adjustable_quantity is locked to the chosen count.
        $lineItem = [
            'price'    => $priceId,
            'quantity' => $quantity,
        ];
        if (empty($slots)) {
            $lineItem['adjustable_quantity'] = [
                'enabled' => true,
                'minimum' => 1,
                'maximum' => self::MAX_QUANTITY,
            ];
        }

        try {
            $session = $this->stripe->createCheckoutSession(
                [$lineItem],
                $successUrl,
                $cancelUrl,
                $metadata,
                true,
                false,
                null,
                false,
                false,
            );

            // Update the placeholder stripe_session_id on the slot rows
            // so the webhook can confirm them by real session id later.
            if (!empty($slots) && !empty($metadata['wp_session_placeholder'])) {
                $this->updateSlotSessionId(
                    $metadata['wp_session_placeholder'],
                    $session->id
                );
            }

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

    private function resolveTierForPriceId(string $priceId): ?string
    {
        $v    = (string) get_field('pb_v_price_id', 'option');
        $vmax = (string) get_field('pb_vmax_price_id', 'option');
        if ($priceId === $v) return 'v';
        if ($priceId === $vmax) return 'vmax';
        return null;
    }

    /**
     * Parse the slots arg — accepts JSON string or array, returns a
     * de-duped int array. Returns empty array when the param is missing
     * or unparseable so the caller can fall through to the legacy path.
     *
     * @return int[]
     */
    private function parseSlots($raw): array
    {
        if (is_array($raw)) {
            $values = $raw;
        } elseif (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            $values = is_array($decoded) ? $decoded : [];
        } else {
            return [];
        }

        $clean = [];
        foreach ($values as $v) {
            $n = (int) $v;
            if ($n > 0) {
                $clean[$n] = true;
            }
        }
        return array_keys($clean);
    }

    private function updateSlotSessionId(string $placeholder, string $realSessionId): void
    {
        global $wpdb;
        $table = \ChildTheme\Providers\Shop\Hooks\PullBoxMigration::slotsTable();
        $wpdb->update(
            $table,
            ['stripe_session_id' => $realSessionId],
            ['stripe_session_id' => $placeholder],
            ['%s'],
            ['%s']
        );
    }
}
