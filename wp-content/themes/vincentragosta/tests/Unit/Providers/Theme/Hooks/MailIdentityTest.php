<?php

namespace ChildTheme\Tests\Unit\Providers\Theme\Hooks;

use ChildTheme\Providers\Theme\Hooks\MailIdentity;
use Mythus\Contracts\Hook;
use WorDBless\BaseTestCase;

/**
 * The behaviour under test is the guard, not the substitution: wp_mail()
 * applies `wp_mail_from` even when the caller supplied an explicit
 * `From:` header, so an unconditional filter would silently rewrite the
 * per-send identity on a verified domain. These tests pin that — and that
 * an address Resend would refuse is replaced, with a Reply-To.
 */
class MailIdentityTest extends BaseTestCase
{
    private MailIdentity $hook;

    public function setUp(): void
    {
        parent::setUp();
        $this->hook = new MailIdentity();

        // wp_mail() loads WordPress's bundled PHPMailer lazily; the Reply-To tests need it.
        require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
        require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
    }

    public function testImplementsHook(): void
    {
        $this->assertInstanceOf(Hook::class, $this->hook);
    }

    public function testReplacesTheCoreDefaultAddress(): void
    {
        $host = preg_replace('/^www\./i', '', parse_url(network_home_url(), PHP_URL_HOST) ?: '');

        $this->assertSame(
            'noreply@vincentragosta.io',
            $this->hook->filterFromEmail('wordpress@' . $host),
        );
    }

    /**
     * A sender may choose its own mailbox on a verified domain.
     *
     * @dataProvider ownDomainAddresses
     */
    public function testLeavesAnExplicitAddressOnOurDomainsAlone(string $address): void
    {
        $this->assertSame($address, $this->hook->filterFromEmail($address));
    }

    public static function ownDomainAddresses(): array
    {
        return [
            'site identity' => ['noreply@vincentragosta.io'],
            'another mailbox on the site domain' => ['shop@vincentragosta.io'],
            'mixed case domain' => ['inquiries@VincentRagosta.io'],
        ];
    }

    /**
     * Resend refuses these, so keeping them means the mail never sends
     * (Smash Balloon's weekly report sends as the admin Gmail).
     *
     * @dataProvider foreignAddresses
     */
    public function testReplacesAnAddressNoRelayAccepts(string $address): void
    {
        $this->assertSame('noreply@vincentragosta.io', $this->hook->filterFromEmail($address));
    }

    public static function foreignAddresses(): array
    {
        return [
            'admin gmail set by a plugin' => ['vincentpasqualeragosta@gmail.com'],
            'a plugin-set address' => ['forms@example.com'],
            'another site default' => ['wordpress@some-other-host.test'],
            'a lookalike subdomain' => ['noreply@mail.vincentragosta.io.example.com'],
            'itzenzo.tv, not verified in Resend' => ['noreply@itzenzo.tv'],
        ];
    }

    public function testReplacedAddressBecomesTheReplyTo(): void
    {
        $mailer = new \PHPMailer\PHPMailer\PHPMailer();
        $this->hook->filterFromEmail('vincentpasqualeragosta@gmail.com');
        $this->hook->addReplyTo($mailer);

        $this->assertSame(['vincentpasqualeragosta@gmail.com'], array_column($mailer->getReplyToAddresses(), 0));
    }

    public function testAnExistingReplyToIsKept(): void
    {
        $mailer = new \PHPMailer\PHPMailer\PHPMailer();
        $mailer->addReplyTo('person@example.com');
        $this->hook->filterFromEmail('vincentpasqualeragosta@gmail.com');
        $this->hook->addReplyTo($mailer);

        $this->assertSame(['person@example.com'], array_column($mailer->getReplyToAddresses(), 0));
    }

    public function testNoReplyToWhenNothingWasReplaced(): void
    {
        $mailer = new \PHPMailer\PHPMailer\PHPMailer();
        $this->hook->filterFromEmail('noreply@vincentragosta.io');
        $this->hook->addReplyTo($mailer);

        $this->assertSame([], $mailer->getReplyToAddresses());
    }

    public function testReplacesTheCoreDefaultName(): void
    {
        $this->assertSame('Vincent Ragosta', $this->hook->filterFromName('WordPress'));
    }

    public function testLeavesAnExplicitNameAlone(): void
    {
        $this->assertSame('itzenzoTTV', $this->hook->filterFromName('itzenzoTTV'));
    }

    public function testRegistersBothFilters(): void
    {
        $this->hook->register();

        $this->assertNotFalse(has_filter('wp_mail_from', [$this->hook, 'filterFromEmail']));
        $this->assertNotFalse(has_filter('wp_mail_from_name', [$this->hook, 'filterFromName']));
        $this->assertNotFalse(has_action('phpmailer_init', [$this->hook, 'addReplyTo']));
    }
}
