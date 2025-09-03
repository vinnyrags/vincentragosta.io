document.addEventListener('DOMContentLoaded', function () {
    const shutterCardsContainers = document.querySelectorAll('.wp-block-vincentragosta-shutter-cards .shutter-cards-container');

    shutterCardsContainers.forEach(container => {
        const cards = container.querySelectorAll('.shutter-card');

        if (cards.length >= 2) {
            // The only job is to handle clicks. PHP and CSS handle the layout.
            cards.forEach(card => {
                card.addEventListener('click', function () {
                    if (!this.classList.contains('shutter-card--inactive')) {
                        return;
                    }

                    container.querySelector('.shutter-card:not(.shutter-card--inactive)')?.classList.add('shutter-card--inactive');
                    this.classList.remove('shutter-card--inactive');
                });
            });
        }
    });
});