<?php

namespace ParentTheme\Tests\Unit\Models;

use ParentTheme\Models\CropDirection;
use ParentTheme\Models\Image;
use ParentTheme\Tests\Mocks\MockImage;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Image model.
 */
class ImageTest extends TestCase
{
    // ========================
    // Fluent API return types
    // ========================

    public function testResizeReturnsSelf(): void
    {
        $image = MockImage::create();

        $result = $image->resize(800, 600);

        $this->assertSame($image, $result);
    }

    public function testSetWidthReturnsSelf(): void
    {
        $image = MockImage::create();

        $result = $image->setWidth(800);

        $this->assertSame($image, $result);
    }

    public function testSetHeightReturnsSelf(): void
    {
        $image = MockImage::create();

        $result = $image->setHeight(600);

        $this->assertSame($image, $result);
    }

    public function testSetSizeReturnsSelf(): void
    {
        $image = MockImage::create();

        $result = $image->setSize('thumbnail');

        $this->assertSame($image, $result);
    }

    public function testCropReturnsSelf(): void
    {
        $image = MockImage::create();

        $result = $image->crop(CropDirection::CENTER);

        $this->assertSame($image, $result);
    }

    public function testSetLazyReturnsSelf(): void
    {
        $image = MockImage::create();

        $result = $image->setLazy(false);

        $this->assertSame($image, $result);
    }

    public function testSetAttrReturnsSelf(): void
    {
        $image = MockImage::create();

        $result = $image->setAttr('class', 'hero-image');

        $this->assertSame($image, $result);
    }

    // ========================
    // Dimension overrides
    // ========================

    public function testWidthReturnsResizeDimensionWhenSet(): void
    {
        $image = MockImage::create();
        $image->resize(400, 300);

        $this->assertEquals(400, $image->width());
    }

    public function testHeightReturnsResizeDimensionWhenSet(): void
    {
        $image = MockImage::create();
        $image->resize(400, 300);

        $this->assertEquals(300, $image->height());
    }

    public function testWidthReturnsOriginalWhenNoResize(): void
    {
        $image = MockImage::create();

        $this->assertEquals(1200, $image->width());
    }

    public function testHeightReturnsOriginalWhenNoResize(): void
    {
        $image = MockImage::create();

        $this->assertEquals(800, $image->height());
    }

    // ========================
    // fillMissingDimension
    // ========================

    public function testFillMissingDimensionCalculatesHeight(): void
    {
        $image = MockImage::create(); // 1200x800
        $image->setWidth(600);

        $image->fillMissingDimension();

        // 600/1200 = 0.5, 800 * 0.5 = 400
        $this->assertEquals(600, $image->width());
        $this->assertEquals(400, $image->height());
    }

    public function testFillMissingDimensionCalculatesWidth(): void
    {
        $image = MockImage::create(); // 1200x800
        $image->setHeight(400);

        $image->fillMissingDimension();

        // 400/800 = 0.5, 1200 * 0.5 = 600
        $this->assertEquals(600, $image->width());
        $this->assertEquals(400, $image->height());
    }

    public function testFillMissingDimensionDoesNothingWhenBothSet(): void
    {
        $image = MockImage::create();
        $image->resize(400, 300);

        $image->fillMissingDimension();

        $this->assertEquals(400, $image->width());
        $this->assertEquals(300, $image->height());
    }

    public function testFillMissingDimensionDoesNothingWhenNoneSet(): void
    {
        $image = MockImage::create();

        $image->fillMissingDimension();

        $this->assertEquals(1200, $image->width());
        $this->assertEquals(800, $image->height());
    }

    // ========================
    // resizedSrc
    // ========================

    public function testResizedSrcReturnsOriginalWhenNoDimensionsSet(): void
    {
        $image = MockImage::create();

        $this->assertEquals('https://example.com/image.jpg', $image->resizedSrc());
    }

    public function testResizedSrcReturnsOriginalWhenDimensionsMatchOriginal(): void
    {
        $image = MockImage::create(); // 1200x800
        $image->resize(1200, 800);

        $this->assertEquals('https://example.com/image.jpg', $image->resizedSrc());
    }

    // ========================
    // shouldResize
    // ========================

    public function testShouldResizeReturnsFalseWithNoDimensions(): void
    {
        $image = MockImage::create();

        $this->assertFalse($image->shouldResize());
    }

