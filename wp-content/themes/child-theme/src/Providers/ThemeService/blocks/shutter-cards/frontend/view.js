document.addEventListener('DOMContentLoaded', function () {
    const containers = document.querySelectorAll('.shutter-cards');

    containers.forEach(container => {
        const cards = container.querySelectorAll('.wp-block-child-theme-shutter-card');

        if (cards.length < 2) return;

        /**
         * Activate a card and deactivate others
         */
        function activateCard(cardToActivate) {
            cards.forEach(card => {
                const isActive = card === cardToActivate;
                const innerCard = card.querySelector('.shutter-card');

                card.classList.toggle('is-inactive', !isActive);

                // Update ARIA attributes
                if (innerCard) {
                    innerCard.setAttribute('aria-expanded', isActive ? 'true' : 'false');
                }

                // Update tabindex for keyboard navigation
                card.setAttribute('tabindex', isActive ? '-1' : '0');
                card.setAttribute('role', isActive ? null : 'button');

                // Update toggle button label
                const toggle = innerCard?.querySelector('.shutter-card__toggle');
                if (toggle) {
                    toggle.setAttribute('aria-label', isActive ? 'Collapse card' : 'Expand card');
                    toggle.setAttribute('aria-expanded', isActive ? 'true' : 'false');
                }
            });
        }

        /**
         * Handle card activation
         */
        function handleActivation(card) {
            if (card.classList.contains('is-inactive')) {
                activateCard(card);
            }
        }

        // Set initial state: first card active, rest inactive
        activateCard(cards[0]);

        // Event listeners for each card
        cards.forEach(card => {
            // Click handler for inactive cards
            card.addEventListener('click', function (e) {
                // Don't activate if clicking inside an active card (allow normal interaction)
                if (!this.classList.contains('is-inactive')) return;

                // Don't activate if clicking the toggle button (handled separately)
                if (e.target.closest('.shutter-card__toggle')) return;

                handleActivation(this);
            });

            // Keyboard handler for inactive cards
            card.addEventListener('keydown', function (e) {
                if (!this.classList.contains('is-inactive')) return;

                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    handleActivation(this);
                }
            });

            // Toggle button handler (works on active cards too)
            const toggle = card.querySelector('.shutter-card__toggle');
            if (toggle) {
                toggle.addEventListener('click', function (e) {
                    e.stopPropagation();

                    const isActive = !card.classList.contains('is-inactive');

                    if (isActive) {
                        // If clicking toggle on active card, activate the next card (or first)
                        const currentIndex = Array.from(cards).indexOf(card);
                        const nextCard = cards[(currentIndex + 1) % cards.length];
                        activateCard(nextCard);
                        nextCard.focus();
                    } else {
                        handleActivation(card);
                    }
                });
            }
        });

        // Remove preload class after initial setup
        requestAnimationFrame(() => {
            container.classList.remove('shutter-cards--preload');
        });
    });
});
