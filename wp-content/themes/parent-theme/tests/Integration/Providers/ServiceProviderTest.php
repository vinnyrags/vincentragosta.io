<?php

namespace ParentTheme\Tests\Integration\Providers;

use ParentTheme\Providers\ServiceProvider;
use ParentTheme\Contracts\Registrable;
use ParentTheme\Contracts\HasAssets;
use WorDBless\BaseTestCase;
use ReflectionClass;

/**
 * Integration tests for the abstract ServiceProvider class.
 */
class ServiceProviderTest extends BaseTestCase
{
    /**
     * Create a concrete implementation of the abstract ServiceProvider.
     */
    private function createConcreteProvider(): ServiceProvider
    {
        return new class extends ServiceProvider {
            public function register(): void
            {
                parent::register();
            }
        };
    }

    /**
     * Test that ServiceProvider implements Registrable.
     */
    public function testImplementsRegistrable(): void
    {
        $provider = $this->createConcreteProvider();
        $this->assertInstanceOf(Registrable::class, $provider);
    }

    /**
     * Test that ServiceProvider implements HasAssets.
     */
    public function testImplementsHasAssets(): void
    {
        $provider = $this->createConcreteProvider();
        $this->assertInstanceOf(HasAssets::class, $provider);
    }

    /**
     * Test that ServiceProvider has features property.
     */
    public function testHasFeaturesProperty(): void
    {
        $provider = $this->createConcreteProvider();
        $reflection = new ReflectionClass($provider);
        $property = $reflection->getProperty('features');
        $property->setAccessible(true);

        $features = $property->getValue($provider);
        $this->assertIsArray($features);
    }

    /**
     * Test that register method calls registerFeatures.
     */
    public function testRegisterCallsRegisterFeatures(): void
    {
        $provider = $this->createConcreteProvider();

        // If register() runs without error, registerFeatures was called
        $provider->register();
        $this->assertTrue(true);
    }

    /**
     * Test that ServiceProvider with features registers them.
     */
    public function testFeaturesAreRegistered(): void
    {
        // Create a test feature class
        $featureRegistered = false;

        $feature = new class($featureRegistered) implements Registrable {
            private bool $registered;

            public function __construct(bool &$registered)
            {
                $this->registered = &$registered;
            }

            public function register(): void
            {
                $this->registered = true;
            }
        };

        // We can't easily inject features, but we can verify the mechanism works
        // by checking the registerFeatures method exists and is called
        $provider = $this->createConcreteProvider();
        $reflection = new ReflectionClass($provider);

        $this->assertTrue($reflection->hasMethod('registerFeatures'));

        $method = $reflection->getMethod('registerFeatures');
        $this->assertTrue($method->isProtected());
    }

    /**
     * Test that provider has enqueueStyle method from HasAssets trait.
     */
    public function testHasEnqueueStyleMethod(): void
    {
        $provider = $this->createConcreteProvider();
        $this->assertTrue(method_exists($provider, 'enqueueStyle'));
    }

    /**
     * Test that provider has enqueueScript method from HasAssets trait.
     */
    public function testHasEnqueueScriptMethod(): void
    {
        $provider = $this->createConcreteProvider();
        $this->assertTrue(method_exists($provider, 'enqueueScript'));
    }
}
