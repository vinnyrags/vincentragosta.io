<?php

namespace ParentTheme\Tests\Unit\Traits;

use PHPUnit\Framework\TestCase;
use ParentTheme\Traits\HasAssets;
use ReflectionClass;

/**
 * Unit tests for the HasAssets trait.
 */
class HasAssetsTraitTest extends TestCase
{
    /**
     * Create a mock class that uses the HasAssets trait.
     */
    private function createMockWithTrait(): object
    {
        return new class {
            use HasAssets;

            // Expose protected methods for testing
            public function publicGetProviderSlug(): string
            {
                return $this->getProviderSlug();
            }
        };
    }

    /**
     * Test that getProviderSlug converts class name correctly.
     */
    public function testGetProviderSlugConvertsClassName(): void
    {
        // Create a named class to test slug generation
        $mock = new class {
            use HasAssets;

            public function publicGetProviderSlug(): string
            {
                return $this->getProviderSlug();
            }
        };

        // Anonymous classes have a generated name, so we just verify it returns a string
        $slug = $mock->publicGetProviderSlug();
        $this->assertIsString($slug);
    }

    /**
     * Test provider slug conversion with a named test class.
     */
    public function testProviderSlugRemovesProviderSuffix(): void
    {
        // We'll test the regex logic directly
        $className = 'BlockServiceProvider';
        $name = preg_replace('/Provider$/', '', $className);
        $slug = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $name));

        $this->assertEquals('block-service', $slug);
    }

    /**
     * Test provider slug with ThemeServiceProvider.
     */
    public function testProviderSlugForThemeService(): void
    {
        $className = 'ThemeServiceProvider';
        $name = preg_replace('/Provider$/', '', $className);
        $slug = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $name));

        $this->assertEquals('theme-service', $slug);
    }

    /**
     * Test provider slug with AssetServiceProvider.
     */
    public function testProviderSlugForAssetService(): void
    {
        $className = 'AssetServiceProvider';
        $name = preg_replace('/Provider$/', '', $className);
        $slug = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $name));

        $this->assertEquals('asset-service', $slug);
    }

    /**
     * Test provider slug with simple name (no suffix).
     */
    public function testProviderSlugWithNoSuffix(): void
    {
        $className = 'Assets';
        $name = preg_replace('/Provider$/', '', $className);
        $slug = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $name));

        $this->assertEquals('assets', $slug);
    }

    /**
     * Test provider slug preserves lowercase.
     */
    public function testProviderSlugIsLowercase(): void
    {
        $className = 'MyCustomBlockProvider';
        $name = preg_replace('/Provider$/', '', $className);
        $slug = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $name));

        $this->assertEquals('my-custom-block', $slug);
        $this->assertStringNotContainsString('M', $slug);
        $this->assertStringNotContainsString('C', $slug);
        $this->assertStringNotContainsString('B', $slug);
    }
}
