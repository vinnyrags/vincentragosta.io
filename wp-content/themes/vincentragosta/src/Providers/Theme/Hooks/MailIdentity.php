<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Theme\Hooks;

use Mythus\Contracts\Hook;
use PHPMailer\PHPMailer\PHPMailer;

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
 * would silently clobber every per-send identity on the site. An explicit
 * address on a domain verified in Resend is left alone, so a sender can
 * still choose its own name and mailbox there.
 *
 * An explicit address on any OTHER domain is replaced: Resend refuses it,
 * so leaving it alone only means the mail is never sent. Plugins do
 * this — Smash Balloon's weekly report sends as the admin email (a Gmail
 * address) and was rejected every week from September 2026. The original
 * address becomes the Reply-To, so answering still reaches it.
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
     * Domains verified in Resend. An explicit From on one of these is kept.
     * Add itzenzo.tv here only after verifying it in Resend.
     */
    private const SENDING_DOMAINS = ['vincentragosta.io'];

    /**
     * WordPress's own defaults, which are the only values we replace.
     */
    private const CORE_DEFAULT_NAME = 'WordPress';

    /**
     * The address replaced on the current send, kept for its Reply-To.
     */
    private ?string $replaced = null;

    public function register(): void
    {
        add_filter('wp_mail_from', [$this, 'filterFromEmail']);
        add_filter('wp_mail_from_name', [$this, 'filterFromName']);
        add_action('phpmailer_init', [$this, 'addReplyTo']);
    }

    /**
     * Replace WordPress's `wordpress@<sitename>` default and any address
     * no relay will accept; keep explicit addresses on our own domains.
     */
    public function filterFromEmail(string $from): string
    {
        $this->replaced = null;

        if ($from === $this->coreDefaultEmail()) {
            return self::FROM_EMAIL;
        }

        if ($this->isSendable($from)) {
            return $from;
        }

        $this->replaced = $from;

        return self::FROM_EMAIL;
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
     * When a foreign From was replaced, replies go back to it — unless the
     * sender already chose a Reply-To.
     */
    public function addReplyTo(PHPMailer $mailer): void
    {
        if ($this->replaced !== null && $mailer->getReplyToAddresses() === []) {
            $mailer->addReplyTo($this->replaced);
        }

        $this->replaced = null;
    }

    private function isSendable(string $email): bool
    {
        $domain = strtolower((string) substr(strrchr($email, '@') ?: '', 1));

        return in_array($domain, self::SENDING_DOMAINS, true);
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
