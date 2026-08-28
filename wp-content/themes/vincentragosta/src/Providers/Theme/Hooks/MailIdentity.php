<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Theme\Hooks;

use Mythus\Contracts\Hook;

/**
 * Gives site-wide outbound mail a real From address on our own domain.
 *
 * WordPress defaults to `wordpress@<sitename>`, which no relay is
 * authorised to send as. Through the old Gmail relay that address was
 * silently rewritten to the account owner's personal Gmail, so password
 * resets arrived from a stranger — indistinguishable from phishing.
 * Through Resend it must match a verified sending domain or the message
 * is refused outright.
 *
 * IMPORTANT — why this checks the incoming value instead of returning a
 * constant. `wp_mail()` applies the `wp_mail_from` filter *even when the
 * caller passed an explicit `From:` header*, so an unconditional filter
 * would silently clobber every per-send identity on the site. The Shop
 * provider's MailNotifications deliberately sets `From: itzenzoTTV
 * <noreply@itzenzo.tv>` per send so only shop mail wears that identity;
 * overriding it here would undo that on purpose-built code. So we only
 * substitute when the value is still WordPress's own default, and leave
 * anything explicitly set alone.
 *
 * @see \ChildTheme\Providers\Shop\Support\MailNotifications
 */
class MailIdentity implements Hook
{
    /**
     * Must stay in sync with the `from` line in /etc/msmtprc on the
     * droplet and with a verified sending domain in Resend. Changing it
     * in only one of those three places breaks delivery.
     */
    private const FROM_EMAIL = 'noreply@vincentragosta.io';

    private const FROM_NAME = 'Vincent Ragosta';

    /**
     * WordPress's own defaults, which are the only values we replace.
     */
    private const CORE_DEFAULT_NAME = 'WordPress';

    public function register(): void
    {
        add_filter('wp_mail_from', [$this, 'filterFromEmail']);
        add_filter('wp_mail_from_name', [$this, 'filterFromName']);
    }

    /**
     * Replace only WordPress's `wordpress@<sitename>` default, so an
     * explicit per-send `From:` header survives untouched.
     */
    public function filterFromEmail(string $from): string
    {
        return $from === $this->coreDefaultEmail() ? self::FROM_EMAIL : $from;
    }

    /**
     * Same rule for the display name. Note that wp_mail() applies this
     * filter independently of the address one, so a per-send header that
     * set only an address still gets a sensible name.
     */
    public function filterFromName(string $name): string
    {
        return $name === self::CORE_DEFAULT_NAME ? self::FROM_NAME : $name;
    }

    /**
     * Rebuild the address wp_mail() would have used, matching core's
     * logic in wp-includes/pluggable.php: the site host with any leading
     * `www.` stripped.
     */
    private function coreDefaultEmail(): string
    {
        $host = parse_url(network_home_url(), PHP_URL_HOST) ?: '';

        return 'wordpress@' . preg_replace('/^www\./i', '', $host);
    }
}