    public function testShouldResizeReturnsFalseWhenMatchingOriginal(): void
    {
        $image = MockImage::create();
        $image->resize(1200, 800);

        $this->assertFalse($image->shouldResize());
    }

    public function testShouldResizeReturnsTrueWhenDownscaling(): void
    {
        $image = MockImage::create();
        $image->resize(600, 400);

        $this->assertTrue($image->shouldResize());
    }

    public function testShouldResizeReturnsFalseWhenUpscalingWithoutCrop(): void
    {
        $image = MockImage::create(); // 1200x800
        $image->resize(2400, 1600);

        $this->assertFalse($image->shouldResize());
    }

    public function testShouldResizeReturnsTrueWhenUpscalingWithCrop(): void
    {
        $image = MockImage::create(); // 1200x800
        $image->resize(2400, 1600)->crop(CropDirection::CENTER);

        $this->assertTrue($image->shouldResize());
    }

    // ========================
    // buildAttributes
    // ========================

    public function testBuildAttributesIncludesExpectedKeys(): void
    {
        $image = MockImage::create();

        $attrs = $image->buildAttributes();

        $this->assertArrayHasKey('src', $attrs);
        $this->assertArrayHasKey('alt', $attrs);
        $this->assertArrayHasKey('width', $attrs);
        $this->assertArrayHasKey('height', $attrs);
        $this->assertArrayHasKey('loading', $attrs);
    }

    public function testBuildAttributesIncludesCorrectValues(): void
    {
        $image = MockImage::create();

        $attrs = $image->buildAttributes();

        $this->assertEquals('https://example.com/image.jpg', $attrs['src']);
        $this->assertEquals('Test image', $attrs['alt']);
        $this->assertEquals('1200', $attrs['width']);
        $this->assertEquals('800', $attrs['height']);
        $this->assertEquals('lazy', $attrs['loading']);
    }

    public function testBuildAttributesExcludesLoadingWhenNotLazy(): void
    {
        $image = MockImage::create();
        $image->setLazy(false);

        $attrs = $image->buildAttributes();

        $this->assertArrayNotHasKey('loading', $attrs);
    }

    public function testBuildAttributesIncludesCustomAttributes(): void
    {
        $image = MockImage::create();
        $image->setAttr('class', 'hero-image');
        $image->setAttr('data-id', '42');

        $attrs = $image->buildAttributes();

        $this->assertEquals('hero-image', $attrs['class']);
        $this->assertEquals('42', $attrs['data-id']);
    }

    // ========================
    // attributesToString
    // ========================

    public function testAttributesToStringFormatsCorrectly(): void
    {
        $image = MockImage::create();

        $result = $image->attributesToString([
            'src' => 'test.jpg',
            'alt' => 'Test',
        ]);

        $this->assertEquals('src="test.jpg" alt="Test"', $result);
    }

    public function testAttributesToStringReturnsEmptyForEmptyArray(): void
    {
        $image = MockImage::create();

        $result = $image->attributesToString([]);

        $this->assertEquals('', $result);
    }

    // ========================
    // Crop direction
    // ========================

    public function testDefaultCropDirectionIsNone(): void
    {
        $image = MockImage::create();

        // With no crop set, shouldResize should return false for upscaling
        $image->resize(2400, 1600);
        $this->assertFalse($image->shouldResize());
    }

    public function testCropDefaultsToCenterWhenCalledWithoutArgs(): void
    {
        $image = MockImage::create();
        $image->resize(2400, 1600)->crop();

        // With crop set, upscaling should be allowed
        $this->assertTrue($image->shouldResize());
    }

    // ========================
    // setSize clears dimensions
    // ========================

    public function testSetSizeClearsResizeDimensions(): void
    {
        $image = MockImage::create();
        $image->resize(800, 600);
        $image->setSize('thumbnail');

        // Width/height should fall back to original since resize was cleared
        $this->assertEquals(1200, $image->width());
        $this->assertEquals(800, $image->height());
    }

    // ========================
    // Chaining
    // ========================

    public function testFluentChainingWorks(): void
    {
        $image = MockImage::create();

        $result = $image
            ->resize(800, 600)
            ->crop(CropDirection::CENTER)
            ->setLazy(false)
            ->setAttr('class', 'hero');

        $this->assertSame($image, $result);
        $this->assertEquals(800, $image->width());
        $this->assertEquals(600, $image->height());
    }
}
