<?php

declare(strict_types=1);

namespace ParentTheme\Providers\Theme\Hooks;

use ParentTheme\Services\IconServiceFactory;
use Mythus\Contracts\Hook;
use DOMDocument;
use DOMXPath;

/**
 * Enhances the core/button block with icon support on the frontend.
 *
 * Uses DOMDocument for robust HTML manipulation.
 */
class ButtonIconEnhancer implements Hook
{
    /**
     * Create the enhancer with its icon factory dependency.
     *
     * @param IconServiceFactory $iconFactory Factory for resolving icon SVG content.
     */
    public function __construct(
        private readonly IconServiceFactory $iconFactory,
    ) {}

    public function register(): void
    {
        add_filter('render_block_core/button', [$this, 'render'], 10, 2);
    }

    /**
     * Filter the button block output to add icons.
     *
     * @param string $content Rendered block HTML.
     * @param array{blockName: string, attrs: array<string, mixed>} $block Parsed block data.
     */
    public function render(string $content, array $block): string
    {
        if (!$this->shouldEnhance($block)) {
            return $content;
        }

        $iconName = $block['attrs']['selectedIcon'];
        $icon = $this->iconFactory->create($iconName);
        if (!$icon->exists()) {
            return $content;
        }

        $position = $block['attrs']['iconPosition'] ?? 'right';

        return $this->enhanceButton($content, (string) $icon, $position, $iconName);
    }

    /**
     * Check if this button block has a selected icon attribute.
     *
     * @param array{blockName?: string, attrs?: array<string, mixed>} $block Parsed block data.
     */
    private function shouldEnhance(array $block): bool
    {
        return isset($block['blockName'])
            && $block['blockName'] === 'core/button'
            && !empty($block['attrs']['selectedIcon']);
    }

    /**
     * Enhance the button HTML by injecting an icon span via DOMDocument.
     *
     * @param string $content Original button block HTML.
     * @param string $svg Rendered SVG markup for the icon.
     * @param string $position Icon position relative to the label ('left' or 'right').
     * @param string $iconName The selected icon name for CSS targeting.
     */
    private function enhanceButton(string $content, string $svg, string $position, string $iconName): string
    {
        $dom = $this->createDom($content);
        if (!$dom) {
            return $content;
        }

        $xpath = new DOMXPath($dom);

        $this->addWrapperClasses($xpath, $position);
        $this->insertIcon($dom, $xpath, $svg, $position, $iconName);

        return $this->getInnerHtml($dom);
    }

    /**
     * Create a DOMDocument from HTML content wrapped in a root element.
     *
     * @param string $content Raw HTML to parse.
     * @return DOMDocument|null The parsed document, or null on parse failure.
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
     * Add icon-related CSS classes to the .wp-block-button wrapper.
     *
     * @param DOMXPath $xpath XPath instance bound to the button document.
     * @param string $position Icon position ('left' or 'right') for the directional class.
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
     * Insert an icon span into the .wp-block-button__link element.
     *
     * @param DOMDocument $dom The button document for creating new nodes.
     * @param DOMXPath $xpath XPath instance for locating the link element.
     * @param string $svg Rendered SVG markup to embed inside the icon span.
     * @param string $position 'left' prepends, 'right' appends the icon span.
     * @param string $iconName The selected icon name for CSS targeting.
     */
    private function insertIcon(DOMDocument $dom, DOMXPath $xpath, string $svg, string $position, string $iconName): void
    {
        $links = $xpath->query("//*[contains(@class, 'wp-block-button__link')]");

        foreach ($links as $link) {
            $iconSpan = $dom->createElement('span');
            $iconSpan->setAttribute('class', 'wp-block-button__icon ' . esc_attr($iconName));
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
     * Parse SVG markup and import it as a node into the target document.
     *
     * @param DOMDocument $dom Target document to import the SVG node into.
     * @param string $svg Raw SVG markup string.
     * @return \DOMNode|null The imported SVG node, or null on parse failure.
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
     * Extract the inner HTML from the __wrapper__ div used during parsing.
     *
     * @param DOMDocument $dom The document containing the wrapper element.
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