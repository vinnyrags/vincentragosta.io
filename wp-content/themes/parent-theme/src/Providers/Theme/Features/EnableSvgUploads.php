<?php

declare(strict_types=1);

namespace ParentTheme\Providers\Theme\Features;

use ParentTheme\Providers\Contracts\Feature;
use ParentTheme\Services\SvgSanitizerService;

/**
 * Enables SVG uploads in the WordPress media library.
 *
 * Adds SVG to allowed mime types for administrators only and provides
 * comprehensive sanitization via enshrined/svg-sanitize to remove
 * potentially harmful content including XSS vectors.
 */
class EnableSvgUploads implements Feature
{
    /**
     * Create the feature with its sanitizer dependency.
     *
     * @param SvgSanitizerService $sanitizer Handles SVG content sanitization.
     */
    public function __construct(
        private readonly SvgSanitizerService $sanitizer,
    ) {}

    public function register(): void
    {
        add_filter('upload_mimes', [$this, 'addSvgMimeType']);
        add_filter('wp_check_filetype_and_ext', [$this, 'fixSvgMimeType'], 10, 5);
        add_filter('wp_handle_upload_prefilter', [$this, 'sanitizeSvg']);
    }

    /**
     * Add SVG to allowed mime types for administrators only.
     *
     * SVG files can contain malicious content. Even with sanitization,
     * restricting uploads to administrators provides defense in depth.
     *
     * @param array<string, string> $mimes Allowed mime types.
     * @return array<string, string>
     */
    public function addSvgMimeType(array $mimes): array
    {
        if (!current_user_can('manage_options')) {
            return $mimes;
        }

        $mimes['svg'] = 'image/svg+xml';
        $mimes['svgz'] = 'image/svg+xml';

        return $mimes;
    }

    /**
     * Fix SVG mime type detection.
     *
     * WordPress sometimes fails to detect SVG mime types correctly.
     *
     * @param array<string, string|false> $data File data.
     * @param string $file Full path to the file.
     * @param string $filename The name of the file.
     * @param array<string, string>|null $mimes Allowed mime types.
     * @param string|false $real_mime Real mime type from finfo.
     * @return array<string, string|false>
     */
    public function fixSvgMimeType(array $data, string $file, string $filename, ?array $mimes, string|false $real_mime): array
    {
        if (empty($data['ext']) || empty($data['type'])) {
            $ext = pathinfo($filename, PATHINFO_EXTENSION);

            if ($ext === 'svg') {
                $data['ext'] = 'svg';
                $data['type'] = 'image/svg+xml';
            }
        }

        return $data;
    }

    /**
     * Sanitize SVG uploads by removing potentially harmful content.
     *
     * @param array<string, string> $file Upload file data.
     * @return array<string, string>
     */
    public function sanitizeSvg(array $file): array
    {
        if ($file['type'] !== 'image/svg+xml') {
            return $file;
        }

        $content = file_get_contents($file['tmp_name']);

        if ($content === false) {
            $file['error'] = 'Could not read SVG file.';
            return $file;
        }

        $sanitized = $this->sanitizeSvgContent($content);

        if ($sanitized === false) {
            $file['error'] = 'SVG file contains invalid or potentially harmful content.';
            return $file;
        }

        file_put_contents($file['tmp_name'], $sanitized);

        return $file;
    }

    /**
     * Sanitize SVG content using the injected SvgSanitizerService.
     *
     * The service handles all known SVG attack vectors including:
     * - <script> elements
     * - Event handler attributes (onclick, onload, etc.)
     * - <foreignObject> elements (embeds arbitrary HTML)
     * - <use> with external references
     * - <style> elements with malicious CSS
     * - <embed>, <object>, <iframe> elements
     * - javascript: and data: URI schemes
     * - CSS url() with javascript:
     *
     * @param string $content SVG content.
     * @return string|false Sanitized content or false on failure.
     */
    protected function sanitizeSvgContent(string $content): string|false
    {
        return $this->sanitizer->sanitize($content);
    }
}
