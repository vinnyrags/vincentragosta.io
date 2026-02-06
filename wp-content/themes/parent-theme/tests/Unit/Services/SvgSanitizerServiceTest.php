<?php

namespace ParentTheme\Tests\Unit\Services;

use ParentTheme\Services\SvgSanitizerService;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for SvgSanitizerService.
 */
class SvgSanitizerServiceTest extends TestCase
{
    private SvgSanitizerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SvgSanitizerService();
    }

    /**
     * Test that sanitize removes script tags.
     */
    public function testSanitizeRemovesScriptTags(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert("xss")</script><rect width="10" height="10"/></svg>';
        $clean = $this->service->sanitize($svg);

        $this->assertIsString($clean);
        $this->assertStringNotContainsString('script', $clean);
        $this->assertStringContainsString('rect', $clean);
    }

    /**
     * Test that sanitize removes event handlers.
     */
    public function testSanitizeRemovesEventHandlers(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" onload="evil()"><rect onclick="bad()" width="10" height="10"/></svg>';
        $clean = $this->service->sanitize($svg);

        $this->assertIsString($clean);
        $this->assertStringNotContainsString('onload', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
    }

    /**
     * Test that sanitize returns false for invalid SVG.
     */
    public function testSanitizeReturnsFalseForInvalidSvg(): void
    {
        $result = $this->service->sanitize('not valid svg at all');

        $this->assertFalse($result);
    }

    /**
     * Test that sanitize returns false for empty content.
     */
    public function testSanitizeReturnsFalseForEmptyContent(): void
    {
        $result = $this->service->sanitize('');

        $this->assertFalse($result);
    }

    /**
     * Test that sanitize preserves valid SVG structure.
     */
    public function testSanitizePreservesValidSvgStructure(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M0 0h24v24H0z"/></svg>';
        $clean = $this->service->sanitize($svg);

        $this->assertIsString($clean);
        $this->assertStringContainsString('svg', $clean);
        $this->assertStringContainsString('path', $clean);
        $this->assertStringContainsString('viewBox', $clean);
    }

    /**
     * Test that sanitize removes foreignObject elements.
     */
    public function testSanitizeRemovesForeignObject(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><foreignObject><div>HTML content</div></foreignObject><rect width="10" height="10"/></svg>';
        $clean = $this->service->sanitize($svg);

        $this->assertIsString($clean);
        $this->assertStringNotContainsString('foreignObject', $clean);
        $this->assertStringNotContainsString('div', $clean);
    }

    /**
     * Test that service can be instantiated.
     */
    public function testServiceCanBeInstantiated(): void
    {
        $service = new SvgSanitizerService();

        $this->assertInstanceOf(SvgSanitizerService::class, $service);
    }

    /**
     * Test that sanitize minifies output.
     */
    public function testSanitizeMinifiesOutput(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg">
            <!-- comment -->
            <rect width="10" height="10"/>
        </svg>';
        $clean = $this->service->sanitize($svg);

        $this->assertIsString($clean);
        // Comments should be removed
        $this->assertStringNotContainsString('<!--', $clean);
    }
}
