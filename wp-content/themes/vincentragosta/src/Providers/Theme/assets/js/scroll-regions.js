/**
 * Terminal blocks (`.is-style-terminal`) on narrow screens.
 *
 * Terminal lines never wrap. When a terminal's longest line is wider than its
 * box, its text first shrinks to fit — down to MIN_FONT_PX — so the whole
 * line stays readable instead of being cropped. A line too long even at that
 * size keeps its normal size and scrolls sideways instead. A scrollable
 * region must be reachable by keyboard (WCAG 2.1.1; axe
 * "scrollable-region-focusable"), so it then gets tabindex="0" — removed
 * again when a wider viewport ends the overflow. Regions that set a tabindex
 * of their own are left alone.
 */

export const SELECTOR = '.is-style-terminal';
export const MIN_FONT_PX = 9;
const MARK = 'data-scroll-focusable';
const FIT = 'data-fit-font';

/**
 * Whether an element's content is wider than its box.
 * @param {HTMLElement} el
 */
export function overflowsX(el) {
    return el.scrollWidth > el.clientWidth + 1;
}

/**
 * The font size (px) at which content `needed` px wide fits `available` px,
 * never above `current`, rounded down to 0.1px. When even `min` can't fit it,
 * the text keeps `current` and scrolls: shrinking to the floor and scrolling
 * anyway would be the worst of both.
 * @param {number} current - the stylesheet's font size, px
 * @param {number} needed - content width at that size, px
 * @param {number} available - room for it, px
 * @param {number} [min]
 */
export function fitFontSize(current, needed, available, min = MIN_FONT_PX) {
    if (needed <= available || needed <= 0) return current;
    const size = Math.floor(current * (available / needed) * 10) / 10;
    return size < min ? current : size;
}

/**
 * Shrink an overflowing terminal's text to fit, from its stylesheet size.
 * @param {HTMLElement} el
 */
function fit(el) {
    if (el.hasAttribute(FIT)) {
        el.style.removeProperty('font-size');
        el.removeAttribute(FIT);
    }
    if (!overflowsX(el)) return;

    const style = window.getComputedStyle(el);
    const current = parseFloat(style.fontSize);
    const padding = (parseFloat(style.paddingLeft) || 0) + (parseFloat(style.paddingRight) || 0);
    if (!current) return;

    const size = fitFontSize(current, el.scrollWidth - padding, el.clientWidth - padding);
    if (size < current) {
        el.style.fontSize = `${size}px`;
        el.setAttribute(FIT, '');
    }
}

/**
 * Fit each terminal, then add or remove the tabindex this script manages.
 * @param {ParentNode} root
 */
export function syncScrollRegions(root = document) {
    root.querySelectorAll(SELECTOR).forEach((el) => {
        fit(el);

        const managed = el.hasAttribute(MARK);
        if (el.hasAttribute('tabindex') && !managed) return;

        if (overflowsX(el)) {
            el.setAttribute('tabindex', '0');
            el.setAttribute(MARK, '');
        } else if (managed) {
            el.removeAttribute('tabindex');
            el.removeAttribute(MARK);
        }
    });
}

function init() {
    syncScrollRegions();

    let frame = 0;
    window.addEventListener('resize', () => {
        cancelAnimationFrame(frame);
        frame = requestAnimationFrame(() => syncScrollRegions());
    });

    // Web fonts change line widths; fit again once they have loaded.
    if (document.fonts?.ready) {
        document.fonts.ready.then(() => syncScrollRegions());
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
