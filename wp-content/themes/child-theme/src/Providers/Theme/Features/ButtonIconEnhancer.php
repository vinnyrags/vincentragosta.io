<?php

namespace ChildTheme\Providers\Theme\Features;

use ParentTheme\Services\IconService;
use ParentTheme\Providers\Contracts\Registrable;
use DOMDocument;
use DOMXPath;

/**
 * Enhances the core/button block with icon support on the frontend.
 *
 * Uses DOMDocument for robust HTML manipulation.
 */
class ButtonIconEnhancer implements Registrable
{
    public function register(): void
    {
        add_filter('render_block_core/button', [$this, 'render'], 10, 2);
    }

    /**
     * Filter the button block output to add icons.
     */
    public function render(string $content, array $block): string
    {
        if (!$this->shouldEnhance($block)) {
            return $content;
        }

        $icon = new IconService($block['attrs']['selectedIcon']);
        if (!$icon->exists()) {
            return $content;
        }

        $position = $block['attrs']['iconPosition'] ?? 'right';

        return $this->enhanceButton($content, (string) $icon, $position);
    }

    /**
     * Check if this button should be enhanced with an icon.
     */
    private function shouldEnhance(array $block): bool
    {
        return isset($block['blockName'])
            && $block['blockName'] === 'core/button'
            && !empty($block['attrs']['selectedIcon']);
    }

    /**
     * Enhance the button HTML with icon using DOMDocument.
     */
    private function enhanceButton(string $content, string $svg, string $position): string
    {
        $dom = $this->createDom($content);
        if (!$dom) {
            return $content;
        }

        $xpath = new DOMXPath($dom);

        $this->addWrapperClasses($xpath, $position);
        $this->insertIcon($dom, $xpath, $svg, $position);

        return $this->getInnerHtml($dom);
    }

    /**
     * Create a DOMDocument from HTML content.
     */
    private function createDom(string $content): ?DOMDocument
    {
        $dom = new DOMDocument();

        $wrapped = '<div id="__wrapper__">' . $content . '</div>';
        if (!@$dom->loadHTML(
            '<?xml encoding="UTF-8">' . $wrapped,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        )) {
            return null;
        }

        return $dom;
    }

    /**
     * Add icon-related classes to the button wrapper div.
     */
    private function addWrapperClasses(DOMXPath $xpath, string $position): void
    {
        $wrappers = $xpath->query("//*[contains(@class, 'wp-block-button')]");

        foreach ($wrappers as $wrapper) {
            $currentClass = $wrapper->getAttribute('class');
            $newClasses = $currentClass . ' has-icon icon-pos-' . esc_attr($position);
            $wrapper->setAttribute('class', $newClasses);
        }
    }

    /**
     * Insert the icon span into the button/link element.
     */
    private function insertIcon(DOMDocument $dom, DOMXPath $xpath, string $svg, string $position): void
    {
        $links = $xpath->query("//*[contains(@class, 'wp-block-button__link')]");

        foreach ($links as $link) {
            $iconSpan = $dom->createElement('span');
            $iconSpan->setAttribute('class', 'wp-block-button__icon');
            $iconSpan->setAttribute('aria-hidden', 'true');

            $svgNode = $this->createSvgFragment($dom, $svg);
            if ($svgNode) {
                $iconSpan->appendChild($svgNode);
            }

            if ($position === 'right') {
                $link->appendChild($iconSpan);
            } else {
                $link->insertBefore($iconSpan, $link->firstChild);
            }
        }
    }

    /**
     * Create a document fragment from SVG content.
     */
    private function createSvgFragment(DOMDocument $dom, string $svg): ?\DOMNode
    {
        $tempDom = new DOMDocument();
        if (!@$tempDom->loadHTML(
            '<?xml encoding="UTF-8"><div>' . $svg . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        )) {
            return null;
        }

        $svgElement = $tempDom->getElementsByTagName('svg')->item(0);
        if (!$svgElement) {
            return null;
        }

        return $dom->importNode($svgElement, true);
    }

    /**
     * Extract the inner HTML from the wrapper div.
     */
    private function getInnerHtml(DOMDocument $dom): string
    {
        $wrapper = $dom->getElementById('__wrapper__');
        if (!$wrapper) {
            return '';
        }

        $html = '';
        foreach ($wrapper->childNodes as $child) {
            $html .= $dom->saveHTML($child);
        }

        return $html;
    }
}
