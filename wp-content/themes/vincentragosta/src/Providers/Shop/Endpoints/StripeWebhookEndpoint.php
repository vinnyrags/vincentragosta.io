<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop\Endpoints;

use ChildTheme\Providers\Shop\Services\StripeService;
use Mythus\Support\Rest\Endpoint;
use Stripe\Exception\SignatureVerificationException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Receives Stripe webhook events.
 *
 * Stock is decremented optimistically at checkout time (CreateCheckoutEndpoint).
 * This webhook handles:
 * - checkout.session.completed: sends owner notification (stock already decremented)
 * - checkout.session.expired: restores stock for abandoned checkouts
 */
class StripeWebhookEndpoint extends Endpoint
{
    public function __construct(
        private readonly StripeService $stripe,
    ) {}

    public function getRoute(): string
    {
        return '/webhook';
    }

    public function getMethods(): string
    {
        return 'POST';
    }

    /**
     * Public endpoint — Stripe sends webhooks from their servers.
     * Signature verification happens in the callback.
     */
    public function getPermission(WP_REST_Request $request): bool
    {
        return true;
    }

    public function callback(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $payload = $request->get_body();
        $signature = $request->get_header('stripe-signature') ?? '';

        try {
            $event = $this->stripe->constructWebhookEvent($payload, $signature);
        } catch (SignatureVerificationException $e) {
            return new WP_Error(
                'invalid_signature',
                'Webhook signature verification failed.',
                ['status' => 400]
            );
        } catch (\Throwable $e) {
            return new WP_Error(
                'webhook_error',
                'Failed to process webhook.',
                ['status' => 400]
            );
        }

        if ($event->type === 'checkout.session.completed') {
            $this->handleCheckoutCompleted($event->data->object);
        } elseif ($event->type === 'checkout.session.expired') {
            $this->handleCheckoutExpired($event->data->object);
        }

        return new WP_REST_Response(['received' => true]);
    }

    /**
     * Handle a completed checkout session.
     *
     * Stock was already decremented optimistically by CreateCheckoutEndpoint.
     * This just sends the owner notification.
     */
    private function handleCheckoutCompleted(object $session): void
    {
        $productData = $session->metadata->product_ids ?? '';

        if (!$productData) {
            return;
        }

        $pairs = explode(',', $productData);
        $orderLines = [];

        foreach ($pairs as $pair) {
            [$postId, $quantity] = explode(':', $pair);
            $postId = (int) $postId;
            $quantity = (int) $quantity;

            if ($postId < 1 || $quantity < 1) {
                continue;
            }

            $title = get_the_title($postId);
            $price = get_field('price', $postId);
            $currentStock = (int) get_field('stock_quantity', $postId);
            $orderLines[] = "{$quantity}x {$title} ({$price}) — {$currentStock} remaining";
        }

        $this->sendOwnerNotification($session, $orderLines);
    }

    /**
     * Handle an expired/abandoned checkout session by restoring stock.
     *
     * Stock was decremented optimistically at checkout time.
     * If the session expires without payment, restore the quantities.
     */
    private function handleCheckoutExpired(object $session): void
    {
        $productData = $session->metadata->product_ids ?? '';

        if (!$productData) {
            return;
        }

        // Skip if stock was already restored by the cancel endpoint
        if (get_transient('stock_restored_session_' . $productData)) {
            return;
        }

        $pairs = explode(',', $productData);

        foreach ($pairs as $pair) {
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

    /**
     * Send an email notification to the shop owner with order details.
     *
     * @param object $session The Stripe Checkout Session object.
     * @param string[] $orderLines Formatted line items for the email body.
     */
    private function sendOwnerNotification(object $session, array $orderLines): void
    {
        $to = get_option('admin_email');
        $customerEmail = $session->customer_details->email ?? 'Unknown';
        $customerName = $session->customer_details->name ?? 'Unknown';
        $total = number_format(($session->amount_total ?? 0) / 100, 2);

        $shipping = $session->shipping_details->address ?? null;
        $shippingAddress = $shipping
            ? implode(', ', array_filter([
                $shipping->line1 ?? '',
                $shipping->line2 ?? '',
                $shipping->city ?? '',
                $shipping->state ?? '',
                $shipping->postal_code ?? '',
                $shipping->country ?? '',
            ]))
            : 'Not provided';

        $subject = "New Shop Order — \${$total}";

        $body = "New order received!\n\n";
        $body .= "Customer: {$customerName} ({$customerEmail})\n";
        $body .= "Ship to: {$shippingAddress}\n";
        $body .= "Total: \${$total}\n\n";
        $body .= "Items:\n";
        $body .= implode("\n", $orderLines);
        $body .= "\n\nStripe Session: {$session->id}";

        wp_mail($to, $subject, $body);
    }
}
