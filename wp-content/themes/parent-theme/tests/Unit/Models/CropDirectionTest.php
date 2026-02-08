<?php

namespace ParentTheme\Tests\Unit\Models;

use ParentTheme\Models\CropDirection;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the CropDirection enum.
 */
class CropDirectionTest extends TestCase
{
    public function testNoneHasCorrectValue(): void
    {
        $this->assertEquals('default', CropDirection::NONE->value);
    }

    public function testCenterHasCorrectValue(): void
    {
        $this->assertEquals('center', CropDirection::CENTER->value);
    }

    public function testTopHasCorrectValue(): void
    {
        $this->assertEquals('top', CropDirection::TOP->value);
    }

    public function testBottomHasCorrectValue(): void
    {
        $this->assertEquals('bottom', CropDirection::BOTTOM->value);
    }

    public function testLeftHasCorrectValue(): void
    {
        $this->assertEquals('left', CropDirection::LEFT->value);
    }

    public function testRightHasCorrectValue(): void
    {
        $this->assertEquals('right', CropDirection::RIGHT->value);
    }

    public function testCaseCountIsSix(): void
    {
        $this->assertCount(6, CropDirection::cases());
    }
}
