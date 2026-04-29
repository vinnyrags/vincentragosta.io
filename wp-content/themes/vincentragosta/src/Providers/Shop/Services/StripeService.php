<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop\Services;

use Stripe\Checkout\Session;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;

/**
 * Stripe API wrapper service.
 *
 * Injectable via the DI container. Wraps the Stripe PHP SDK
 * for checkout session creation and webhook event verification.
 */
class StripeService
{
    private StripeClient $client;

    public function __construct()
    {
        if (!defined('STRIPE_SECRET_KEY') || STRIPE_SECRET_KEY === '') {
            throw new \RuntimeException(
                'STRIPE_SECRET_KEY constant is not defined. Add it to wp-config.php.'
            );
        }

        $this->client = new StripeClient(STRIPE_SECRET_KEY);
    }

    /**
     * Create a Stripe Checkout Session.
     *
     * @param array<int, array{price: string, quantity: int}> $lineItems
     * @param string $successUrl URL to redirect to after successful payment.
     * @param string $cancelUrl URL to redirect to if checkout is cancelled.
     * @param array<string, string> $metadata Metadata to attach to the session.
     * @param bool $skipShipping If true, no shipping is collected (buyer already covered this period).
     * @param bool $international If true, use international shipping rate and countries.
     */
    public function createCheckoutSession(
        array $lineItems,
        string $successUrl,
        string $cancelUrl,
        array $metadata = [],
        bool $skipShipping = false,
        bool $international = false,
        ?string $customerEmail = null,
        bool $countryKnown = true,
        bool $discordLinked = false,
    ): Session {
        $params = [
            'mode'       => 'payment',
            'expires_at' => time() + 1800, // 30 minutes
            'line_items' => $lineItems,
            'allow_promotion_codes' => true,
            'success_url' => $successUrl,
            'cancel_url'  => $cancelUrl,
            'metadata'    => $metadata,
        ];

        if (!$discordLinked) {
            $params['custom_fields'] = [
                [
                    'key'      => 'discord_username',
                    'label'    => ['type' => 'custom', 'custom' => 'Discord username'],
                    'type'     => 'text',
                    'optional' => true,
                ],
            ];
        }

        if (!$skipShipping) {
            if (!$countryKnown) {
                // Unknown buyer — present both options, buyer self-selects.
                // Webhook auto-flags their country from shipping address for future purchases.
                $params['shipping_address_collection'] = [
                    'allowed_countries' => ['US', 'CA'],
                ];
                $params['shipping_options'] = [
                    [
                        'shipping_rate_data' => [
                            'type'         => 'fixed_amount',
                            'fixed_amount' => ['amount' => 1000, 'currency' => 'usd'],
                            'display_name' => 'Standard Shipping (US)',
                        ],
                    ],
                    [
                        'shipping_rate_data' => [
                            'type'         => 'fixed_amount',
                            'fixed_amount' => ['amount' => 2500, 'currency' => 'usd'],
                            'display_name' => 'International Shipping',
                        ],
                    ],
                ];
            } elseif ($international) {
                $params['shipping_address_collection'] = [
                    'allowed_countries' => ['CA'],
                ];
                $params['shipping_options'] = [
                    [
                        'shipping_rate_data' => [
                            'type'         => 'fixed_amount',
                            'fixed_amount' => ['amount' => 2500, 'currency' => 'usd'],
                            'display_name' => 'International Shipping',
                        ],
                    ],
                ];
            } else {
                $params['shipping_address_collection'] = [
                    'allowed_countries' => ['US'],
                ];
                $params['shipping_options'] = [
                    [
                        'shipping_rate_data' => [
                            'type'         => 'fixed_amount',
                            'fixed_amount' => ['amount' => 1000, 'currency' => 'usd'],
                            'display_name' => 'Standard Shipping (US)',
                        ],
                    ],
                ];
            }
        }

        if ($customerEmail) {
            $params['customer_email'] = $customerEmail;
        }

        return $this->client->checkout->sessions->create($params);
    }

    /**
     * Find the first inactive (or missing) price in a list — used by
     * CreateCheckoutEndpoint as a pre-flight check before decrementing
     * stock or creating a Stripe session. Catching drift here means a
     * buyer with a stale catalog reference gets a friendly "item is
     * no longer available" instead of a generic checkout failure with
     * a useless stock-decrement-then-restore round-trip.
     *
     * Uses expand=product so one API call per price covers both the
     * price.active=false AND price.product.active=false cases (Stripe
     * tracks them separately — a price can appear active even when
     * its product is archived).
     *
     * Returns the offending priceId, or null if every price is active.
     */
    public function findFirstInactivePriceId(array $priceIds): ?string
    {
        foreach ($priceIds as $priceId) {
            if (!is_string($priceId) || $priceId === '') {
                continue;
            }
            try {
                $price = $this->client->prices->retrieve($priceId, ['expand' => ['product']]);
                if (!$price->active) {
                    return $priceId;
                }
                if (isset($price->product->active) && !$price->product->active) {
                    return $priceId;
                }
            } catch (\Stripe\Exception\InvalidRequestException $e) {
                // Price doesn't exist at all — same outcome from the
                // buyer's perspective, the item can't be purchased.
                return $priceId;
            } catch (\Throwable $e) {
                // Network / auth / rate limit — don't mistake those for
                // an inactive price. Let the main createCheckoutSession
                // call surface the real error through the normal catch.
                return null;
            }
        }
        return null;
    }

    /**
     * Sync a product's stock count to Stripe metadata.
     *
     * Keeps Stripe metadata in sync with WordPress so pull-products
     * and any external tools see accurate stock numbers.
     */
    public function syncStockToStripe(string $stripeProductId, int $stock): void
    {
        try {
            $this->client->products->update($stripeProductId, [
                'metadata' => ['stock' => (string) $stock],
            ]);
        } catch (\Throwable $e) {
            error_log("Failed to sync stock to Stripe for {$stripeProductId}: {$e->getMessage()}");
        }
    }

    /**
     * Construct and verify a webhook event from a raw payload.
     *
     * @throws SignatureVerificationException If the signature is invalid.
     */
    public function constructWebhookEvent(string $payload, string $signature): Event
    {
        if (!defined('STRIPE_WEBHOOK_SECRET') || STRIPE_WEBHOOK_SECRET === '') {
            throw new \RuntimeException(
                'STRIPE_WEBHOOK_SECRET constant is not defined. Add it to wp-config.php.'
            );
        }

        return Webhook::constructEvent($payload, $signature, STRIPE_WEBHOOK_SECRET);
    }
}
