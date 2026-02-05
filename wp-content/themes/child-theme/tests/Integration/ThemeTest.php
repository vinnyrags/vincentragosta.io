<?php

namespace ChildTheme\Tests\Integration;

use ChildTheme\Theme;
use ChildTheme\Providers\Project\ProjectProvider;
use ChildTheme\Providers\Theme\ThemeProvider;
use ParentTheme\Theme as BaseTheme;
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
        // Reset the singleton before each test
        BaseTheme::resetInstance();
        $this->theme = new Theme();
        $this->theme->bootstrap();
    }

    public function tear_down(): void
    {
        // Reset after each test to ensure clean state
        BaseTheme::resetInstance();
        parent::tear_down();
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

        $this->assertContains(ThemeProvider::class, $providers);
        $this->assertContains(ProjectProvider::class, $providers);
    }

    /**
     * Test that Theme extends the parent theme's base class.
     */
    public function testThemeExtendsParentTheme(): void
    {
        $this->assertInstanceOf(\ParentTheme\Theme::class, $this->theme);
    }
}
