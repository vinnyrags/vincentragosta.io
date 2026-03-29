/**
 * Grid Toolkit — shared sort, search, visibility, and scroll reveal utilities.
 *
 * Both the projects block and blog block import from this module.
 * esbuild inlines the shared code into each consuming bundle.
 */

/**
 * Sort cards within a grid by a data attribute.
 * @param {HTMLElement} grid - The grid container
 * @param {string} cardSelector - CSS selector for cards (e.g., '.project-card')
 * @param {string} field - Sort field matching a data-{field} attribute ('title' or 'date')
 * @param {string} order - 'asc' or 'desc'
 */
export function sortCards(grid, cardSelector, field, order) {
    const cards = Array.from(grid.querySelectorAll(cardSelector));

    cards.sort((a, b) => {
        const valA = a.dataset[field] || '';
        const valB = b.dataset[field] || '';
        const numA = parseFloat(valA);
        const numB = parseFloat(valB);
        const isNumeric = !isNaN(numA) && !isNaN(numB);

        if (isNumeric) {
            return order === 'asc' ? numA - numB : numB - numA;
        }
        return order === 'asc' ? valA.localeCompare(valB) : valB.localeCompare(valA);
    });

    cards.forEach((card) => {
        card.classList.remove('is-visible');
        grid.appendChild(card);
    });
}

/**
 * Apply composite visibility from independent filter states.
 * Each filter manages its own data-*-hidden attribute;
 * this derives the final aria-hidden state.
 * @param {HTMLElement} card
 * @param {string[]} hiddenAttributes - e.g., ['data-search-hidden', 'data-category-hidden']
 */
export function applyVisibility(card, hiddenAttributes) {
    const hidden = hiddenAttributes.some((attr) => card.hasAttribute(attr));
    if (hidden) {
        card.setAttribute('aria-hidden', 'true');
        card.classList.remove('is-visible');
    } else {
        card.removeAttribute('aria-hidden');
    }
}

/**
 * Filter cards by a search query against the data-title attribute.
 * @param {HTMLElement} grid - The grid container
 * @param {string} cardSelector - CSS selector for cards
 * @param {string} query - Search string
 * @param {string[]} hiddenAttributes - All hidden attribute names for visibility computation
 */
export function filterBySearch(grid, cardSelector, query, hiddenAttributes) {
    const cards = grid.querySelectorAll(cardSelector);
    const term = query.toLowerCase().trim();

    cards.forEach((card) => {
        const title = card.dataset.title || '';
        const match = !term || title.includes(term);
        card.toggleAttribute('data-search-hidden', !match);
        applyVisibility(card, hiddenAttributes);
    });
}

/**
 * Filter cards by a taxonomy value stored in a space-separated data attribute.
 * Works for categories, tags, or any taxonomy-style filter.
 * @param {HTMLElement} grid - The grid container
 * @param {string} cardSelector - CSS selector for cards
 * @param {string} dataKey - The dataset key to read (e.g., 'category', 'tags')
 * @param {string} hiddenAttr - The hidden attribute name (e.g., 'data-category-hidden')
 * @param {string} value - The taxonomy slug to filter by, or 'all' to show all
 * @param {string[]} hiddenAttributes - All hidden attribute names for visibility computation
 */
export function filterByTaxonomy(grid, cardSelector, dataKey, hiddenAttr, value, hiddenAttributes) {
    const cards = grid.querySelectorAll(cardSelector);

    cards.forEach((card) => {
        const values = (card.dataset[dataKey] || '').split(/\s+/);
        const match = value === 'all' || values.includes(value);
        card.toggleAttribute(hiddenAttr, !match);
        applyVisibility(card, hiddenAttributes);
    });
}

/**
 * Create a scroll reveal observer for cards in a grid.
 * Returns an object with reinit() to re-observe after sort/filter changes.
 * @param {HTMLElement} block - The block wrapper (gets 'has-scroll-reveal' class)
 * @param {HTMLElement} grid - The grid container
 * @param {string} cardSelector - CSS selector for cards
 * @returns {{ reinit: () => void }}
 */
export function createScrollReveal(block, grid, cardSelector) {
    block.classList.add('has-scroll-reveal');
    let observer = null;

    function init() {
        if (observer) observer.disconnect();

        observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        observer.unobserve(entry.target);
                    }
                });
            },
            { rootMargin: '50px' }
        );

        grid.querySelectorAll(cardSelector).forEach((card) => {
            if (!card.classList.contains('is-visible')) {
                observer.observe(card);
            }
        });
    }

    init();

    return {
        reinit() {
            requestAnimationFrame(() => init());
        },
    };
}

/**
 * Pre-select a dropdown item without dispatching a change event.
 * @param {HTMLElement} dropdown - The .dropdown container
 * @param {HTMLElement} item - The item to select
 */
export function preselectDropdownItem(dropdown, item) {
    const label = dropdown.querySelector('.dropdown__label');
    const items = dropdown.querySelectorAll('.dropdown__item');

    items.forEach((i) => {
        const isSelected = i === item;
        i.setAttribute('aria-selected', isSelected ? 'true' : 'false');
        i.setAttribute('aria-disabled', isSelected ? 'true' : 'false');
    });

    if (label) {
        label.textContent = item.textContent.trim();
    }
}
