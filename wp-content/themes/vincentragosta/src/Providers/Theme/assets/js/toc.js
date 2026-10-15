/**
 * Contents card ([data-toc]) — the Contents block on CMS pages and the
 * project detail's "In this case study" card.
 *
 * Marks the section currently being read (aria-current on its link) and
 * fills the progress bar as the reader moves through the sections. Sections
 * are the elements the links point at ([data-toc-link] ids).
 *
 * On phones the card is a slim sticky bar (see _toc.scss): enhance() adds a
 * toggle naming the current section ("03 / 11 Feature system") that opens the
 * rest of the card; choosing a section closes it again. Desktop hides the
 * toggle and shows the card as before.
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
 * The bar's summary: "03 / 11" and the current section's label (the first
 * section until one has been reached).
 * @param {HTMLElement} toc
 * @returns {{count: string, label: string}}
 */
export function summary(toc) {
    const links = Array.from(toc.querySelectorAll('[data-toc-link]'));
    const index = Math.max(0, links.findIndex((link) => link.hasAttribute('aria-current')));
    const pad = (n) => String(n).padStart(2, '0');
    const link = links[index];
    const label = link ? (link.firstChild?.textContent ?? link.textContent).trim() : '';

    return { count: links.length ? `${pad(index + 1)} / ${pad(links.length)}` : '', label };
}

/**
 * Add the phone toggle: a button at the top of the card naming the current
 * section, controlling a panel that holds the rest of the card. The progress
 * bar stays outside the panel so the collapsed bar still shows it.
 * @param {HTMLElement} toc
 */
export function enhance(toc) {
    if (toc.querySelector('.toc__toggle')) return;

    const panel = document.createElement('div');
    panel.className = 'toc__panel';
    panel.id = `${toc.id || toc.getAttribute('aria-labelledby') || 'toc'}-panel`;
    const progressBar = toc.querySelector('.toc__progress');
    Array.from(toc.childNodes).forEach((node) => {
        if (node !== progressBar) panel.appendChild(node);
    });

    const toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'toc__toggle';
    toggle.setAttribute('aria-expanded', 'false');
    toggle.setAttribute('aria-controls', panel.id);
    toggle.innerHTML = '<span class="toc__current"><span class="toc__count"></span> <span class="toc__label"></span></span><span class="toc__hint">On this page</span>';

    toc.prepend(toggle);
    toggle.after(panel);
    if (progressBar) panel.after(progressBar);

    const setOpen = (open) => {
        toc.classList.toggle('is-open', open);
        toggle.setAttribute('aria-expanded', String(open));
    };
    toggle.addEventListener('click', () => setOpen(!toc.classList.contains('is-open')));
    panel.addEventListener('click', (event) => {
        if (event.target.closest('a')) setOpen(false);
    });
    toc.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && toc.classList.contains('is-open')) {
            setOpen(false);
            toggle.focus();
        }
    });

    renderSummary(toc);
}

/**
 * Write the current summary into the toggle.
 * @param {HTMLElement} toc
 */
export function renderSummary(toc) {
    const count = toc.querySelector('.toc__count');
    const label = toc.querySelector('.toc__label');
    if (!count || !label) return;
    const { count: c, label: l } = summary(toc);
    count.textContent = c;
    label.textContent = l;
}

/**
 * Wire every contents card on the page to scroll.
 */
export function initToc() {
    const cards = Array.from(document.querySelectorAll('[data-toc]'))
        .map((toc) => ({ toc, sections: targets(toc) }))
        .filter(({ sections }) => sections.length);
    if (!cards.length) return;

    cards.forEach(({ toc }) => enhance(toc));

    const line = () => window.innerHeight * 0.35;
    const refresh = () => cards.forEach(({ toc, sections }) => {
        update(toc, sections, line());
        renderSummary(toc);
    });
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
