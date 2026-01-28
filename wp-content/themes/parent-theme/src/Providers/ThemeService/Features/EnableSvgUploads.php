<?php

namespace ParentTheme\Providers\ThemeService\Features;

use ParentTheme\Contracts\Registrable;

/**
 * Enables SVG uploads in the WordPress media library.
 *
 * Adds SVG to allowed mime types and provides basic sanitization
 * to remove potentially harmful content.
 */
class EnableSvgUploads implements Registrable
{
    public function register(): void
    {
        add_filter('upload_mimes', [$this, 'addSvgMimeType']);
        add_filter('wp_check_filetype_and_ext', [$this, 'fixSvgMimeType'], 10, 5);
        add_filter('wp_handle_upload_prefilter', [$this, 'sanitizeSvg']);
    }

    /**
     * Add SVG to allowed mime types.
     *
     * @param array<string, string> $mimes Allowed mime types.
     * @return array<string, string>
     */
    public function addSvgMimeType(array $mimes): array
    {
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
    public function fixSvgMimeType(array $data, string $file, string $filename, ?array $mimes, $real_mime): array
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
     * Sanitize SVG content.
     *
     * Removes scripts, event handlers, and other potentially harmful content.
     *
     * @param string $content SVG content.
     * @return string|false Sanitized content or false on failure.
     */
    protected function sanitizeSvgContent(string $content)
    {
        // Check for valid XML
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;

        if (!$dom->loadXML($content)) {
            libxml_clear_errors();
            return false;
        }

        libxml_clear_errors();

        // Remove script elements
        $scripts = $dom->getElementsByTagName('script');
        while ($scripts->length > 0) {
            $scripts->item(0)->parentNode->removeChild($scripts->item(0));
        }

        // Remove event handler attributes (onclick, onload, etc.)
        $xpath = new \DOMXPath($dom);
        $elements = $xpath->query('//*[@*[starts-with(name(), "on")]]');

        foreach ($elements as $element) {
            $attributes = $element->attributes;
            $toRemove = [];

            foreach ($attributes as $attr) {
                if (strpos($attr->name, 'on') === 0) {
                    $toRemove[] = $attr->name;
                }
            }

            foreach ($toRemove as $name) {
                $element->removeAttribute($name);
            }
        }

        // Remove href attributes with javascript:
        $hrefs = $xpath->query('//*[@href]');
        foreach ($hrefs as $element) {
            $href = $element->getAttribute('href');
            if (stripos($href, 'javascript:') !== false) {
                $element->removeAttribute('href');
            }
        }

        // Remove xlink:href with javascript:
        $xlinks = $xpath->query('//*[@xlink:href]');
        foreach ($xlinks as $element) {
            $href = $element->getAttribute('xlink:href');
            if (stripos($href, 'javascript:') !== false) {
                $element->removeAttribute('xlink:href');
            }
        }

        return $dom->saveXML();
    }
}
