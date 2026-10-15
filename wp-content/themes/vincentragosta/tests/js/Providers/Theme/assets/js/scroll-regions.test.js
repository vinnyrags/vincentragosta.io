import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { syncScrollRegions, fitFontSize, MIN_FONT_PX, FIT_QUERY } from '../../../../../../src/Providers/Theme/assets/js/scroll-regions.js';

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

describe('fitFontSize', () => {
    it('keeps the stylesheet size when the line already fits', () => {
        expect(fitFontSize(12, 300, 320)).toBe(12);
    });

    it('scales down so the line fits, rounded down to 0.1px', () => {
        expect(fitFontSize(12, 331, 326)).toBe(11.8);
    });

    it('fits at exactly the floor', () => {
        expect(fitFontSize(12, 400, 300)).toBe(MIN_FONT_PX);
    });

    it('keeps the stylesheet size (and scrolls) when even the floor cannot fit', () => {
        expect(fitFontSize(12, 600, 200)).toBe(12);
    });
});

describe('syncScrollRegions fitting', () => {
    let el;
    const original = window.getComputedStyle;

    /** @param {boolean} phone */
    function viewport(phone) {
        window.matchMedia.mockImplementation((query) => ({ matches: phone && query === FIT_QUERY, media: query }));
    }

    beforeEach(() => {
        document.body.innerHTML = '<pre class="is-style-terminal" id="t"></pre>';
        el = document.getElementById('t');
        // 330 wide content in a 300 box at 12px; once fitted it no longer overflows.
        Object.defineProperty(el, 'clientWidth', { configurable: true, value: 300 });
        Object.defineProperty(el, 'scrollWidth', { configurable: true, get: () => (el.style.fontSize ? 300 : 330) });
        window.getComputedStyle = () => ({ fontSize: '12px', paddingLeft: '0px', paddingRight: '0px' });
    });

    afterEach(() => {
        window.getComputedStyle = original;
        viewport(false);
    });

    it('on a phone, shrinks an overflowing terminal to fit instead of making it scroll', () => {
        viewport(true);
        syncScrollRegions();

        expect(el.style.fontSize).toBe('10.9px');
        expect(el.hasAttribute('tabindex')).toBe(false);
    });

    it('on a wider screen, keeps the stylesheet size and scrolls', () => {
        viewport(false);
        syncScrollRegions();

        expect(el.style.fontSize).toBe('');
        expect(el.getAttribute('tabindex')).toBe('0');
    });

    it('drops a phone fit once the viewport widens', () => {
        viewport(true);
        syncScrollRegions();
        viewport(false);
        syncScrollRegions();

        expect(el.style.fontSize).toBe('');
        expect(el.hasAttribute('data-fit-font')).toBe(false);
    });
});
