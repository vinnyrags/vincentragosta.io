/**
 * Keyboard access for sideways-scrolling regions.
 *
 * Terminal blocks (`.is-style-terminal`) never wrap their lines, so on narrow
 * screens they scroll sideways. A scrollable region must be reachable by
 * keyboard (WCAG 2.1.1; axe "scrollable-region-focusable"), so any that
 * actually overflows gets tabindex="0" — and loses it again when a wider
 * viewport removes the overflow. Regions that already set a tabindex of their
 * own are left alone.
 */

export const SELECTOR = '.is-style-terminal';
const MARK = 'data-scroll-focusable';

/**
 * Whether an element's content is wider than its box.
 * @param {HTMLElement} el
 */
export function overflowsX(el) {
    return el.scrollWidth > el.clientWidth + 1;
}

/**
 * Add or remove the tabindex this script manages.
 * @param {ParentNode} root
 */
export function syncScrollRegions(root = document) {
    root.querySelectorAll(SELECTOR).forEach((el) => {
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
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
