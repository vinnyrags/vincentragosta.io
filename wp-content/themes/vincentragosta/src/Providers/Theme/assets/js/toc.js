/**
 * Contents card ([data-toc]) — the Contents block on CMS pages and the
 * project detail's "In this case study" card.
 *
 * Marks the section currently being read (aria-current on its link) and
 * fills the progress bar as the reader moves through the sections. Sections
 * are the elements the links point at ([data-toc-link] ids).
 */

/**
 * Index of the active section: the last one whose top has passed the
 * reading line. -1 before the first section.
 * @param {number[]} tops - section top offsets relative to the viewport (px)
 * @param {number} line - reading line from the viewport top (px)
 * @returns {number}
 */
export function activeIndex(tops, line) {
    let active = -1;
    tops.forEach((top, i) => {
        if (top <= line) active = i;
    });
    return active;
}

/**
 * Fraction (0–1) of the sections region scrolled past the reading line.
 * @param {number} top - region top relative to the viewport (px)
 * @param {number} height - region height (px)
 * @param {number} line - reading line from the viewport top (px)
 * @returns {number}
 */
export function progress(top, height, line) {
    if (height <= 0) return 0;
    return Math.min(1, Math.max(0, (line - top) / height));
}

/**
 * The elements a contents card's links point at, in link order.
 * @param {HTMLElement} toc
 * @returns {HTMLElement[]}
 */
export function targets(toc) {
    return Array.from(toc.querySelectorAll('[data-toc-link]'))
        .map((link) => document.getElementById(link.dataset.tocLink))
        .filter(Boolean);
}

/**
 * Apply the active state and progress to the contents card.
 * Progress spans from the first section to the end of the last section's
 * container.
 * @param {HTMLElement} toc
 * @param {HTMLElement[]} sections
 * @param {number} line
 */
export function update(toc, sections, line) {
    const index = activeIndex(sections.map((s) => s.getBoundingClientRect().top), line);
    const activeId = index >= 0 ? sections[index].id : null;

    toc.querySelectorAll('[data-toc-link]').forEach((link) => {
        if (link.dataset.tocLink === activeId) {
            link.setAttribute('aria-current', 'true');
        } else {
            link.removeAttribute('aria-current');
        }
    });

    const bar = toc.querySelector('[data-toc-progress]');
    const end = sections[sections.length - 1]?.parentElement;
    if (bar && end) {
        const top = sections[0].getBoundingClientRect().top;
        const height = end.getBoundingClientRect().bottom - top;
        bar.style.transform = `scaleX(${progress(top, height, line)})`;
    }
}

/**
 * Wire every contents card on the page to scroll.
 */
export function initToc() {
    const cards = Array.from(document.querySelectorAll('[data-toc]'))
        .map((toc) => ({ toc, sections: targets(toc) }))
        .filter(({ sections }) => sections.length);
    if (!cards.length) return;

    const line = () => window.innerHeight * 0.35;
    const refresh = () => cards.forEach(({ toc, sections }) => update(toc, sections, line()));
    let queued = false;

    const onScroll = () => {
        if (queued) return;
        queued = true;
        requestAnimationFrame(() => {
            queued = false;
            refresh();
        });
    };

    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', onScroll);
    refresh();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initToc);
} else {
    initToc();
}
