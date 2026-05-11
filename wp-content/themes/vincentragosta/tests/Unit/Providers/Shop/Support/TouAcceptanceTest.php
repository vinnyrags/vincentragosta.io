<?php

namespace ChildTheme\Tests\Unit\Providers\Shop\Support;

use ChildTheme\Providers\Shop\Support\TouAcceptance;
use PHPUnit\Framework\TestCase;
use WP_Error;

/**
 * Tests for the ToS-acceptance validator. The helper is the gate every
 * checkout endpoint runs before stock decrement / Stripe call, so it
 * needs to: reject missing/wrong versions cleanly, return Stripe-ready
 * audit metadata on success, and pull the right IP through nginx's
 * X-Forwarded-For.
 */
class TouAcceptanceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Clean state between tests — $_SERVER persists across cases
        // in PHPUnit and we set HTTP_X_FORWARDED_FOR / REMOTE_ADDR /
        // HTTP_USER_AGENT below.
        unset(
            $_SERVER['HTTP_X_FORWARDED_FOR'],
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        );
    }

    public function testRejectsMissingTermsVersion(): void
    {
        $request = $this->mockRequest(['terms_version' => null]);
        $result = TouAcceptance::validate($request);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('terms_not_accepted', $result->get_error_code());
        $data = $result->get_error_data();
        $this->assertSame(400, $data['status']);
    }

    public function testRejectsEmptyTermsVersion(): void
    {
        $request = $this->mockRequest(['terms_version' => '']);
        $result = TouAcceptance::validate($request);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('terms_not_accepted', $result->get_error_code());
    }

    public function testRejectsOutdatedTermsVersion(): void
    {
        // A buyer caching old HTML before a terms update should hit
        // this path and get a "please re-accept" message — NOT a
        // generic 400. The error data carries the current version so
        // the client can render an actionable message.
        $request = $this->mockRequest(['terms_version' => '0.9']);
        $result = TouAcceptance::validate($request);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('terms_version_outdated', $result->get_error_code());
        $data = $result->get_error_data();
        $this->assertSame(400, $data['status']);
        $this->assertSame(TouAcceptance::CURRENT_VERSION, $data['current_version']);
        $this->assertSame('0.9', $data['submitted']);
    }

    public function testCurrentVersionReturnsAuditFields(): void
    {
        $_SERVER['REMOTE_ADDR'] = '198.51.100.7';
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 TestBrowser/1.0';

        $request = $this->mockRequest([
            'terms_version' => TouAcceptance::CURRENT_VERSION,
        ]);
        $result = TouAcceptance::validate($request);

        $this->assertIsArray($result);
        $this->assertSame(TouAcceptance::CURRENT_VERSION, $result['terms_version']);
        $this->assertSame('198.51.100.7', $result['terms_accepted_ip']);
        $this->assertSame('Mozilla/5.0 TestBrowser/1.0', $result['terms_accepted_ua']);
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/',
            $result['terms_accepted_at'],
            'terms_accepted_at must be ISO 8601 UTC with trailing Z'
        );
    }

    public function testPrefersXForwardedForOverRemoteAddr(): void
    {
        // Nginx → PHP-FPM puts the real client IP in X-Forwarded-For
        // and the nginx-loopback IP in REMOTE_ADDR. Without this
        // preference every acceptance would record "127.0.0.1".
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.50';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $request = $this->mockRequest([
            'terms_version' => TouAcceptance::CURRENT_VERSION,
        ]);
        $result = TouAcceptance::validate($request);

        $this->assertSame('203.0.113.50', $result['terms_accepted_ip']);
    }

    public function testTakesFirstIpFromForwardedChain(): void
    {
        // Multiple proxies append themselves to XFF: "client, proxy1, proxy2".
        // First entry is the original client, the rest are intermediate
        // hops we don't care about.
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.50, 70.0.0.1, 10.0.0.5';

        $request = $this->mockRequest([
            'terms_version' => TouAcceptance::CURRENT_VERSION,
        ]);
        $result = TouAcceptance::validate($request);

        $this->assertSame('203.0.113.50', $result['terms_accepted_ip']);
    }

    public function testFallsBackToRemoteAddrWhenXForwardedForMissing(): void
    {
        $_SERVER['REMOTE_ADDR'] = '198.51.100.7';

        $request = $this->mockRequest([
            'terms_version' => TouAcceptance::CURRENT_VERSION,
        ]);
        $result = TouAcceptance::validate($request);

        $this->assertSame('198.51.100.7', $result['terms_accepted_ip']);
    }

    public function testReturnsUnknownWhenNoIpAvailable(): void
    {
        // CLI / cron context — neither header is set. Must not fail
        // the validate call; we just record 'unknown' for IP and let
        // the rest of the audit fields stand.
        $request = $this->mockRequest([
            'terms_version' => TouAcceptance::CURRENT_VERSION,
        ]);
        $result = TouAcceptance::validate($request);

        $this->assertSame('unknown', $result['terms_accepted_ip']);
    }

    public function testIgnoresInvalidIpInXForwardedFor(): void
    {
        // Spoofed XFF header — must not blindly trust it. Fall back
        // to REMOTE_ADDR if the XFF entry doesn't parse as a real IP.
        $_SERVER['HTTP_X_FORWARDED_FOR'] = 'not-an-ip';
        $_SERVER['REMOTE_ADDR'] = '198.51.100.7';

        $request = $this->mockRequest([
            'terms_version' => TouAcceptance::CURRENT_VERSION,
        ]);
        $result = TouAcceptance::validate($request);

        $this->assertSame('198.51.100.7', $result['terms_accepted_ip']);
    }

    public function testTruncatesLongUserAgentToStripeMetadataLimit(): void
    {
        // Stripe metadata values are capped at 500 chars. A pathologically
        // long UA string must be truncated, not allowed to blow up the
        // whole session.create call.
        $_SERVER['HTTP_USER_AGENT'] = str_repeat('A', 600);

        $request = $this->mockRequest([
            'terms_version' => TouAcceptance::CURRENT_VERSION,
        ]);
        $result = TouAcceptance::validate($request);

        $this->assertSame(500, strlen($result['terms_accepted_ua']));
    }

    public function testReturnsUnknownWhenUserAgentMissing(): void
    {
        // Don't carry an empty string into Stripe metadata — Stripe
        // accepts empty values but 'unknown' reads more honestly in
        // the dispute portal.
        $request = $this->mockRequest([
            'terms_version' => TouAcceptance::CURRENT_VERSION,
        ]);
        $result = TouAcceptance::validate($request);

        $this->assertSame('unknown', $result['terms_accepted_ua']);
    }

    /**
     * Build a minimal mock of WP_REST_Request supporting get_param.
     */
    private function mockRequest(array $params): \WP_REST_Request
    {
        $request = $this->createMock(\WP_REST_Request::class);
        $request->method('get_param')
            ->willReturnCallback(fn (string $key) => $params[$key] ?? null);
        return $request;
    }
}
