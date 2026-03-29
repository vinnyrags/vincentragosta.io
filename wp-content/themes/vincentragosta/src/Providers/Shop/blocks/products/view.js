/**
 * Products block interactive behavior.
 * Manages search, category filter, sort, and add-to-cart for the shop grid.
 */

import {
    sortCards,
    filterBySearch,
    filterByTaxonomy,
    createScrollReveal,
    preselectDropdownItem,
} from '../../../Theme/assets/js/grid-toolkit';

const CARD = '.product-card';
const HIDDEN_ATTRS = ['data-search-hidden', 'data-category-hidden'];

/**
 * Initialize products block functionality.
 */
function initProducts() {
    const blocks = document.querySelectorAll('.wp-block-vincentragosta-products');
    if (!blocks.length) return;

    blocks.forEach((block) => {
        const grid = block.querySelector('.products-grid');
        if (!grid) return;

        const reveal = createScrollReveal(block, grid, CARD);

        // Search filter
        const searchInput = block.querySelector('.shop-search__input');
        if (searchInput) {
            searchInput.addEventListener('input', () => {
                filterBySearch(grid, CARD, searchInput.value, HIDDEN_ATTRS);
                reveal.reinit();
            });
        }

        // Category filter
        const categoryDropdown = block.querySelector('[data-dropdown="category"]');
        if (categoryDropdown) {
            categoryDropdown.addEventListener('change', (e) => {
                filterByTaxonomy(grid, CARD, 'category', 'data-category-hidden', e.detail.value, HIDDEN_ATTRS);
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
                    filterByTaxonomy(grid, CARD, 'category', 'data-category-hidden', urlCategory, HIDDEN_ATTRS);
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

        // Add to cart buttons
        grid.addEventListener('click', (e) => {
            const button = e.target.closest('[data-add-to-cart]');
            if (!button) return;

            e.preventDefault();

            const item = {
                priceId: button.dataset.priceId,
                productId: button.dataset.productId,
                title: button.dataset.title,
                price: button.dataset.price,
                image: button.dataset.image,
                stock: parseInt(button.dataset.stock, 10) || 1,
                quantity: 1,
            };

            // Dispatch custom event for the cart module to handle
            document.dispatchEvent(new CustomEvent('shop:add-to-cart', {
                detail: item,
            }));

            // Visual feedback
            button.textContent = 'Added!';
            button.disabled = true;
            setTimeout(() => {
                button.textContent = 'Add to Cart';
                button.disabled = false;
            }, 1500);
        });
    });
}

// Auto-init when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initProducts);
} else {
    initProducts();
}
