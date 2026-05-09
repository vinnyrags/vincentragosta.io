<?php

namespace ChildTheme\Tests\Unit\Providers\Shop\Endpoints;

use ChildTheme\Providers\Shop\Endpoints\BundleCheckoutEndpoint;
use Mythus\Support\Rest\Endpoint;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Structural tests for BundleCheckoutEndpoint. The atomic stock
 * decrement (UPDATE ... WHERE CAST(option_value AS UNSIGNED) > 0) is
 * the heart of the no-oversell promise — pinned by source inspection
 * rather than callback exercise because WorDBless doesn't simulate the
 * raw $wpdb UPDATE meaningfully. The race is verified end-to-end on
 * production by Stripe's test mode + manual checkout attempts.
 */
class BundleCheckoutEndpointTest extends TestCase
{
    public function testExtendsEndpoint(): void
    {
        $this->assertTrue(is_subclass_of(BundleCheckoutEndpoint::class, Endpoint::class));
    }

    public function testRouteAndMethod(): void
    {
        $endpoint = (new ReflectionClass(BundleCheckoutEndpoint::class))->newInstanceWithoutConstructor();
        $this->assertSame('/bundle-checkout', $endpoint->getRoute());
        $this->assertSame('POST', $endpoint->getMethods());
    }

    public function testRequiresPriceIdArg(): void
    {
        $endpoint = (new ReflectionClass(BundleCheckoutEndpoint::class))->newInstanceWithoutConstructor();
        $args = $endpoint->getArgs();
        $this->assertArrayHasKey('priceId', $args);
        $this->assertTrue($args['priceId']['required']);
        $this->assertSame('sanitize_text_field', $args['priceId']['sanitize_callback']);
    }

    public function testPermissionIsPublic(): void
    {
        // Bundle is sold from the homepage anonymously — no auth gate.
        // The buyer's identity comes from the Stripe checkout flow, not
        // from this endpoint.
        $endpoint = (new ReflectionClass(BundleCheckoutEndpoint::class))->newInstanceWithoutConstructor();
        $request = $this->createMock(\WP_REST_Request::class);
        $this->assertTrue($endpoint->getPermission($request));
    }

    public function testAtomicDecrementSqlIncludesRaceSafetyClause(): void
    {
        // The decrement is the single most race-prone path in the
        // bundle flow — two buyers can hit Buy at the same instant and
        // both could decrement past zero unless the WHERE clause guards
        // it. Pin the atomic-update pattern so a refactor can't
        // accidentally drop the safety check.
        $source = file_get_contents(
            __DIR__ . '/../../../../../src/Providers/Shop/Endpoints/BundleCheckoutEndpoint.php'
        );

        $this->assertStringContainsString(
            'CAST(option_value AS UNSIGNED) - 1',
            $source,
            'Decrement must run as a single UPDATE that reads + writes atomically'
        );
        $this->assertStringContainsString(
            'CAST(option_value AS UNSIGNED) > 0',
            $source,
            'WHERE clause must guard against negative stock — without it, oversell is possible under concurrent load'
        );
        $this->assertStringContainsString(
            "'options_bundle_stock'",
            $source,
            'Decrement must target the ACF-backed wp_options row (options_bundle_stock)'
        );
    }

    public function testStripeMetadataMarksBundleSource(): void
    {
        // The Stripe webhook handler in Nous looks for
        // metadata.source === 'bundle' to identify bundle purchases —
        // changing this string breaks the bot's downstream handling.
        $source = file_get_contents(
            __DIR__ . '/../../../../../src/Providers/Shop/Endpoints/BundleCheckoutEndpoint.php'
        );
        $this->assertMatchesRegularExpression(
            "/'source'\\s*=>\\s*'bundle'/",
            $source,
            "Bundle Stripe sessions must tag metadata.source = 'bundle' so Nous can route them"
        );
    }

    public function testRollbackPathRestoresStockOnStripeFailure(): void
    {
        // If Stripe session creation fails AFTER we've decremented
        // stock, we must restore it — otherwise a transient Stripe
        // error gradually drains stock without any sales.
        $source = file_get_contents(
            __DIR__ . '/../../../../../src/Providers/Shop/Endpoints/BundleCheckoutEndpoint.php'
        );

        $this->assertStringContainsString(
            'incrementBundleStock',
            $source,
            'Catch block must call incrementBundleStock() to undo the pre-flight decrement'
        );
        $this->assertStringContainsString(
            'CAST(option_value AS UNSIGNED) + 1',
            $source,
            'Restoration must add 1 atomically (mirroring the decrement)'
        );
    }
}
