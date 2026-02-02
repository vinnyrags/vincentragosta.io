document.addEventListener('DOMContentLoaded', function () {
    const containers = document.querySelectorAll('.shutter-cards');

    containers.forEach(container => {
        // Target the WordPress wrapper elements (the actual flex items)
        const cards = container.querySelectorAll('.wp-block-child-theme-shutter-card');

        if (cards.length < 2) return;

        // Set initial state: first card active, rest inactive
        cards.forEach((card, index) => {
            if (index > 0) {
                card.classList.add('is-inactive');
            }
        });

        // Handle clicks
        cards.forEach(card => {
            card.addEventListener('click', function () {
                if (!this.classList.contains('is-inactive')) return;

                // Deactivate all cards
                cards.forEach(c => c.classList.add('is-inactive'));

                // Activate clicked card
                this.classList.remove('is-inactive');
            });
        });

        // Remove preload class after initial setup
        requestAnimationFrame(() => {
            container.classList.remove('shutter-cards--preload');
        });
    });
});
