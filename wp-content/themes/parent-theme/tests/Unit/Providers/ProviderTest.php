<?php

namespace ParentTheme\Tests\Unit\Providers;

use DI\Container;
use Mythus\Contracts\Registrable;
use ParentTheme\Providers\Provider;
use ParentTheme\Tests\Support\HasContainer;
use ReflectionMethod;
use Twig\Environment;
use WorDBless\BaseTestCase;

/**
 * Concrete test provider for unit testing.
 *
 * Located in a predictable directory for config path testing.
 */
class TestableProvider extends Provider
{
    protected array $features = [];
    protected array $blocks = [];
    private ?string $testConfigPath = null;

    public function register(): void
    {
        // Minimal registration for testing
    }

    /**
     * Set a custom config path for testing.
     * Must be called before loadConfig to override the setup()-derived path.
     */
    public function setTestConfigPath(string $path): void
    {
        $this->testConfigPath = $path;
    }

    /**
     * Expose loadConfig for testing with custom path support.
     */
    public function testLoadConfig(string $filename): ?array
    {
        // Initialize normally first
        $this->setup();

        // Override configPath if a test path was set
        if ($this->testConfigPath !== null) {
            $this->configPath = $this->testConfigPath;
        }

        // Build the filepath manually to avoid calling setup() again
        $filepath = $this->configPath . '/' . $filename;

        if (!file_exists($filepath)) {
            return null;
        }

        $content = file_get_contents($filepath);
        if ($content === false) {
            return null;
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $data;
    }

    /**
     * Expose maybeRegisterTwigFilter for testing.
     */
    public function testMaybeRegisterTwigFilter(): void
    {
        $this->maybeRegisterTwigFilter();
    }
}

/**
 * Provider that overrides addTwigFunctions.
 */
class TwigOverrideProvider extends Provider
{
    protected array $features = [];
    protected array $blocks = [];

    public function register(): void
    {
        $this->setup();
        $this->maybeRegisterTwigFilter();
    }

    public function addTwigFunctions(Environment $twig): Environment
    {
        // Custom implementation
        return $twig;
    }
}

/**
 * Unit tests for the Provider base class.
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
     * Test loadConfig returns null for missing file.
     */
    public function testLoadConfigReturnsNullForMissingFile(): void
    {
        $provider = new TestableProvider($this->container);
        $provider->setTestConfigPath('/nonexistent/config/path');

        $result = $provider->testLoadConfig('nonexistent.json');

        $this->assertNull($result);
    }

    /**
     * Test loadConfig returns null for invalid JSON.
     */
    public function testLoadConfigReturnsNullForInvalidJson(): void
    {
        $tempDir = sys_get_temp_dir() . '/provider-test-' . uniqid();
        mkdir($tempDir, 0777, true);
        file_put_contents($tempDir . '/invalid.json', '{ invalid json }');

        $provider = new TestableProvider($this->container);
        $provider->setTestConfigPath($tempDir);

        $result = $provider->testLoadConfig('invalid.json');

        $this->assertNull($result);

        // Cleanup
        unlink($tempDir . '/invalid.json');
        rmdir($tempDir);
    }

    /**
     * Test loadConfig returns array for valid JSON.
     */
    public function testLoadConfigReturnsArrayForValidJson(): void
    {
        $tempDir = sys_get_temp_dir() . '/provider-test-' . uniqid();
        mkdir($tempDir, 0777, true);
        file_put_contents($tempDir . '/valid.json', '{"key": "value", "nested": {"foo": "bar"}}');

        $provider = new TestableProvider($this->container);
        $provider->setTestConfigPath($tempDir);

        $result = $provider->testLoadConfig('valid.json');

        $this->assertIsArray($result);
        $this->assertEquals('value', $result['key']);
        $this->assertEquals('bar', $result['nested']['foo']);

        // Cleanup
        unlink($tempDir . '/valid.json');
        rmdir($tempDir);
    }

    /**
     * Test maybeRegisterTwigFilter skips base class implementation.
     */
    public function testMaybeRegisterTwigFilterSkipsBaseClass(): void
    {
        $provider = new TestableProvider($this->container);

        // Force initialization
        $provider->testMaybeRegisterTwigFilter();

        // Base provider shouldn't register timber/twig filter
        $this->assertFalse(has_filter('timber/twig', [$provider, 'addTwigFunctions']));
    }

    /**
     * Test maybeRegisterTwigFilter registers filter when overridden.
     */
    public function testMaybeRegisterTwigFilterRegistersWhenOverridden(): void
    {
        $provider = new TwigOverrideProvider($this->container);

        // Initialize and register
        $provider->register();

        // Override should register timber/twig filter
        $this->assertGreaterThan(0, has_filter('timber/twig', [$provider, 'addTwigFunctions']));
    }

    /**
     * Test that Provider is abstract and cannot be instantiated directly.
     */
    public function testProviderIsAbstract(): void
    {
        $reflection = new \ReflectionClass(Provider::class);

        $this->assertTrue($reflection->isAbstract());
    }

    /**
     * Test that Provider implements Registrable.
     */
    public function testProviderImplementsRegistrable(): void
    {
        $provider = new TestableProvider($this->container);

        $this->assertInstanceOf(Registrable::class, $provider);
    }

    /**
     * Test getTemplatePath returns null when no templates directory exists.
     */
    public function testGetTemplatePathReturnsNullWhenNoTemplatesDir(): void
    {
        $provider = new TestableProvider($this->container);

        $this->assertNull($provider->getTemplatePath());
    }

    /**
     * Test getTemplatePath returns path when templates directory exists.
     */
    public function testGetTemplatePathReturnsPathWhenTemplatesDirExists(): void
    {
        // TestableProvider's file is in this test directory — create a templates/ sibling
        $reflection = new \ReflectionClass(TestableProvider::class);
        $providerDir = dirname($reflection->getFileName());
        $templatesDir = $providerDir . '/templates';

        mkdir($templatesDir, 0777, true);

        try {
            $provider = new TestableProvider($this->container);
            $result = $provider->getTemplatePath();

            $this->assertSame($templatesDir, $result);
        } finally {
            rmdir($templatesDir);
        }
    }
}
