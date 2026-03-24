/**
 * Blog block interactive behavior
 * Manages search, tag filter, and sort controls for the Nous Signal blog grid.
 */

import {
    sortCards,
    filterBySearch,
    filterByTaxonomy,
    createScrollReveal,
} from '../../../Theme/assets/js/grid-toolkit';

const CARD = '.post-card';
const HIDDEN_ATTRS = ['data-search-hidden', 'data-tag-hidden'];

/**
 * Initialize blog block functionality.
 */
function initBlog() {
    const blocks = document.querySelectorAll('.wp-block-ix-blog');
    if (!blocks.length) return;

    blocks.forEach((block) => {
        const grid = block.querySelector('.blog-grid');
        if (!grid) return;

        const reveal = createScrollReveal(block, grid, CARD);

        // Search filter
        const searchInput = block.querySelector('.blog-search__input');
        if (searchInput) {
            searchInput.addEventListener('input', () => {
                filterBySearch(grid, CARD, searchInput.value, HIDDEN_ATTRS);
                reveal.reinit();
            });
        }

        // Tag filter
        const tagDropdown = block.querySelector('[data-dropdown="tag"]');
        if (tagDropdown) {
            tagDropdown.addEventListener('change', (e) => {
                filterByTaxonomy(grid, CARD, 'tags', 'data-tag-hidden', e.detail.value, HIDDEN_ATTRS);
                reveal.reinit();
            });
        }

        // Sort controls
        const sortDropdown = block.querySelector('[data-dropdown="sort"]');
        if (sortDropdown) {
            sortDropdown.addEventListener('change', (e) => {
                const [field, order] = e.detail.value.split('-');
                sortCards(grid, CARD, field, order);
                reveal.reinit();
            });
        }
    });
}

// Auto-init when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initBlog);
} else {
    initBlog();
}
