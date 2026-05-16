<?php

namespace ChildTheme\Tests\Unit\Providers\Shop\Support;

use ChildTheme\Providers\Shop\Support\MailNotifications;
use PHPUnit\Framework\TestCase;

/**
 * Tests for MailNotifications. The helper calls wp_mail() internally,
 * which in the test environment is intercepted by the pre_wp_mail
 * filter pattern (see ChildTheme test bootstrap). We capture the
 * arguments and assert the wire shape — subject, body markers, the
 * per-send From header — without actually delivering email.
 *
 * The "doesn't deliver, just records the call" pattern matches how
 * other shop tests interact with wp_remote_post and Stripe.
 */
class MailNotificationsTest extends TestCase
{
    /** @var array<int, array{to:string,subject:string,message:string,headers:array}> */
    private array $captured = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->captured = [];
        // Intercept wp_mail by short-circuiting via the pre_wp_mail filter.
        // Return a non-null value to skip the actual wp_mail() body.
        add_filter('pre_wp_mail', [$this, 'captureMail'], 10, 2);
    }

    protected function tearDown(): void
    {
        remove_filter('pre_wp_mail', [$this, 'captureMail'], 10);
        parent::tearDown();
    }

    public function captureMail(?bool $short, array $atts): bool
    {
        $this->captured[] = [
            'to'      => $atts['to'],
            'subject' => $atts['subject'],
            'message' => $atts['message'],
            'headers' => $atts['headers'],
        ];
        return true;
    }

    public function testOfferConfirmationSendsToBuyerEmail(): void
    {
        $result = MailNotifications::sendOfferConfirmation([
            'email'        => 'buyer@example.com',
            'card_title'   => 'Charizard Base Set 4/102',
            'offer_amount' => '$2,500.00',
        ]);

        $this->assertTrue($result);
        $this->assertCount(1, $this->captured);
        $this->assertSame('buyer@example.com', $this->captured[0]['to']);
        $this->assertStringContainsString('Charizard Base Set 4/102', $this->captured[0]['subject']);
        $this->assertStringContainsString('$2,500.00', $this->captured[0]['message']);
    }

    public function testOfferConfirmationIncludesBuyerNoteWhenPresent(): void
    {
        MailNotifications::sendOfferConfirmation([
            'email'        => 'buyer@example.com',
            'card_title'   => 'Charizard',
            'offer_amount' => '$500.00',
            'message'      => 'Looking for shadowless if you have it.',
        ]);
        $this->assertStringContainsString(
            'Looking for shadowless if you have it.',
            $this->captured[0]['message']
        );
    }

    public function testOfferConfirmationOmitsNoteSectionWhenMessageEmpty(): void
    {
        // Pin: a buyer who left the message field blank should NOT
        // see a "Your note:" header in their confirmation email
        // followed by nothing. Cosmetic, but reads awkwardly.
        MailNotifications::sendOfferConfirmation([
            'email'        => 'buyer@example.com',
            'card_title'   => 'Charizard',
            'offer_amount' => '$500.00',
            'message'      => '',
        ]);
        $this->assertStringNotContainsString('Your note:', $this->captured[0]['message']);
    }

    public function testOfferConfirmationUsesItzenzoFromHeader(): void
    {
        // Pin the per-send From — verifies the header strategy is
        // working and the portfolio-side wp_mail() (which sets no
        // headers) would NOT inherit this From accidentally.
        MailNotifications::sendOfferConfirmation([
            'email'        => 'buyer@example.com',
            'card_title'   => 'Charizard',
            'offer_amount' => '$500.00',
        ]);
        $this->assertContains(
            'From: itzenzoTTV <noreply@itzenzo.tv>',
            $this->captured[0]['headers']
        );
    }

    public function testOfferConfirmationContentTypeIsPlainText(): void
    {
        MailNotifications::sendOfferConfirmation([
            'email'        => 'buyer@example.com',
            'card_title'   => 'Charizard',
            'offer_amount' => '$500.00',
        ]);
        $this->assertContains(
            'Content-Type: text/plain; charset=UTF-8',
            $this->captured[0]['headers']
        );
    }

    public function testOfferConfirmationReturnsFalseOnInvalidEmail(): void
    {
        $result = MailNotifications::sendOfferConfirmation([
            'email'        => 'not-an-email',
            'card_title'   => 'Charizard',
            'offer_amount' => '$500.00',
        ]);
        $this->assertFalse($result);
        $this->assertCount(0, $this->captured, 'wp_mail must not be called with an invalid email');
    }

    public function testOfferConfirmationReturnsFalseOnEmptyEmail(): void
    {
        $result = MailNotifications::sendOfferConfirmation([
            'email'        => '',
            'card_title'   => 'Charizard',
            'offer_amount' => '$500.00',
        ]);
        $this->assertFalse($result);
        $this->assertCount(0, $this->captured);
    }

    public function testCardRequestConfirmationSendsToBuyerEmail(): void
    {
        $result = MailNotifications::sendCardRequestConfirmation([
            'email'      => 'buyer@example.com',
            'card_title' => 'Pikachu Base Set 58/102',
        ]);
        $this->assertTrue($result);
        $this->assertCount(1, $this->captured);
        $this->assertSame('buyer@example.com', $this->captured[0]['to']);
        $this->assertStringContainsString('Pikachu Base Set 58/102', $this->captured[0]['subject']);
        $this->assertStringContainsString('Pikachu Base Set 58/102', $this->captured[0]['message']);
    }

    public function testCardRequestConfirmationUsesSameFromHeader(): void
    {
        MailNotifications::sendCardRequestConfirmation([
            'email'      => 'buyer@example.com',
            'card_title' => 'Pikachu',
        ]);
        $this->assertContains(
            'From: itzenzoTTV <noreply@itzenzo.tv>',
            $this->captured[0]['headers']
        );
    }

    public function testCardRequestConfirmationReturnsFalseOnInvalidEmail(): void
    {
        $result = MailNotifications::sendCardRequestConfirmation([
            'email'      => 'bad-email',
            'card_title' => 'Pikachu',
        ]);
        $this->assertFalse($result);
        $this->assertCount(0, $this->captured);
    }

    public function testCardRequestConfirmationMentionsTikTokStreamLink(): void
    {
        // The email tells unlinked buyers where to watch — Whatnot is
        // the live show venue post-2026-05-16. Pin so a future copy
        // change can't quietly drop it.
        MailNotifications::sendCardRequestConfirmation([
            'email'      => 'buyer@example.com',
            'card_title' => 'Pikachu',
        ]);
        $this->assertStringContainsString(
            'whatnot.com/user/itzenzottv',
            $this->captured[0]['message']
        );
    }
}
