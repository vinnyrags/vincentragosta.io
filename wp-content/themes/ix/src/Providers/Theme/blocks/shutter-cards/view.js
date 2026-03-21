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

        // Update ARIA attributes
        if (innerCard) {
            innerCard.setAttribute('aria-expanded', isActive ? 'true' : 'false');
        }

        // Remove card wrapper from tab order — toggle button is the sole tab stop
        card.removeAttribute('tabindex');
        card.removeAttribute('role');

        // Update toggle button label
        const toggle = innerCard?.querySelector('.shutter-card__toggle');
        if (toggle) {
            toggle.setAttribute('aria-label', isActive ? 'Collapse card' : 'Expand card');
            toggle.setAttribute('aria-expanded', isActive ? 'true' : 'false');
        }
    });
}

/**
 * Deactivate all cards (no card is active)
 * @param {NodeList|HTMLElement[]} cards - All cards in the container
 */
export function deactivateAll(cards) {
    cards.forEach((card) => {
        const innerCard = card.querySelector('.shutter-card');

        card.removeAttribute('tabindex');
        card.removeAttribute('role');

        if (innerCard) {
            innerCard.setAttribute('aria-expanded', 'false');
        }

        const toggle = innerCard?.querySelector('.shutter-card__toggle');
        if (toggle) {
            toggle.setAttribute('aria-label', 'Expand card');
            toggle.setAttribute('aria-expanded', 'false');
        }
    });
}

/**
 * Initialize shutter cards for all containers on the page
 */
export function initShutterCards() {
    const containers = document.querySelectorAll('.shutter-cards');

    containers.forEach((container) => {
        const cards = container.querySelectorAll('.wp-block-ix-shutter-card');

        if (cards.length < 2) return;

        /**
         * Handle card activation
         */
        function handleActivation(card) {
            if (card.querySelector('.shutter-card')?.getAttribute('aria-expanded') === 'false') {
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
                if (this.querySelector('.shutter-card')?.getAttribute('aria-expanded') !== 'false') return;
                if (e.target.closest('.shutter-card__toggle')) return;
                handleActivation(this);
            });


            // Toggle button handler
            const toggle = card.querySelector('.shutter-card__toggle');
            if (toggle) {
                toggle.addEventListener('click', function (e) {
                    e.stopPropagation();
                    const isActive = card.querySelector('.shutter-card')?.getAttribute('aria-expanded') === 'true';

                    if (isActive) {
                        deactivateAll(cards);
                        toggle.focus();
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
