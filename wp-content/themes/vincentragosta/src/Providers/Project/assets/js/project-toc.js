/**
 * Project detail — sticky "In this case study" contents card.
 *
 * Marks the section currently being read (aria-current on its link) and
 * fills the progress bar as the reader moves through the sections.
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
 * Apply the active state and progress to the contents card.
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
    const region = sections[0]?.parentElement;
    if (bar && region) {
        const rect = region.getBoundingClientRect();
        bar.style.transform = `scaleX(${progress(rect.top, rect.height, line)})`;
    }
}

/**
 * Wire the contents card to scroll.
 */
export function initProjectToc() {
    const toc = document.querySelector('[data-project-toc]');
    const sections = Array.from(document.querySelectorAll('[data-toc-section]'));
    if (!toc || !sections.length) return;

    const line = () => window.innerHeight * 0.35;
    let queued = false;

    const onScroll = () => {
        if (queued) return;
        queued = true;
        requestAnimationFrame(() => {
            queued = false;
            update(toc, sections, line());
        });
    };

    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', onScroll);
    update(toc, sections, line());
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initProjectToc);
} else {
    initProjectToc();
}
