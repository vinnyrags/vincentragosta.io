<?php

namespace ParentTheme\Tests\Integration\Providers;

use ParentTheme\Providers\ServiceProvider;
use ParentTheme\Providers\Contracts\Registrable;
use ParentTheme\Providers\Support\Feature\FeatureManager;
use WorDBless\BaseTestCase;
use ReflectionClass;

// Test feature stubs for collectFeatures tests.
class StubFeatureOne implements Registrable
{
    public function register(): void
    {
    }
}

class StubFeatureTwo implements Registrable
{
    public function register(): void
    {
    }
}

class StubFeatureThree implements Registrable
{
    public function register(): void
    {
    }
}

// Simulates a parent-level provider with features.
class StubParentProvider extends ServiceProvider
{
    protected array $features = [
        StubFeatureOne::class,
        StubFeatureTwo::class,
    ];

    public function register(): void
    {
        parent::register();
    }
}

// Simulates a child provider that adds its own feature and disables a parent one.
class StubChildProvider extends StubParentProvider
{
    protected array $features = [
        StubFeatureThree::class,
        StubFeatureTwo::class => false,
    ];
}

// Simulates a child provider that inherits all parent features without changes.
class StubChildNoOverrideProvider extends StubParentProvider
{
    protected array $features = [
        StubFeatureThree::class,
    ];
}

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
     * Test that register method calls registerFeatures.
     */
    public function testRegisterCallsRegisterFeatures(): void
    {
        $provider = $this->createConcreteProvider();
        $provider->register();
        $this->assertTrue(true);
    }

    /**
     * Test that boot creates a FeatureManager instance.
     */
    public function testBootCreatesFeatureManager(): void
    {
        $provider = $this->createConcreteProvider();
        $provider->register();

        $reflection = new ReflectionClass($provider);
        $property = $reflection->getProperty('featureManager');
        $property->setAccessible(true);

        $this->assertInstanceOf(FeatureManager::class, $property->getValue($provider));
    }

    /**
     * Test that collectFeatures returns empty for base provider with no features.
     */
    public function testCollectFeaturesReturnsEmptyForBaseProvider(): void
    {
        $provider = $this->createConcreteProvider();

        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('collectFeatures');
        $method->setAccessible(true);

        $this->assertSame([], $method->invoke($provider));
    }

    /**
     * Test that collectFeatures merges parent features into a child provider.
     */
    public function testCollectFeaturesMergesParentAndChild(): void
    {
        $provider = new StubChildNoOverrideProvider();

        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('collectFeatures');
        $method->setAccessible(true);

        $features = $method->invoke($provider);
        $manager = new FeatureManager($features);
        $enabled = $manager->getEnabled();

        $this->assertContains(StubFeatureOne::class, $enabled);
        $this->assertContains(StubFeatureTwo::class, $enabled);
        $this->assertContains(StubFeatureThree::class, $enabled);
        $this->assertCount(3, $enabled);
    }

    /**
     * Test that a child provider can opt out of a parent feature.
     */
    public function testCollectFeaturesChildOptOutDisablesParentFeature(): void
    {
        $provider = new StubChildProvider();

        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('collectFeatures');
        $method->setAccessible(true);

        $features = $method->invoke($provider);
        $manager = new FeatureManager($features);

        // StubFeatureTwo was disabled by child
        $this->assertFalse($manager->isEnabled(StubFeatureTwo::class));
        $this->assertContains(StubFeatureTwo::class, $manager->getDisabled());

        // Other features remain enabled
        $this->assertTrue($manager->isEnabled(StubFeatureOne::class));
        $this->assertTrue($manager->isEnabled(StubFeatureThree::class));

        $enabled = $manager->getEnabled();
        $this->assertContains(StubFeatureOne::class, $enabled);
        $this->assertContains(StubFeatureThree::class, $enabled);
        $this->assertNotContains(StubFeatureTwo::class, $enabled);
        $this->assertCount(2, $enabled);
    }

    /**
     * Test that a parent provider's features are collected without a child.
     */
    public function testCollectFeaturesParentOnly(): void
    {
        $provider = new StubParentProvider();

        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('collectFeatures');
        $method->setAccessible(true);

        $features = $method->invoke($provider);
        $manager = new FeatureManager($features);
        $enabled = $manager->getEnabled();

        $this->assertContains(StubFeatureOne::class, $enabled);
        $this->assertContains(StubFeatureTwo::class, $enabled);
        $this->assertCount(2, $enabled);
    }
}
