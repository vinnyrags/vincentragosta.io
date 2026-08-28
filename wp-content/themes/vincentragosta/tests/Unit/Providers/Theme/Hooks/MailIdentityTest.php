<?php

namespace ChildTheme\Tests\Unit\Providers\Theme\Hooks;

use ChildTheme\Providers\Theme\Hooks\MailIdentity;
use Mythus\Contracts\Hook;
use WorDBless\BaseTestCase;

/**
 * The behaviour under test is the guard, not the substitution: wp_mail()
 * applies `wp_mail_from` even when the caller supplied an explicit
 * `From:` header, so an unconditional filter would silently rewrite the
 * Shop provider's per-send itzenzoTTV identity. These tests pin that.
 */
class MailIdentityTest extends BaseTestCase
{
    private MailIdentity $hook;

    public function setUp(): void
    {
        parent::setUp();
        $this->hook = new MailIdentity();
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
     * The regression that matters: shop mail must keep its own From.
     *
     * @dataProvider explicitAddresses
     */
    public function testLeavesAnExplicitAddressAlone(string $address): void
    {
        $this->assertSame($address, $this->hook->filterFromEmail($address));
    }

    public static function explicitAddresses(): array
    {
        return [
            'shop per-send identity' => ['noreply@itzenzo.tv'],
            'a plugin-set address' => ['forms@example.com'],
            'another site default' => ['wordpress@some-other-host.test'],
        ];
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
    }
}
