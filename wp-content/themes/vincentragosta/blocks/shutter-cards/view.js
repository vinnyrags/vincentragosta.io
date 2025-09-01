document.addEventListener('DOMContentLoaded', function () {
    const shutterCardsContainers = document.querySelectorAll('.wp-block-vincentragosta-shutter-cards .shutter-cards-container');

    shutterCardsContainers.forEach(container => {
        const cards = container.querySelectorAll('.shutter-card');

        if (cards.length >= 2) {
            // By default, PHP adds '--inactive'. We make the first card active by removing the class.
            cards[0].classList.remove('shutter-card--inactive');

            cards.forEach(card => {
                card.addEventListener('click', function () {
                    // If this card is already active (doesn't have the class), do nothing.
                    if (!this.classList.contains('shutter-card--inactive')) {
                        return;
                    }

                    // First, add the inactive class to all cards in this group.
                    cards.forEach(c => c.classList.add('shutter-card--inactive'));

                    // Then, activate the clicked card by removing the inactive class.
                    this.classList.remove('shutter-card--inactive');
                });
            });
        }
    });
});