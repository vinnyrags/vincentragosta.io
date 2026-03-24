/**
 * Projects block interactive behavior
 * Manages scroll reveal, search, category filter, and sort controls.
 */

import {
    sortCards,
    filterBySearch,
    filterByTaxonomy,
    createScrollReveal,
    preselectDropdownItem,
} from '../../../Theme/assets/js/grid-toolkit';

const CARD = '.project-card';
const HIDDEN_ATTRS = ['data-search-hidden', 'data-category-hidden'];

/**
 * Sort project cards within a grid.
 * @param {HTMLElement} grid
 * @param {string} field - 'title' or 'date'
 * @param {string} order - 'asc' or 'desc'
 */
export function sortProjects(grid, field, order) {
    sortCards(grid, CARD, field, order);
}

/**
 * Filter project cards by search query.
 * @param {HTMLElement} grid
 * @param {string} query
 */
export function filterProjects(grid, query) {
    filterBySearch(grid, CARD, query, HIDDEN_ATTRS);
}

/**
 * Filter project cards by category.
 * @param {HTMLElement} grid
 * @param {string} category - slug or 'all'
 */
export function filterByCategory(grid, category) {
    filterByTaxonomy(grid, CARD, 'category', 'data-category-hidden', category, HIDDEN_ATTRS);
}

/**
 * Initialize projects block functionality.
 */
export function initProjects() {
    const blocks = document.querySelectorAll('.wp-block-ix-projects');
    if (!blocks.length) return;

    blocks.forEach((block) => {
        const grid = block.querySelector('.projects-grid');
        if (!grid) return;

        const reveal = createScrollReveal(block, grid, CARD);

        // Search filter (only present in "all" mode)
        const searchInput = block.querySelector('.projects-search__input');
        if (searchInput) {
            searchInput.addEventListener('input', () => {
                filterProjects(grid, searchInput.value);
                reveal.reinit();
            });
        }

        // Category filter (only present in "all" mode)
        const categoryDropdown = block.querySelector('[data-dropdown="category"]');
        if (categoryDropdown) {
            categoryDropdown.addEventListener('change', (e) => {
                filterByCategory(grid, e.detail.value);
                reveal.reinit();
            });

            // Pre-select category from URL param
            const urlCategory = new URLSearchParams(window.location.search).get('category');
            if (urlCategory) {
                const matchingItem = categoryDropdown.querySelector(
                    `[data-value="${CSS.escape(urlCategory)}"]`
                );
                if (matchingItem) {
                    preselectDropdownItem(categoryDropdown, matchingItem);
                    filterByCategory(grid, urlCategory);
                    reveal.reinit();
                }
            }
        }

        // Sort controls (only present in "all" mode)
        const dropdown = block.querySelector('[data-dropdown="sort"]');
        if (dropdown) {
            dropdown.addEventListener('change', (e) => {
                const [field, order] = e.detail.value.split('-');
                sortProjects(grid, field, order);
                reveal.reinit();
            });
        }
    });
}

// Auto-init when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initProjects);
} else {
    initProjects();
}
