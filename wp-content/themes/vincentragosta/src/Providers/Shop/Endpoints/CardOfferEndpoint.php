<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop\Endpoints;

use Mythus\Support\Rest\Endpoint;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Receive a "Make an Offer" submission against a personal-collection card.
 *
 * Personal collection cards (is_personal_collection=true) live on /collection
 * and are not for sale through the standard add-to-cart flow. Buyers who want
 * one badly enough can submit an offer with their email + amount + an optional
 * note; the offer is forwarded to Vinny via Nous (Discord DM, falling back to
 * #ops if the DM fails).
 *
 * Routing is fire-and-forget — Nous outage degrades to "buyer sees a generic
 * success message but the DM doesn't land." If we ever need durable storage,
 * add a wp_card_offers table behind this same endpoint without changing the
 * frontend contract.
 */
class CardOfferEndpoint extends Endpoint
{
    public function getRoute(): string
    {
        return '/card-offer';
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
            'card_id' => [
                'required' => true,
                'type'     => 'integer',
            ],
            'email' => [
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_email',
            ],
            'offer_amount' => [
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'discord_username' => [
                'required'          => false,
                'type'              => 'string',
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'message' => [
                'required'          => false,
                'type'              => 'string',
                'default'           => '',
                'sanitize_callback' => 'sanitize_textarea_field',
            ],
        ];
    }

    public function callback(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $cardId = (int) $request->get_param('card_id');
        // Lowercase + trim email for the same reason CardRequestEndpoint does:
        // every email-keyed lookup downstream is case-sensitive in the bot's
        // SQLite store, and we want one identity per buyer.
        $email = strtolower(trim((string) $request->get_param('email')));
        $offerRaw = trim((string) $request->get_param('offer_amount'));
        $discord = trim((string) $request->get_param('discord_username'));
        $message = trim((string) $request->get_param('message'));

        if ($cardId < 1) {
            return new WP_Error(
                'invalid_card',
                'Missing or invalid card id.',
                ['status' => 400]
            );
        }

        if (!$email || !is_email($email)) {
            return new WP_Error(
                'invalid_email',
                'Enter a valid email so we can reach you about your offer.',
                ['status' => 400]
            );
        }

        $offerAmount = $this->normalizeOfferAmount($offerRaw);
        if ($offerAmount === null) {
            return new WP_Error(
                'invalid_offer',
                'Enter an offer amount, e.g. "$500" or "500".',
                ['status' => 400]
            );
        }

        // Cap optional message length so a runaway form submission can't
        // produce a Discord embed that exceeds Discord's 4096-char field cap.
        if (strlen($message) > 1000) {
            $message = substr($message, 0, 1000);
        }

        $card = get_post($cardId);
        if (!$card || $card->post_type !== 'card' || $card->post_status !== 'publish') {
            return new WP_Error(
                'card_not_found',
                'Card is not available for offers.',
                ['status' => 404]
            );
        }

        $isPersonal = (bool) get_field('is_personal_collection', $card->ID);
        if (!$isPersonal) {
            // Refuse offers on regular catalog cards — those have a real price
            // and an Add to Cart path. Letting offers through on those would
            // confuse the operator (Discord DM landing for a card that the
            // buyer could just have purchased).
            return new WP_Error(
                'not_offerable',
                'This card is part of the standard catalog — add it to your cart instead of making an offer.',
                ['status' => 422]
            );
        }

        // Fire the action FIRST so the Activity Feed bridge can broadcast
        // the event regardless of whether Nous is reachable.
        do_action('shop_card_offer_submitted', [
            'card_id'          => $cardId,
            'card_title'       => $card->post_title,
            'email'            => $email,
            'discord_username' => $discord,
            'offer_amount'     => $offerAmount,
            'offer_raw'        => $offerRaw,
            'message'          => $message,
        ]);

        $this->dispatchToNous([
            'cardId'         => $cardId,
            'cardTitle'      => $card->post_title,
            'cardSlug'       => $card->post_name,
            'cardPermalink'  => get_permalink($card->ID),
            'email'          => $email,
            'discordUsername' => $discord,
            'offerAmount'    => $offerAmount,
            'offerRaw'       => $offerRaw,
            'message'        => $message,
        ]);

        return new WP_REST_Response([
            'status'  => 'received',
            'message' => "Got it — Vinny will reach out at {$email}.",
        ]);
    }

    /**
     * Accept "$500", "500", "500.00", "$1,250" — anything that boils down
     * to a positive number. Returns the formatted "$X.XX" string for
     * downstream display, or null if no usable amount is present.
     */
    private function normalizeOfferAmount(string $raw): ?string
    {
        $stripped = preg_replace('/[^0-9.]/', '', $raw);
        if ($stripped === null || $stripped === '' || $stripped === '.') {
            return null;
        }
        $value = (float) $stripped;
        if ($value <= 0) {
            return null;
        }
        return '$' . number_format($value, 2, '.', ',');
    }

    private function dispatchToNous(array $payload): void
    {
        $endpoint = defined('NOUS_BOT_URL') ? NOUS_BOT_URL : 'http://127.0.0.1:3100';
        $url = rtrim($endpoint, '/') . '/webhooks/card-offer-received';

        $secret = defined('LIVESTREAM_SECRET') ? LIVESTREAM_SECRET : '';
        if ($secret === '') {
            return;
        }

        wp_remote_post($url, [
            'timeout'  => 2,
            'blocking' => false,
            'headers'  => [
                'Content-Type' => 'application/json',
                'X-Bot-Secret' => $secret,
            ],
            'body'     => wp_json_encode([
                'event'     => 'card.offer_received',
                'data'      => $payload,
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            ]),
        ]);
    }
}
