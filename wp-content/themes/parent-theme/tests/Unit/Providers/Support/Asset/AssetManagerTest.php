<?php

namespace ParentTheme\Tests\Unit\Providers\Support\Asset;

use WorDBless\BaseTestCase;
use ParentTheme\Providers\Support\Asset\AssetManager;

/**
 * Unit tests for the AssetManager class.
 */
class AssetManagerTest extends BaseTestCase
{
    /**
     * Test that slugify removes Provider suffix and converts to kebab-case.
     */
    public function testSlugifyRemovesProviderSuffix(): void
    {
        $this->assertEquals('block', AssetManager::slugify('BlockProvider'));
    }

    /**
     * Test slugify with AssetProvider.
     */
    public function testSlugifyForAssetProvider(): void
    {
        $this->assertEquals('asset', AssetManager::slugify('AssetProvider'));
    }

    /**
     * Test slugify with simple name (no suffix).
     */
    public function testSlugifyWithNoSuffix(): void
    {
        $this->assertEquals('assets', AssetManager::slugify('Assets'));
    }

    /**
     * Test slugify preserves lowercase.
     */
    public function testSlugifyIsLowercase(): void
    {
        $slug = AssetManager::slugify('MyCustomBlockProvider');

        $this->assertEquals('my-custom-block', $slug);
        $this->assertStringNotContainsString('M', $slug);
        $this->assertStringNotContainsString('C', $slug);
        $this->assertStringNotContainsString('B', $slug);
    }

    /**
     * Test slugify with ThemeProvider (current naming convention).
     */
    public function testSlugifyForThemeProvider(): void
    {
        $this->assertEquals('theme', AssetManager::slugify('ThemeProvider'));
    }

    /**
     * Test slugify with ProjectProvider.
     */
    public function testSlugifyForProjectProvider(): void
    {
        $this->assertEquals('project', AssetManager::slugify('ProjectProvider'));
    }

    /**
     * Test constructor sets properties correctly via path methods.
     */
    public function testConstructorSetsProperties(): void
    {
        $manager = new AssetManager('theme', '/path/to/dist', 'https://example.com/dist');

        $this->assertEquals('https://example.com/dist/css/style.css', $manager->getStyleUri('style.css'));
        $this->assertEquals('https://example.com/dist/js/theme/script.js', $manager->getScriptUri('script.js'));
    }

    /**
     * Test getStylePath returns null when file doesn't exist.
     */
    public function testGetStylePathReturnsNullWhenMissing(): void
    {
        $manager = new AssetManager('theme', '/nonexistent/dist', 'https://example.com/dist');

        $this->assertNull($manager->getStylePath('nonexistent.css'));
    }

    /**
     * Test getScriptPath returns null when file doesn't exist.
     */
    public function testGetScriptPathReturnsNullWhenMissing(): void
    {
        $manager = new AssetManager('theme', '/nonexistent/dist', 'https://example.com/dist');

        $this->assertNull($manager->getScriptPath('nonexistent.js'));
    }

    /**
     * Test hasStyle returns false when file doesn't exist.
     */
    public function testHasStyleReturnsFalseWhenMissing(): void
    {
        $manager = new AssetManager('theme', '/nonexistent/dist', 'https://example.com/dist');

        $this->assertFalse($manager->hasStyle('nonexistent.css'));
    }

    /**
     * Test hasScript returns false when file doesn't exist.
     */
    public function testHasScriptReturnsFalseWhenMissing(): void
    {
        $manager = new AssetManager('theme', '/nonexistent/dist', 'https://example.com/dist');

        $this->assertFalse($manager->hasScript('nonexistent.js'));
    }

    /**
     * Test enqueueDistStyle does not enqueue when file doesn't exist.
     */
    public function testEnqueueDistStyleSkipsWhenFileMissing(): void
    {
        $manager = new AssetManager('theme', '/nonexistent/dist', 'https://example.com/dist');

        $manager->enqueueDistStyle('test-handle', 'blocks/style-index.css');

        $this->assertFalse(wp_style_is('test-handle', 'enqueued'));
    }

    /**
     * Test enqueueDistScript does not enqueue when file doesn't exist.
     */
    public function testEnqueueDistScriptSkipsWhenFileMissing(): void
    {
        $manager = new AssetManager('theme', '/nonexistent/dist', 'https://example.com/dist');

        $manager->enqueueDistScript('test-handle', 'js/frontend.js');

        $this->assertFalse(wp_script_is('test-handle', 'enqueued'));
    }

    /**
     * Test enqueueManifestScript returns early when asset file doesn't exist.
     */
    public function testEnqueueManifestScriptSkipsWhenAssetFileMissing(): void
    {
        $manager = new AssetManager('theme', '/nonexistent/dist', 'https://example.com/dist');

        $manager->enqueueManifestScript('test-handle', 'blocks/index.js');

        $this->assertFalse(wp_script_is('test-handle', 'enqueued'));
    }

    /**
     * Test enqueueDistStyle constructs correct URI from dist-relative path.
     */
    public function testEnqueueDistStyleConstructsCorrectUri(): void
    {
        $distPath = sys_get_temp_dir() . '/asset-manager-test-dist';
        @mkdir($distPath . '/blocks', 0777, true);
        file_put_contents($distPath . '/blocks/style-index.css', 'body{}');

        $manager = new AssetManager('theme', $distPath, 'https://example.com/dist');
        $manager->enqueueDistStyle('test-blocks-style', 'blocks/style-index.css');

        $this->assertTrue(wp_style_is('test-blocks-style', 'enqueued'));

        // Clean up
        @unlink($distPath . '/blocks/style-index.css');
        @rmdir($distPath . '/blocks');
        @rmdir($distPath);
    }

    /**
     * Test enqueueDistScript constructs correct URI from dist-relative path.
     */
    public function testEnqueueDistScriptConstructsCorrectUri(): void
    {
        $distPath = sys_get_temp_dir() . '/asset-manager-test-dist';
        @mkdir($distPath . '/js', 0777, true);
        file_put_contents($distPath . '/js/frontend.js', '// js');

        $manager = new AssetManager('theme', $distPath, 'https://example.com/dist');
        $manager->enqueueDistScript('test-frontend-js', 'js/frontend.js');

        $this->assertTrue(wp_script_is('test-frontend-js', 'enqueued'));

        // Clean up
        @unlink($distPath . '/js/frontend.js');
        @rmdir($distPath . '/js');
        @rmdir($distPath);
    }
}
