<?php

namespace ParentTheme\Tests\Unit\Services;

use ParentTheme\Services\IconService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Unit tests for IconService.
 *
 * These tests focus on the pure PHP logic without WordPress dependencies.
 */
class IconServiceTest extends TestCase
{
    /**
     * Test that sanitizeName removes directory traversal attempts.
     */
    public function testSanitizeNameRemovesDirectoryTraversal(): void
    {
        $service = new IconService('test', '/test/svg/');
        $method = $this->getPrivateMethod($service, 'sanitizeName');

        $this->assertEquals('icon', $method->invoke($service, '../../../icon'));
        $this->assertEquals('icon', $method->invoke($service, '../../icon.svg'));
        $this->assertEquals('icon', $method->invoke($service, '/etc/passwd/../icon'));
    }

    /**
     * Test that sanitizeName removes .svg extension.
     */
    public function testSanitizeNameRemovesSvgExtension(): void
    {
        $service = new IconService('test', '/test/svg/');
        $method = $this->getPrivateMethod($service, 'sanitizeName');

        $this->assertEquals('arrow', $method->invoke($service, 'arrow.svg'));
        $this->assertEquals('arrow', $method->invoke($service, 'arrow.SVG'));
        $this->assertEquals('arrow', $method->invoke($service, 'arrow'));
    }

    /**
     * Test that sanitizeContent removes script tags.
     */
    public function testSanitizeContentRemovesScriptTags(): void
    {
        $service = new IconService('test', '/test/svg/');
        $method = $this->getPrivateMethod($service, 'sanitizeContent');

        $dirty = '<svg><script>alert("xss")</script><path d="M0 0"/></svg>';
        $clean = $method->invoke($service, $dirty);

        $this->assertStringNotContainsString('script', $clean);
        $this->assertStringContainsString('<path', $clean);
    }

