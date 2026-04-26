<?php

declare(strict_types=1);

namespace ChildTheme\Tests\Unit\Providers\Shop\Hooks;

use ChildTheme\Providers\Shop\Hooks\PngSubsizesAsJpeg;
use Mythus\Contracts\Hook;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the PngSubsizesAsJpeg hook.
 *
 * The non-obvious invariant under test: WordPress invokes the
 * `image_editor_output_format` filter with a NULL filename only when the
 * editor is generating a sub-size via `make_subsize()`. Calls with an
 * explicit filename are for the original (or its `-scaled` variant).
 * The hook keys off that distinction so original PNGs stay PNG and
 * sub-sizes are forced to JPEG.
 */
class PngSubsizesAsJpegTest extends TestCase
{
    public function testImplementsHookInterface(): void
    {
        $this->assertTrue(
            is_subclass_of(PngSubsizesAsJpeg::class, Hook::class),
        );
    }

    public function testRegisterAddsImageEditorOutputFormatFilter(): void
    {
        $hook = new PngSubsizesAsJpeg();
        $hook->register();

        $this->assertIsInt(
            has_filter(
                'image_editor_output_format',
                [$hook, 'forceJpegForSubsizes'],
            ),
        );
    }

    public function testNullFilenameWithPngMimeMapsToJpeg(): void
    {
        $hook = new PngSubsizesAsJpeg();

        $result = $hook->forceJpegForSubsizes([], null, 'image/png');

        $this->assertSame(['image/png' => 'image/jpeg'], $result);
    }

    public function testExplicitFilenameWithPngMimeStaysPng(): void
    {
        $hook = new PngSubsizesAsJpeg();

        $result = $hook->forceJpegForSubsizes(
            [],
            '/uploads/2026/04/card_hires.png',
            'image/png',
        );

        $this->assertSame([], $result);
    }

    public function testSubsizeFilenameWithExplicitPathStaysPng(): void
    {
        // Even sub-size paths shouldn't trigger the swap when WordPress passes
        // them explicitly — only the null-filename calls from make_subsize do.
        $hook = new PngSubsizesAsJpeg();

        $result = $hook->forceJpegForSubsizes(
            [],
            '/uploads/2026/04/card_hires-500x698.png',
            'image/png',
        );

        $this->assertSame([], $result);
    }

    public function testNonPngMimeIsUnchanged(): void
    {
        $hook = new PngSubsizesAsJpeg();

        $result = $hook->forceJpegForSubsizes([], null, 'image/jpeg');

        $this->assertSame([], $result);
    }

    public function testHeicDefaultsArePreserved(): void
    {
        // wp_get_image_editor_output_format() seeds HEIC defaults that
        // downstream code relies on. The hook should add to — not replace —
        // any existing format mapping when the PNG/null-filename branch fires.
        $hook = new PngSubsizesAsJpeg();

        $defaults = [
            'image/heic' => 'image/jpeg',
            'image/heif' => 'image/jpeg',
        ];
        $result = $hook->forceJpegForSubsizes($defaults, null, 'image/png');

        $this->assertSame(
            [
                'image/heic' => 'image/jpeg',
                'image/heif' => 'image/jpeg',
                'image/png'  => 'image/jpeg',
            ],
            $result,
        );
    }

    public function testHeicDefaultsArePreservedWhenNotPng(): void
    {
        $hook = new PngSubsizesAsJpeg();

        $defaults = [
            'image/heic' => 'image/jpeg',
            'image/heif' => 'image/jpeg',
        ];
        $result = $hook->forceJpegForSubsizes($defaults, null, 'image/jpeg');

        $this->assertSame($defaults, $result);
    }
}
