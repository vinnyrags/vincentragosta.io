(function () {
    const archive = document.querySelector('.projects-archive');
    if (!archive) return;

    const grid = archive.querySelector('.projects-grid');
    if (!grid) return;

    // Scroll reveal
    archive.classList.add('has-scroll-reveal');

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

    // Sort controls
    function sortProjects(field, order) {
        const cards = Array.from(grid.querySelectorAll('.project-card'));

        cards.sort((a, b) => {
            let valA, valB;

            if (field === 'title') {
                valA = a.dataset.title || '';
                valB = b.dataset.title || '';
                return order === 'asc'
                    ? valA.localeCompare(valB)
                    : valB.localeCompare(valA);
            }

            // date — ISO strings sort lexicographically
            valA = a.dataset.date || '';
            valB = b.dataset.date || '';
            return order === 'asc'
                ? valA.localeCompare(valB)
                : valB.localeCompare(valA);
        });

        cards.forEach((card) => {
            card.classList.remove('is-visible');
            grid.appendChild(card);
        });

        requestAnimationFrame(() => initScrollReveal());
    }

    const dropdown = archive.querySelector('[data-dropdown="sort"]');
    if (dropdown) {
        dropdown.addEventListener('change', (e) => {
            const [field, order] = e.detail.value.split('-');
            sortProjects(field, order);
        });
    }
})();
