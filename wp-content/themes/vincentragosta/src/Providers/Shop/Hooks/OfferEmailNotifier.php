<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop\Hooks;

use ChildTheme\Providers\Shop\Support\MailNotifications;
use Mythus\Contracts\Hook;

/**
 * Sends a buyer confirmation email when a Make-an-Offer form is
 * submitted on /collection. Listens to the shop_card_offer_submitted
 * action that CardOfferEndpoint already fires for the Activity Feed
 * + Nous webhook — adding email parity for buyers who don't use
 * Discord (or just want a paper trail).
 *
 * Non-blocking: MailNotifications swallows send failures internally,
 * so a bouncing email never breaks the offer submission flow. The
 * operator-side Discord DM via Nous continues regardless.
 */
class OfferEmailNotifier implements Hook
{
    public function register(): void
    {
        add_action('shop_card_offer_submitted', [$this, 'onSubmitted'], 10, 1);
    }

    public function onSubmitted(array $data): void
    {
        MailNotifications::sendOfferConfirmation($data);
    }
}
