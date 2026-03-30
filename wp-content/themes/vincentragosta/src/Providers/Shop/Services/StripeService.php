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
     */
    public function createCheckoutSession(
        array $lineItems,
        string $successUrl,
        string $cancelUrl,
        array $metadata = [],
    ): Session {
        return $this->client->checkout->sessions->create([
            'mode'                        => 'payment',
            'line_items'                  => $lineItems,
            'success_url'                 => $successUrl,
            'cancel_url'                  => $cancelUrl,
            'metadata'                    => $metadata,
            'shipping_address_collection' => [
                'allowed_countries' => ['US'],
            ],
            'shipping_options' => [
                [
                    'shipping_rate_data' => [
                        'type'         => 'fixed_amount',
                        'fixed_amount' => [
                            'amount'   => 1000,
                            'currency' => 'usd',
                        ],
                        'display_name' => 'Standard Shipping',
                    ],
                ],
            ],
            'custom_fields' => [
                [
                    'key'      => 'discord_username',
                    'label'    => ['type' => 'custom', 'custom' => 'Discord username for role upgrades (optional)'],
                    'type'     => 'text',
                    'optional' => true,
                ],
            ],
        ]);
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
