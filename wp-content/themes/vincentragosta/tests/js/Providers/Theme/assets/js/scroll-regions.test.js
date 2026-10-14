import { describe, it, expect, beforeEach } from 'vitest';
import { syncScrollRegions } from '../../../../../../src/Providers/Theme/assets/js/scroll-regions.js';

/**
 * jsdom has no layout, so give each region the widths a browser would report.
 * @param {HTMLElement} el
 * @param {number} scrollWidth
 * @param {number} clientWidth
 */
function size(el, scrollWidth, clientWidth) {
    Object.defineProperty(el, 'scrollWidth', { configurable: true, value: scrollWidth });
    Object.defineProperty(el, 'clientWidth', { configurable: true, value: clientWidth });
}

describe('syncScrollRegions', () => {
    let wide;
    let narrow;
    let own;

    beforeEach(() => {
        document.body.innerHTML = `
            <pre class="is-style-terminal" id="wide"></pre>
            <pre class="is-style-terminal" id="narrow"></pre>
            <pre class="is-style-terminal" id="own" tabindex="-1"></pre>`;
        wide = document.getElementById('wide');
        narrow = document.getElementById('narrow');
        own = document.getElementById('own');
        size(wide, 600, 340);
        size(narrow, 300, 340);
        size(own, 600, 340);
    });

    it('makes an overflowing terminal focusable and leaves a fitting one alone', () => {
        syncScrollRegions();

        expect(wide.getAttribute('tabindex')).toBe('0');
        expect(narrow.hasAttribute('tabindex')).toBe(false);
    });

    it('removes the tabindex it added once the overflow is gone', () => {
        syncScrollRegions();
        size(wide, 600, 700);
        syncScrollRegions();

        expect(wide.hasAttribute('tabindex')).toBe(false);
    });

    it('never touches a tabindex the markup set itself', () => {
        syncScrollRegions();
        size(own, 300, 340);
        syncScrollRegions();

        expect(own.getAttribute('tabindex')).toBe('-1');
    });
});