    /**
     * Test that sanitizeContent removes event handlers.
     */
    public function testSanitizeContentRemovesEventHandlers(): void
    {
        $service = new IconService('test', '/test/svg/');
        $method = $this->getPrivateMethod($service, 'sanitizeContent');

        $dirty = '<svg onload="alert(1)" onclick="evil()"><path d="M0 0"/></svg>';
        $clean = $method->invoke($service, $dirty);

        $this->assertStringNotContainsString('onload', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringContainsString('<svg', $clean);
    }

    /**
     * Test that sanitizeContent removes XML declaration.
     */
    public function testSanitizeContentRemovesXmlDeclaration(): void
    {
        $service = new IconService('test', '/test/svg/');
        $method = $this->getPrivateMethod($service, 'sanitizeContent');

        $dirty = '<?xml version="1.0" encoding="UTF-8"?><svg><path d="M0 0"/></svg>';
        $clean = $method->invoke($service, $dirty);

        $this->assertStringNotContainsString('<?xml', $clean);
        $this->assertStringStartsWith('<svg', $clean);
    }

    /**
     * Test that sanitizeContent removes DOCTYPE.
     */
    public function testSanitizeContentRemovesDoctype(): void
    {
        $service = new IconService('test', '/test/svg/');
        $method = $this->getPrivateMethod($service, 'sanitizeContent');

        $dirty = '<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd"><svg><path d="M0 0"/></svg>';
        $clean = $method->invoke($service, $dirty);

        $this->assertStringNotContainsString('DOCTYPE', $clean);
        $this->assertStringStartsWith('<svg', $clean);
    }

    /**
     * Test that withClass adds a CSS class.
     */
    public function testWithClassAddsClass(): void
    {
        $service = IconService::get('test', '/test/svg/')->withClass('icon-lg');
        $attributes = $this->getPrivateProperty($service, 'attributes');

        $this->assertArrayHasKey('class', $attributes);
        $this->assertEquals('icon-lg', $attributes['class']);
    }

    /**
     * Test that withClass appends to existing classes.
     */
    public function testWithClassAppendsToExistingClasses(): void
    {
        $service = IconService::get('test', '/test/svg/')
            ->withClass('icon-lg')
            ->withClass('icon-primary');

        $attributes = $this->getPrivateProperty($service, 'attributes');

        $this->assertStringContainsString('icon-lg', $attributes['class']);
        $this->assertStringContainsString('icon-primary', $attributes['class']);
    }

    /**
     * Test that withAttributes merges attributes.
     */
    public function testWithAttributesMergesAttributes(): void
    {
        $service = IconService::get('test', '/test/svg/')
            ->withClass('icon-lg')
            ->withAttributes(['aria-hidden' => 'true', 'role' => 'img']);

        $attributes = $this->getPrivateProperty($service, 'attributes');

        $this->assertArrayHasKey('class', $attributes);
        $this->assertArrayHasKey('aria-hidden', $attributes);
        $this->assertArrayHasKey('role', $attributes);
        $this->assertEquals('true', $attributes['aria-hidden']);
        $this->assertEquals('img', $attributes['role']);
    }

    /**
     * Test that withAttributes overwrites existing attributes.
     */
    public function testWithAttributesOverwritesExisting(): void
    {
        $service = IconService::get('test', '/test/svg/')
            ->withAttributes(['data-id' => '1'])
            ->withAttributes(['data-id' => '2']);

        $attributes = $this->getPrivateProperty($service, 'attributes');

        $this->assertEquals('2', $attributes['data-id']);
    }

    /**
     * Test static get factory method returns instance.
     */
    public function testGetReturnsInstance(): void
    {
        $service = IconService::get('test', '/test/svg/');

        $this->assertInstanceOf(IconService::class, $service);
    }

    /**
     * Test that non-existent icon returns false for exists().
     */
    public function testExistsReturnsFalseForMissingIcon(): void
    {
        $service = new IconService('definitely-does-not-exist-12345', '/test/svg/');

        $this->assertFalse($service->exists());
    }

    /**
     * Test that non-existent icon returns null for getType().
     */
    public function testGetTypeReturnsNullForMissingIcon(): void
    {
        $service = new IconService('definitely-does-not-exist-12345', '/test/svg/');

        $this->assertNull($service->getType());
    }

    /**
     * Test that non-existent icon renders empty string.
     */
    public function testRenderReturnsEmptyStringForMissingIcon(): void
    {
        $service = new IconService('definitely-does-not-exist-12345', '/test/svg/');

        $this->assertEquals('', $service->render());
        $this->assertEquals('', (string) $service);
    }

    /**
     * Test that applyAttributes adds class attribute to SVG.
     */
    public function testApplyAttributesAddsClassToSvg(): void
    {
        $service = new IconService('test', '/test/svg/');
        $method = $this->getPrivateMethod($service, 'applyAttributes');

        // Set type to 'svg' and attributes
        $this->setPrivateProperty($service, 'type', 'svg');
        $this->setPrivateProperty($service, 'attributes', ['class' => 'my-icon']);

        $content = '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0"/></svg>';
        $result = $method->invoke($service, $content);

        $this->assertStringContainsString('class="my-icon"', $result);
        $this->assertStringStartsWith('<svg', $result);
    }

    /**
     * Test that applyAttributes preserves existing SVG structure.
     */
    public function testApplyAttributesPreservesExistingStructure(): void
    {
        $service = new IconService('test', '/test/svg/');
        $method = $this->getPrivateMethod($service, 'applyAttributes');

        $this->setPrivateProperty($service, 'type', 'svg');
        $this->setPrivateProperty($service, 'attributes', ['aria-hidden' => 'true', 'role' => 'img']);

        $content = '<svg viewBox="0 0 24 24"><path d="M0 0"/></svg>';
        $result = $method->invoke($service, $content);

        $this->assertStringContainsString('viewBox="0 0 24 24"', $result);
        $this->assertStringContainsString('<path', $result);
        $this->assertStringContainsString('aria-hidden="true"', $result);
        $this->assertStringContainsString('role="img"', $result);
    }

    /**
     * Test that applyAttributes wraps content without SVG tag.
     */
    public function testApplyAttributesWrapsContentWithoutSvgTag(): void
    {
        $service = new IconService('test', '/test/svg/');
        $method = $this->getPrivateMethod($service, 'applyAttributes');

        $this->setPrivateProperty($service, 'type', 'icon');
        $this->setPrivateProperty($service, 'attributes', ['class' => 'my-icon']);

        // Content without SVG wrapper
        $content = '<path d="M0 0"/><circle cx="10" cy="10" r="5"/>';
        $result = $method->invoke($service, $content);

        $this->assertStringStartsWith('<svg', $result);
        $this->assertStringContainsString('class="my-icon"', $result);
        $this->assertStringContainsString('xmlns="http://www.w3.org/2000/svg"', $result);
        $this->assertStringContainsString('<path', $result);
    }

    /**
     * Test that applyAttributes returns content unchanged when no attributes.
     */
    public function testApplyAttributesReturnsUnchangedWhenNoAttributes(): void
    {
        $service = new IconService('test', '/test/svg/');
        $method = $this->getPrivateMethod($service, 'applyAttributes');

        $this->setPrivateProperty($service, 'type', 'svg');
        $this->setPrivateProperty($service, 'attributes', []);

        $content = '<svg><path d="M0 0"/></svg>';
        $result = $method->invoke($service, $content);

        $this->assertEquals($content, $result);
    }

    /**
     * Test that sanitizeName allows subdirectory paths.
     */
    public function testSanitizeNameAllowsSubdirectoryPaths(): void
    {
        $service = new IconService('test', '/test/svg/');
        $method = $this->getPrivateMethod($service, 'sanitizeName');

        $this->assertEquals('squiggle/squiggle-1', $method->invoke($service, 'squiggle/squiggle-1'));
        $this->assertEquals('subdir/icon', $method->invoke($service, 'subdir/icon.svg'));
    }

    /**
     * Test that sanitizeName normalizes backslashes.
     */
    public function testSanitizeNameNormalizesBackslashes(): void
    {
        $service = new IconService('test', '/test/svg/');
        $method = $this->getPrivateMethod($service, 'sanitizeName');

        $this->assertEquals('subdir/icon', $method->invoke($service, 'subdir\\icon'));
    }

    /**
     * Test that sanitizeName handles absolute paths by extracting basename.
     */
    public function testSanitizeNameHandlesAbsolutePaths(): void
    {
        $service = new IconService('test', '/test/svg/');
        $method = $this->getPrivateMethod($service, 'sanitizeName');

        $this->assertEquals('icon', $method->invoke($service, '/absolute/path/to/icon'));
    }

    /**
     * Test that themeDirs returns single directory when no child theme.
     *
     * In WorDBless, get_stylesheet_directory() and get_template_directory()
     * return the same value, simulating a standalone (non-child) theme.
     */
    public function testThemeDirsReturnsSingleDirWithoutChildTheme(): void
    {
        $method = $this->getPrivateStaticMethod(IconService::class, 'themeDirs');
        $dirs = $method->invoke(null);

        $this->assertCount(1, $dirs);
        $this->assertEquals(get_stylesheet_directory(), $dirs[0]);
    }

    /**
     * Test that themeDirs does not duplicate when stylesheet equals template.
     */
    public function testThemeDirsNoDuplicatesWhenSameDir(): void
    {
        $method = $this->getPrivateStaticMethod(IconService::class, 'themeDirs');
        $dirs = $method->invoke(null);

        $this->assertCount(count(array_unique($dirs)), $dirs);
    }

    /**
     * Test that resolve checks parent theme directory for icons.
     *
     * Creates a temporary SVG in the parent theme's icon path and verifies
     * that IconService can resolve it.
     */
    public function testResolveFindsIconInParentThemeDir(): void
    {
        $themeDir = get_template_directory();
        $svgDir = '/test-icons-' . uniqid() . '/';
        $iconsDir = $themeDir . $svgDir . 'icons/';

        // Create temporary directory and SVG
        mkdir($iconsDir, 0755, true);
        file_put_contents($iconsDir . 'test-resolve.svg', '<svg><path d="M0 0"/></svg>');

        try {
            $service = new IconService('test-resolve', $svgDir);
            $this->assertTrue($service->exists());
            $this->assertEquals('icon', $service->getType());
            $this->assertStringContainsString('<path', $service->render());
        } finally {
            // Clean up
            unlink($iconsDir . 'test-resolve.svg');
            rmdir($iconsDir);
            rmdir($themeDir . $svgDir);
        }
    }

    /**
     * Test that all() finds icons from the theme directory.
     */
    public function testAllFindsIconsFromThemeDir(): void
    {
        $themeDir = get_template_directory();
        $svgDir = '/test-all-' . uniqid() . '/';
        $iconsDir = $themeDir . $svgDir . 'icons/';

        mkdir($iconsDir, 0755, true);
        file_put_contents($iconsDir . 'alpha.svg', '<svg><path d="M0 0"/></svg>');
        file_put_contents($iconsDir . 'beta.svg', '<svg><circle cx="5" cy="5" r="5"/></svg>');

        try {
            $icons = IconService::all($svgDir, 'icon');
            $names = array_column($icons, 'name');

            $this->assertContains('alpha', $names);
            $this->assertContains('beta', $names);
            $this->assertCount(2, $icons);
        } finally {
            unlink($iconsDir . 'alpha.svg');
            unlink($iconsDir . 'beta.svg');
            rmdir($iconsDir);
            rmdir($themeDir . $svgDir);
        }
    }

    /**
     * Helper to get a private static method via reflection.
     */
    private function getPrivateStaticMethod(string $className, string $methodName): \ReflectionMethod
    {
        $reflection = new ReflectionClass($className);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method;
    }

    /**
     * Helper to get a private method via reflection.
     */
    private function getPrivateMethod(object $object, string $methodName): \ReflectionMethod
    {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method;
    }

    /**
     * Helper to get a private property value via reflection.
     */
    private function getPrivateProperty(object $object, string $propertyName): mixed
    {
        $reflection = new ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        return $property->getValue($object);
    }

    /**
     * Helper to set a private property value via reflection.
     */
    private function setPrivateProperty(object $object, string $propertyName, mixed $value): void
    {
        $reflection = new ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }
}
