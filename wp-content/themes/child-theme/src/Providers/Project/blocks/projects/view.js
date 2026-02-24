/**
 * Projects block interactive behavior
 * Manages scroll reveal and sort controls.
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
 * Initialize projects block functionality
 */
export function initProjects() {
    const blocks = document.querySelectorAll('.wp-block-child-theme-projects');
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
