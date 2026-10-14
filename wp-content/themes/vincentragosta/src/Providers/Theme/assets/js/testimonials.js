/**
 * Testimonials slider
 *
 * Enhances groups using the Testimonials block style (a scroll-snap row of
 * quotes, see _wp-block-container.scss) with previous/next buttons that
 * disable at either end. Without JS the row still scrolls and swipes.
 */

export const GROUP = '.wp-block-group.is-style-testimonials';
const ITEM = ':scope > :is(.wp-block-quote, .wp-block-pullquote)';

/**
 * Scroll target for one step: the next card start past the current
 * position, or the previous one before it. Null when there is none.
 * @param {number[]} starts - card start positions relative to the row's scroll origin
 * @param {number} scrollLeft
 * @param {1|-1} direction
 * @returns {number|null}
 */
export function stepTarget(starts, scrollLeft, direction) {
    const tolerance = 8;

    if (direction > 0) {
        const next = starts.find((start) => start > scrollLeft + tolerance);
        return next === undefined ? null : next;
    }

    const previous = starts.filter((start) => start < scrollLeft - tolerance);
    return previous.length ? previous[previous.length - 1] : null;
}

/**
 * Whether the row can scroll further in a direction.
 * @param {{scrollLeft: number, scrollWidth: number, clientWidth: number}} row
 * @returns {{prev: boolean, next: boolean}}
 */
export function canScroll(row) {
    return {
        prev: row.scrollLeft > 4,
        next: row.scrollLeft + row.clientWidth < row.scrollWidth - 4,
    };
}

function button(direction, label) {
    const el = document.createElement('button');
    el.type = 'button';
    el.className = `testimonials-controls__${direction}`;
    el.setAttribute('aria-label', label);
    el.innerHTML = direction === 'prev'
        ? '<svg width="14" height="14" viewBox="0 0 14 14" aria-hidden="true" focusable="false"><path d="M9 2L4 7l5 5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>'
        : '<svg width="14" height="14" viewBox="0 0 14 14" aria-hidden="true" focusable="false"><path d="M5 2l5 5-5 5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    return el;
}

/**
 * Add controls to one testimonials row.
 * @param {HTMLElement} row
 */
export function enhance(row) {
    const items = Array.from(row.querySelectorAll(ITEM));
    if (items.length < 2 || row.dataset.slider === 'ready') return;
    row.dataset.slider = 'ready';

    row.setAttribute('role', 'region');
    row.setAttribute('aria-roledescription', 'carousel');
    row.setAttribute('aria-label', 'Testimonials');
    row.tabIndex = 0;

    const controls = document.createElement('div');
    controls.className = 'testimonials-controls';
    const prev = button('prev', 'Previous testimonial');
    const next = button('next', 'Next testimonial');
    controls.append(prev, next);
    row.after(controls);

    const step = (direction) => {
        const origin = items[0].offsetLeft;
        const maxScroll = row.scrollWidth - row.clientWidth;
        const target = stepTarget(items.map((i) => i.offsetLeft - origin), row.scrollLeft, direction);
        row.scrollTo({ left: target === null ? (direction > 0 ? maxScroll : 0) : Math.min(target, maxScroll) });
    };

    const update = () => {
        const state = canScroll(row);
        prev.disabled = !state.prev;
        next.disabled = !state.next;
    };

    prev.addEventListener('click', () => step(-1));
    next.addEventListener('click', () => step(1));
    row.addEventListener('scroll', () => requestAnimationFrame(update), { passive: true });
    window.addEventListener('resize', update);
    update();
}

export function initTestimonials() {
    document.querySelectorAll(GROUP).forEach(enhance);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTestimonials);
} else {
    initTestimonials();
}
