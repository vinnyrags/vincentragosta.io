<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop\Hooks;

use Mythus\Contracts\Hook;

/**
 * Saves PNG intermediate image sizes as JPEG.
 *
 * Card art comes in as PNG, which compresses poorly for detail-rich
 * photographic content — a 600x836 PNG can be ~750 KB, barely smaller
 * than the 734x1024 original. JPEG at quality 85 drops the same image
 * to ~80–150 KB. Originals stay PNG so the source of truth is intact;
 * only sub-sizes (the variants we serve through next/image) get the
 * JPEG treatment.
 */
class PngSubsizesAsJpeg implements Hook
{
    public function register(): void
    {
        add_filter('image_editor_output_format', [$this, 'forceJpegForSubsizes'], 10, 3);
    }

    /**
     * @param array<string, string> $formats
     * @return array<string, string>
     */
    public function forceJpegForSubsizes(array $formats, ?string $filename, string $mimeType): array
    {
        if ($mimeType !== 'image/png') {
            return $formats;
        }

        // WordPress invokes this filter with a null filename only when the
        // editor's `make_subsize()` is generating an intermediate. Calls with
        // an explicit filename are for the original (or its scaled variant) —
        // those keep their PNG format.
        if ($filename === null) {
            $formats['image/png'] = 'image/jpeg';
        }

        return $formats;
    }
}
