/**
 * Projects block interactive behavior
 * Manages scroll reveal, search, and sort controls.
 */

/**
 * Sort project cards within a grid
 * @param {HTMLElement} grid - The .projects-grid container
 * @param {string} field - Sort field ('title' or 'date')
 * @param {string} order - Sort order ('asc' or 'desc')
 */
export function sortProjects(grid, field, order) {
    const cards = Array.from(grid.querySelectorAll('.project-card'));

    cards.sort((a, b) => {
        let valA, valB;

        if (field === 'title') {
            valA = a.dataset.title || '';
            valB = b.dataset.title || '';
            return order === 'asc' ? valA.localeCompare(valB) : valB.localeCompare(valA);
        }

        // date — ISO strings sort lexicographically
        valA = a.dataset.date || '';
        valB = b.dataset.date || '';
        return order === 'asc' ? valA.localeCompare(valB) : valB.localeCompare(valA);
    });

    cards.forEach((card) => {
        card.classList.remove('is-visible');
        grid.appendChild(card);
    });
}

/**
 * Compute visibility from independent filter states.
 * Each filter manages its own data-*-hidden attribute;
 * this function derives the final aria-hidden attribute.
 * @param {HTMLElement} card
 */
function applyVisibility(card) {
    const hidden =
        card.hasAttribute('data-search-hidden') ||
        card.hasAttribute('data-category-hidden');
    if (hidden) {
        card.setAttribute('aria-hidden', 'true');
        card.classList.remove('is-visible');
    } else {
        card.removeAttribute('aria-hidden');
    }
}

/**
 * Filter project cards by search query
 * @param {HTMLElement} grid - The .projects-grid container
 * @param {string} query - Search string to match against card titles
 */
export function filterProjects(grid, query) {
    const cards = grid.querySelectorAll('.project-card');
    const term = query.toLowerCase().trim();

    cards.forEach((card) => {
        const title = card.dataset.title || '';
        const match = !term || title.includes(term);
        card.toggleAttribute('data-search-hidden', !match);
        applyVisibility(card);
    });
}

/**
 * Filter project cards by category
 * @param {HTMLElement} grid - The .projects-grid container
 * @param {string} category - Category slug to filter by, or 'all' to show all
 */
export function filterByCategory(grid, category) {
    const cards = grid.querySelectorAll('.project-card');

    cards.forEach((card) => {
        const cardCategories = (card.dataset.category || '').split(/\s+/);
        const match = category === 'all' || cardCategories.includes(category);
        card.toggleAttribute('data-category-hidden', !match);
        applyVisibility(card);
    });
}

/**
 * Pre-select a dropdown item without dispatching a change event.
 * Replicates the minimal ARIA update logic from the dropdown component.
 * @param {HTMLElement} dropdown - The .dropdown container
 * @param {HTMLElement} item - The item to select
 */
function preselectDropdownItem(dropdown, item) {
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

/**
 * Initialize projects block functionality
 */
export function initProjects() {
    const blocks = document.querySelectorAll('.wp-block-ix-projects');
    if (!blocks.length) return;

    blocks.forEach((block) => {
        const grid = block.querySelector('.projects-grid');
        if (!grid) return;

        // Scroll reveal
        block.classList.add('has-scroll-reveal');

        let observer = null;

        function initScrollReveal() {
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

            grid.querySelectorAll('.project-card').forEach((card) => {
                if (!card.classList.contains('is-visible')) {
                    observer.observe(card);
                }
            });
        }

        initScrollReveal();

        // Search filter (only present in "all" mode)
        const searchInput = block.querySelector('.projects-search__input');
        if (searchInput) {
            searchInput.addEventListener('input', () => {
                filterProjects(grid, searchInput.value);
                requestAnimationFrame(() => initScrollReveal());
            });
        }

        // Category filter (only present in "all" mode)
        const categoryDropdown = block.querySelector('[data-dropdown="category"]');
        if (categoryDropdown) {
            categoryDropdown.addEventListener('change', (e) => {
                filterByCategory(grid, e.detail.value);
                requestAnimationFrame(() => initScrollReveal());
            });

            // Pre-select category from URL param (e.g., ?category=web-development)
            const urlCategory = new URLSearchParams(window.location.search).get('category');
            if (urlCategory) {
                const matchingItem = categoryDropdown.querySelector(
                    `[data-value="${CSS.escape(urlCategory)}"]`
                );
                if (matchingItem) {
                    preselectDropdownItem(categoryDropdown, matchingItem);
                    filterByCategory(grid, urlCategory);
                    requestAnimationFrame(() => initScrollReveal());
                }
            }
        }

        // Sort controls (only present in "all" mode)
        const dropdown = block.querySelector('[data-dropdown="sort"]');
        if (dropdown) {
            dropdown.addEventListener('change', (e) => {
                const [field, order] = e.detail.value.split('-');
                sortProjects(grid, field, order);
                requestAnimationFrame(() => initScrollReveal());
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
