/**
 * Blog block interactive behavior
 * Manages search, tag filter, and sort controls for the Nous Signal blog grid.
 */

import {
    sortCards,
    filterBySearch,
    filterByTaxonomy,
    createScrollReveal,
    preselectDropdownItem,
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

        // Month navigation (server-side — triggers page reload)
        const monthDropdown = block.querySelector('[data-dropdown="month"]');
        if (monthDropdown) {
            monthDropdown.addEventListener('change', (e) => {
                const url = new URL(window.location.href);
                url.searchParams.set('month', e.detail.value);
                url.searchParams.delete('tag');
                window.location.href = url.toString();
            });
        }

        // Search filter with global search fallback
        const searchInput = block.querySelector('.blog-search__input');
        const searchAllContainer = grid.querySelector('.blog-grid__search-all-container');
        const searchAllLink = block.querySelector('.blog-grid__search-all');

        function updateSearchAllLink(query) {
            if (!searchAllContainer || !searchAllLink) return;
            const hasQuery = query.trim().length > 0;
            const noVisibleCards = !grid.querySelector(`${CARD}:not([aria-hidden="true"])`);
            if (hasQuery && noVisibleCards) {
                const url = new URL(window.location.origin);
                url.searchParams.set('s', query.trim());
                url.searchParams.set('post_type', 'post');
                searchAllLink.href = url.toString();
                searchAllContainer.style.display = 'block';
            } else {
                searchAllContainer.style.display = 'none';
            }
        }

        if (searchInput) {
            searchInput.addEventListener('input', () => {
                filterBySearch(grid, CARD, searchInput.value, HIDDEN_ATTRS);
                reveal.reinit();
                updateSearchAllLink(searchInput.value);
            });

            // Enter key triggers global search when no local results
            searchInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && searchAllContainer && searchAllContainer.style.display !== 'none') {
                    window.location.href = searchAllLink.href;
                }
            });
        }

        // Tag filter
        const tagDropdown = block.querySelector('[data-dropdown="tag"]');
        if (tagDropdown) {
            tagDropdown.addEventListener('change', (e) => {
                filterByTaxonomy(grid, CARD, 'tags', 'data-tag-hidden', e.detail.value, HIDDEN_ATTRS);
                reveal.reinit();
            });

            // Pre-select tag from URL param
            const urlTag = new URLSearchParams(window.location.search).get('tag');
            if (urlTag) {
                const matchingItem = tagDropdown.querySelector(
                    `[data-value="${CSS.escape(urlTag)}"]`
                );
                if (matchingItem) {
                    preselectDropdownItem(tagDropdown, matchingItem);
                    filterByTaxonomy(grid, CARD, 'tags', 'data-tag-hidden', urlTag, HIDDEN_ATTRS);
                    reveal.reinit();
                }
            }
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
