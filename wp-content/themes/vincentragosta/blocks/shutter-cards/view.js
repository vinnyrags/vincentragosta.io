document.addEventListener('DOMContentLoaded', function () {
    const shutterCardsContainers = document.querySelectorAll('.wp-block-vincentragosta-shutter-cards .shutter-cards-container');

    shutterCardsContainers.forEach(container => {
        const cards = container.querySelectorAll('.shutter-card');

        if (cards.length >= 2) {
            // Add inactive class to all but the first card on page load
            cards.forEach((card, index) => {
                if (index > 0) {
                    card.classList.add('shutter-card--inactive');
                }
            });

            // The only job is to handle clicks. PHP and CSS handle the layout.
            cards.forEach(card => {
                card.addEventListener('click', function () {
                    if (this.classList.contains('shutter-card--inactive')) {
                        cards.forEach(c => c.classList.add('shutter-card--inactive'));
                        this.classList.remove('shutter-card--inactive');
                    }
                });
            });

            // Remove preload class after all calculations are done
            setTimeout(() => {
                container.closest('.wp-block-vincentragosta-shutter-cards').classList.remove('shutter-cards--preload');
            }, 500)
        }
    });
});