<?php

namespace ChildTheme\Tests\Unit\Providers\Shop\Services;

use ChildTheme\Providers\Shop\Services\StripeService;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the StripeService.
 */
class StripeServiceTest extends TestCase
{
    public function testCreateCheckoutSessionAcceptsInternationalParameter(): void
    {
        $reflection = new \ReflectionClass(StripeService::class);
        $method = $reflection->getMethod('createCheckoutSession');

        $params = $method->getParameters();
        $paramNames = array_map(fn ($p) => $p->getName(), $params);

        $this->assertContains('international', $paramNames);
    }

    public function testInternationalParameterDefaultsToFalse(): void
    {
        $reflection = new \ReflectionClass(StripeService::class);
        $method = $reflection->getMethod('createCheckoutSession');

        $intlParam = null;
        foreach ($method->getParameters() as $param) {
            if ($param->getName() === 'international') {
                $intlParam = $param;
            }
        }

        $this->assertNotNull($intlParam);
        $this->assertTrue($intlParam->isDefaultValueAvailable());
        $this->assertFalse($intlParam->getDefaultValue());
    }

    public function testSkipShippingParameterStillExists(): void
    {
        $reflection = new \ReflectionClass(StripeService::class);
        $method = $reflection->getMethod('createCheckoutSession');

        $paramNames = array_map(fn ($p) => $p->getName(), $method->getParameters());

        $this->assertContains('skipShipping', $paramNames);
    }
}
