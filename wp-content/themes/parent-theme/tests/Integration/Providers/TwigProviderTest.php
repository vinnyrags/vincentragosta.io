<?php

namespace ParentTheme\Tests\Integration\Providers;

use ParentTheme\Providers\Twig\TwigProvider;
use ParentTheme\Providers\Contracts\Registrable;
use Twig\Environment;
use WorDBless\BaseTestCase;
use ReflectionClass;

/**
 * Integration tests for the TwigProvider class.
 */
class TwigProviderTest extends BaseTestCase
{
    /**
     * Test that TwigProvider implements Registrable.
     */
    public function testImplementsRegistrable(): void
    {
        $provider = new TwigProvider();
        $this->assertInstanceOf(Registrable::class, $provider);
    }

    /**
     * Test that register method adds timber/twig filter.
     */
    public function testRegisterAddsTwigFilter(): void
    {
        $provider = new TwigProvider();
        $provider->register();

        // Check that the filter was added
        $this->assertTrue(has_filter('timber/twig') !== false);
    }

    /**
     * Test that addTwigFunctions method exists and returns Twig Environment.
     */
    public function testAddTwigFunctionsMethodExists(): void
    {
        $provider = new TwigProvider();
        $reflection = new ReflectionClass($provider);

        $this->assertTrue($reflection->hasMethod('addTwigFunctions'));

        $method = $reflection->getMethod('addTwigFunctions');
        $this->assertTrue($method->isPublic());
    }

    /**
     * Test that addTwigFunctions returns the Twig Environment.
     */
    public function testAddTwigFunctionsReturnsTwigEnvironment(): void
    {
        $provider = new TwigProvider();

        // Create a mock Twig Environment
        $loader = new \Twig\Loader\ArrayLoader([]);
        $twig = new Environment($loader);

        $result = $provider->addTwigFunctions($twig);

        $this->assertInstanceOf(Environment::class, $result);
        $this->assertSame($twig, $result);
    }

    /**
     * Test that provider can be extended by child classes.
     */
    public function testProviderCanBeExtended(): void
    {
        $childProvider = new class extends TwigProvider {
            public bool $customFunctionAdded = false;

            public function addTwigFunctions(Environment $twig): Environment
            {
                $twig = parent::addTwigFunctions($twig);
                $this->customFunctionAdded = true;
                return $twig;
            }
        };

        $loader = new \Twig\Loader\ArrayLoader([]);
        $twig = new Environment($loader);

        $childProvider->addTwigFunctions($twig);

        $this->assertTrue($childProvider->customFunctionAdded);
    }
}
