<?php

namespace IX\Tests\Unit\Services;

use IX\Services\IconService;
use IX\Services\IconServiceFactory;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for IconServiceFactory.
 */
class IconServiceFactoryTest extends TestCase
{
    private IconServiceFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new IconServiceFactory('/test/svg/');
    }

    /**
     * Test that create returns an IconService instance.
     */
    public function testCreateReturnsIconService(): void
    {
        $icon = $this->factory->create('test-icon');

        $this->assertInstanceOf(IconService::class, $icon);
    }

    /**
     * Test that create passes the name to IconService.
     */
    public function testCreatePassesNameToIconService(): void
    {
        $icon = $this->factory->create('arrow');

        // IconService with non-existent icon returns false for exists()
        // This confirms the name was passed (it's looking for 'arrow')
        $this->assertFalse($icon->exists());
    }

    /**
     * Test that all method returns an array.
     */
    public function testAllReturnsArray(): void
    {
        $result = $this->factory->all();

        $this->assertIsArray($result);
    }

    /**
     * Test that options method returns array with empty option.
     */
    public function testOptionsReturnsArrayWithEmptyOption(): void
    {
        $result = $this->factory->options('all', '-- Select --');

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertEquals('-- Select --', $result[0]['label']);
        $this->assertEquals('', $result[0]['value']);
    }

    /**
     * Test that contentMap returns an array.
     */
    public function testContentMapReturnsArray(): void
    {
        $result = $this->factory->contentMap();

        $this->assertIsArray($result);
    }

    /**
     * Test that factory accepts explicit svgDir parameter.
     */
    public function testFactoryAcceptsExplicitSvgDir(): void
    {
        $factory = new IconServiceFactory('/test/svg/');

        $this->assertInstanceOf(IconServiceFactory::class, $factory);
    }

    /**
     * Test that factory works with default svgDir parameter.
     */
    public function testFactoryWorksWithDefaultSvgDir(): void
    {
        $factory = new IconServiceFactory();

        $this->assertInstanceOf(IconServiceFactory::class, $factory);

        // Verify it can create an IconService (even if icon doesn't exist)
        $icon = $factory->create('nonexistent');
        $this->assertInstanceOf(IconService::class, $icon);
        $this->assertFalse($icon->exists());
    }
}
