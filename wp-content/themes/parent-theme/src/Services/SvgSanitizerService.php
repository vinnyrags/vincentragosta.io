<?php

declare(strict_types=1);

namespace ParentTheme\Services;

use enshrined\svgSanitize\Sanitizer;

/**
 * SVG sanitization service.
 *
 * Wraps the enshrined/svg-sanitize library with preconfigured
 * security settings for safe SVG handling.
 */
class SvgSanitizerService
{
    private Sanitizer $sanitizer;

    public function __construct()
    {
        $this->sanitizer = new Sanitizer();
        $this->sanitizer->removeRemoteReferences(true);
        $this->sanitizer->removeXMLTag(true);
        $this->sanitizer->minify(true);
    }

    /**
     * Sanitize SVG content.
     *
     * @param string $content Raw SVG content.
     * @return string|false Sanitized content or false on failure.
     */
    public function sanitize(string $content): string|false
    {
        $clean = $this->sanitizer->sanitize($content);

        if ($clean === false || $clean === '') {
            return false;
        }

        return $clean;
    }
}
