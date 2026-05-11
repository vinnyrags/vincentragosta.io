<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop\Hooks;

use ChildTheme\Providers\Shop\Support\MailNotifications;
use Mythus\Contracts\Hook;

/**
 * Sends a buyer confirmation email when a Request to See is submitted
 * from /cards. Listens to shop_card_request_submitted — an action
 * fired by CardRequestEndpoint alongside the existing queue write,
 * specifically so this notifier (and any future bridges) can attach
 * without polluting the endpoint with email/Discord logic.
 *
 * Mirrors OfferEmailNotifier in shape — both are thin handlers that
 * just hand the action payload to MailNotifications and let the
 * fire-and-forget send happen.
 */
class CardRequestEmailNotifier implements Hook
{
    public function register(): void
    {
        add_action('shop_card_request_submitted', [$this, 'onSubmitted'], 10, 1);
    }

    public function onSubmitted(array $data): void
    {
        MailNotifications::sendCardRequestConfirmation($data);
    }
}
