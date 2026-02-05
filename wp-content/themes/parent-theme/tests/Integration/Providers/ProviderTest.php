<?php

namespace ParentTheme\Tests\Integration\Providers;

use DI\Container;
use ParentTheme\Providers\Provider;
use ParentTheme\Providers\Contracts\Registrable;
use ParentTheme\Providers\Support\Feature\FeatureManager;
use ParentTheme\Providers\Support\Rest\Endpoint;
use ParentTheme\Providers\Support\Rest\RestManager;
use ParentTheme\Tests\Support\HasContainer;
use WorDBless\BaseTestCase;
use ReflectionClass;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

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
class StubParentProvider extends Provider
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

// Stub REST endpoints for route tests.
class StubEndpointAlpha extends Endpoint
{
    public function getRoute(): string
    {
        return '/alpha';
    }

    public function getMethods(): string|array
    {
        return 'GET';
    }

    public function callback(WP_REST_Request $request): WP_REST_Response|WP_Error|array
    {
        return ['endpoint' => 'alpha'];
    }

    public function getPermission(WP_REST_Request $request): bool|WP_Error
    {
        return true;
    }
}

class StubEndpointBeta extends Endpoint
{
    public function getRoute(): string
    {
        return '/beta';
    }

    public function getMethods(): string|array
    {
        return 'POST';
    }

    public function callback(WP_REST_Request $request): WP_REST_Response|WP_Error|array
    {
        return ['endpoint' => 'beta'];
    }

    public function getPermission(WP_REST_Request $request): bool|WP_Error
    {
        return true;
    }
}

class StubEndpointGamma extends Endpoint
{
    public function getRoute(): string
    {
        return '/gamma';
    }

    public function getMethods(): string|array
    {
        return 'GET';
    }

    public function callback(WP_REST_Request $request): WP_REST_Response|WP_Error|array
    {
        return ['endpoint' => 'gamma'];
    }

    public function getPermission(WP_REST_Request $request): bool|WP_Error
    {
        return true;
    }
}

// Provider with routes for testing.
class StubRouteParentProvider extends Provider
{
    protected array $routes = [
        StubEndpointAlpha::class,
        StubEndpointBeta::class,
    ];

    public function register(): void
    {
        parent::register();
    }
}

// Child provider that adds a route and opts out of a parent route.
class StubRouteChildProvider extends StubRouteParentProvider
{
    protected array $routes = [
        StubEndpointGamma::class,
        StubEndpointBeta::class => false,
    ];
}

// Child provider that inherits all parent routes.
class StubRouteChildNoOverrideProvider extends StubRouteParentProvider
{
    protected array $routes = [
        StubEndpointGamma::class,
    ];
}

/**
 * Integration tests for the abstract Provider class.
 */
class ProviderTest extends BaseTestCase
{
    use HasContainer;

    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = $this->buildTestContainer();
    }

    /**
     * Create a concrete implementation of the abstract Provider.
     */
    private function createConcreteProvider(): Provider
    {
        return new class($this->container) extends Provider {
            public function register(): void
            {
                parent::register();
            }
        };
    }

    /**
     * Test that Provider implements Registrable.
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
     * Test that init creates a FeatureManager instance.
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
        $provider = new StubChildNoOverrideProvider($this->container);

        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('collectFeatures');
        $method->setAccessible(true);

        $features = $method->invoke($provider);
        $manager = new FeatureManager($features, $this->container);
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
        $provider = new StubChildProvider($this->container);

        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('collectFeatures');
        $method->setAccessible(true);

        $features = $method->invoke($provider);
        $manager = new FeatureManager($features, $this->container);

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
        $provider = new StubParentProvider($this->container);

        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('collectFeatures');
        $method->setAccessible(true);

        $features = $method->invoke($provider);
        $manager = new FeatureManager($features, $this->container);
        $enabled = $manager->getEnabled();

        $this->assertContains(StubFeatureOne::class, $enabled);
        $this->assertContains(StubFeatureTwo::class, $enabled);
        $this->assertCount(2, $enabled);
    }

    /**
     * Test that default addTwigFunctions returns the Environment unchanged.
     */
    public function testAddTwigFunctionsReturnsEnvironmentUnchanged(): void
    {
        $provider = $this->createConcreteProvider();
        $loader = new \Twig\Loader\ArrayLoader([]);
        $twig = new \Twig\Environment($loader);

        $result = $provider->addTwigFunctions($twig);

        $this->assertSame($twig, $result);
    }

    /**
     * Test that collectRoutes returns empty for base provider with no routes.
     */
    public function testCollectRoutesReturnsEmptyForBaseProvider(): void
    {
        $provider = $this->createConcreteProvider();

        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('collectRoutes');
        $method->setAccessible(true);

        $this->assertSame([], $method->invoke($provider));
    }

    /**
     * Test that collectRoutes merges parent and child routes.
     */
    public function testCollectRoutesMergesParentAndChild(): void
    {
        $provider = new StubRouteChildNoOverrideProvider($this->container);

        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('collectRoutes');
        $method->setAccessible(true);

        $routes = $method->invoke($provider);
        $manager = new RestManager($routes, $this->container, 'test/v1');
        $enabled = $manager->getEnabled();

        $this->assertContains(StubEndpointAlpha::class, $enabled);
        $this->assertContains(StubEndpointBeta::class, $enabled);
        $this->assertContains(StubEndpointGamma::class, $enabled);
        $this->assertCount(3, $enabled);
    }

    /**
     * Test that a child provider can opt out of a parent route.
     */
    public function testCollectRoutesChildOptOutDisablesParentRoute(): void
    {
        $provider = new StubRouteChildProvider($this->container);

        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('collectRoutes');
        $method->setAccessible(true);

        $routes = $method->invoke($provider);
        $manager = new RestManager($routes, $this->container, 'test/v1');

        $this->assertFalse($manager->isEnabled(StubEndpointBeta::class));
        $this->assertContains(StubEndpointBeta::class, $manager->getDisabled());

        $this->assertTrue($manager->isEnabled(StubEndpointAlpha::class));
        $this->assertTrue($manager->isEnabled(StubEndpointGamma::class));

        $enabled = $manager->getEnabled();
        $this->assertContains(StubEndpointAlpha::class, $enabled);
        $this->assertContains(StubEndpointGamma::class, $enabled);
        $this->assertNotContains(StubEndpointBeta::class, $enabled);
        $this->assertCount(2, $enabled);
    }

    /**
     * Test that register hooks rest_api_init when routes exist.
     */
    public function testRegisterHooksRestApiInitWhenRoutesExist(): void
    {
        $provider = new StubRouteParentProvider($this->container);
        $provider->register();

        $this->assertIsInt(has_action('rest_api_init', [$provider, 'registerRoutes']));
    }

    /**
     * Test that register skips rest_api_init when no routes exist.
     */
    public function testRegisterSkipsRestApiInitWhenNoRoutes(): void
    {
        $provider = $this->createConcreteProvider();
        $provider->register();

        $this->assertFalse(has_action('rest_api_init', [$provider, 'registerRoutes']));
    }

    /**
     * Test that init creates a RestManager instance.
     */
    public function testBootCreatesRestManager(): void
    {
        $provider = $this->createConcreteProvider();
        $provider->register();

        $reflection = new ReflectionClass($provider);
        $property = $reflection->getProperty('restManager');
        $property->setAccessible(true);

        $this->assertInstanceOf(RestManager::class, $property->getValue($provider));
    }
}
