/**
 * Shutter cards interactive behavior
 * Manages card activation, ARIA state, and keyboard navigation.
 */

/**
 * Activate a card and deactivate others
 * @param {NodeList|HTMLElement[]} cards - All cards in the container
 * @param {HTMLElement} cardToActivate - The card to activate
 */
export function activateCard(cards, cardToActivate) {
    cards.forEach((card) => {
        const isActive = card === cardToActivate;
        const innerCard = card.querySelector('.shutter-card');

        card.classList.toggle('is-inactive', !isActive);

        // Update ARIA attributes
        if (innerCard) {
            innerCard.setAttribute('aria-expanded', isActive ? 'true' : 'false');
        }

        // Update tabindex for keyboard navigation
        card.setAttribute('tabindex', isActive ? '-1' : '0');
        if (!isActive) {
            card.setAttribute('role', 'button');
        } else {
            card.removeAttribute('role');
        }

        // Update toggle button label
        const toggle = innerCard?.querySelector('.shutter-card__toggle');
        if (toggle) {
            toggle.setAttribute('aria-label', isActive ? 'Collapse card' : 'Expand card');
            toggle.setAttribute('aria-expanded', isActive ? 'true' : 'false');
        }
    });
}

/**
 * Initialize shutter cards for all containers on the page
 */
export function initShutterCards() {
    const containers = document.querySelectorAll('.shutter-cards');

    containers.forEach((container) => {
        const cards = container.querySelectorAll('.wp-block-child-theme-shutter-card');

        if (cards.length < 2) return;

        /**
         * Handle card activation
         */
        function handleActivation(card) {
            if (card.classList.contains('is-inactive')) {
                activateCard(cards, card);
            }
        }

        // Set initial state
        activateCard(cards, cards[0]);

        // Remove preload class
        container.classList.remove('shutter-cards--preload');

        // Event listeners for each card
        cards.forEach((card) => {
            // Click handler for inactive cards
            card.addEventListener('click', function (e) {
                if (!this.classList.contains('is-inactive')) return;
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

            // Toggle button handler
            const toggle = card.querySelector('.shutter-card__toggle');
            if (toggle) {
                toggle.addEventListener('click', function (e) {
                    e.stopPropagation();
                    const isActive = !card.classList.contains('is-inactive');

                    if (isActive) {
                        const currentIndex = Array.from(cards).indexOf(card);
                        const nextCard = cards[(currentIndex + 1) % cards.length];
                        activateCard(cards, nextCard);
                        nextCard.focus();
                    } else {
                        handleActivation(card);
                    }
                });
            }
        });
    });
}

// Auto-init when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initShutterCards);
} else {
    initShutterCards();
}
