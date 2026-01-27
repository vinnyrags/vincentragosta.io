<?php

namespace ParentTheme\Tests\Integration;

use ParentTheme\Theme;
use Timber\Site;
use WorDBless\BaseTestCase;
use ReflectionClass;

/**
 * Integration tests for the Theme class.
 */
class ThemeTest extends BaseTestCase
{
    /**
     * Test that Theme can be instantiated.
     */
    public function testThemeCanBeInstantiated(): void
    {
        $theme = new Theme();
        $this->assertInstanceOf(Theme::class, $theme);
    }

    /**
     * Test that Theme extends Timber\Site.
     */
    public function testThemeExtendsSite(): void
    {
        $theme = new Theme();
        $this->assertInstanceOf(Site::class, $theme);
    }

    /**
     * Test that Theme has empty providers by default.
     */
    public function testThemeHasEmptyProvidersByDefault(): void
    {
        $theme = new Theme();
        $reflection = new ReflectionClass($theme);
        $property = $reflection->getProperty('providers');
        $property->setAccessible(true);

        $providers = $property->getValue($theme);
        $this->assertIsArray($providers);
        $this->assertEmpty($providers);
    }

    /**
     * Test that Theme has template directories configured.
     */
    public function testThemeHasTemplateDirectories(): void
    {
        $theme = new Theme();
        $reflection = new ReflectionClass($theme);
        $property = $reflection->getProperty('templateDirectories');
        $property->setAccessible(true);

        $dirs = $property->getValue($theme);
        $this->assertIsArray($dirs);
        $this->assertContains('templates', $dirs);
        $this->assertContains('views', $dirs);
        $this->assertContains('blocks', $dirs);
    }

    /**
     * Test that registerAll method exists and is callable.
     */
    public function testRegisterAllMethodExists(): void
    {
        $theme = new Theme();
        $reflection = new ReflectionClass($theme);

        $this->assertTrue($reflection->hasMethod('registerAll'));

        $method = $reflection->getMethod('registerAll');
        $this->assertTrue($method->isProtected());
    }
}
