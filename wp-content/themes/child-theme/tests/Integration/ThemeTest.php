<?php

namespace ChildTheme\Tests\Integration;

use ChildTheme\Theme;
use ChildTheme\Providers\AssetService\AssetServiceProvider;
use ChildTheme\Providers\BlockService\BlockServiceProvider;
use ChildTheme\Providers\PostTypeService\PostTypeServiceProvider;
use ChildTheme\Providers\ThemeService\ThemeServiceProvider;
use ChildTheme\Providers\TwigService\TwigServiceProvider;
use WorDBless\BaseTestCase;
use ReflectionClass;

/**
 * Integration tests for the Theme class.
 *
 * These tests verify the theme bootstraps correctly with WordPress.
 */
class ThemeTest extends BaseTestCase
{
    private Theme $theme;

    public function set_up(): void
    {
        parent::set_up();
        $this->theme = new Theme();
    }

    /**
     * Test that Theme class can be instantiated.
     */
    public function testThemeCanBeInstantiated(): void
    {
        $this->assertInstanceOf(Theme::class, $this->theme);
    }

    /**
     * Test that Theme has providers configured.
     */
    public function testThemeHasProviders(): void
    {
        $reflection = new ReflectionClass($this->theme);
        $property = $reflection->getProperty('providers');
        $property->setAccessible(true);

        $providers = $property->getValue($this->theme);

        $this->assertIsArray($providers);
        $this->assertNotEmpty($providers);
    }

    /**
     * Test that all expected providers are registered.
     */
    public function testExpectedProvidersAreRegistered(): void
    {
        $reflection = new ReflectionClass($this->theme);
        $property = $reflection->getProperty('providers');
        $property->setAccessible(true);

        $providers = $property->getValue($this->theme);

        $this->assertContains(ThemeServiceProvider::class, $providers);
        $this->assertContains(AssetServiceProvider::class, $providers);
        $this->assertContains(BlockServiceProvider::class, $providers);
        $this->assertContains(PostTypeServiceProvider::class, $providers);
        $this->assertContains(TwigServiceProvider::class, $providers);
    }

    /**
     * Test that Theme extends the parent theme's base class.
     */
    public function testThemeExtendsParentTheme(): void
    {
        $this->assertInstanceOf(\ParentTheme\Theme::class, $this->theme);
    }
}
