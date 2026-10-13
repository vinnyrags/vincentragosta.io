/**
 * Accent formats — Rich Text Formats
 *
 * - Accent: wraps selected text in a <mark> with the .accent-highlight class.
 *   The class uses var(--wp--preset--color--accent-1) so it adapts to
 *   per-page accent overrides (e.g. Nous Signal red).
 * - Serif accent: wraps selected text in an <em> with the .serif-accent class,
 *   the italic serif word used inside display headings ("Selected *projects*").
 */
(function () {
    'use strict';

    const { registerFormatType, toggleFormat } = wp.richText;
    const { RichTextToolbarButton } = wp.blockEditor;
    const { createElement } = wp.element;

    const FORMAT_NAME = 'ix/accent-highlight';

    registerFormatType(FORMAT_NAME, {
        title: 'Accent',
        tagName: 'mark',
        className: 'accent-highlight',
        edit: function AccentHighlightButton({ isActive, onChange, value }) {
            return createElement(RichTextToolbarButton, {
                icon: createElement('svg', {
                    xmlns: 'http://www.w3.org/2000/svg',
                    viewBox: '0 0 24 24',
                    width: 24,
                    height: 24,
                },
                    createElement('path', {
                        d: 'M12 2L9.19 8.63 2 9.24l5.46 4.73L5.82 21 12 17.27 18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2z',
                        fill: 'currentColor',
                    })
                ),
                title: 'Accent',
                isActive: isActive,
                onClick: function () {
                    onChange(toggleFormat(value, { type: FORMAT_NAME }));
                },
            });
        },
    });

    const SERIF_FORMAT_NAME = 'ix/serif-accent';

    registerFormatType(SERIF_FORMAT_NAME, {
        title: 'Serif accent',
        tagName: 'em',
        className: 'serif-accent',
        edit: function SerifAccentButton({ isActive, onChange, value }) {
            return createElement(RichTextToolbarButton, {
                icon: createElement('svg', {
                    xmlns: 'http://www.w3.org/2000/svg',
                    viewBox: '0 0 24 24',
                    width: 24,
                    height: 24,
                },
                    createElement('path', {
                        d: 'M10 4h8v2h-2.9l-3.4 12H14v2H6v-2h2.9l3.4-12H10V4z',
                        fill: 'currentColor',
                    })
                ),
                title: 'Serif accent',
                isActive: isActive,
                onClick: function () {
                    onChange(toggleFormat(value, { type: SERIF_FORMAT_NAME }));
                },
            });
        },
    });
})();
