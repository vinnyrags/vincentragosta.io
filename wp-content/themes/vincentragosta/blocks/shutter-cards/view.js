document.addEventListener('DOMContentLoaded', function () {
    const shutterCardsContainers = document.querySelectorAll('.wp-block-vincentragosta-shutter-cards .shutter-cards-container');

    shutterCardsContainers.forEach(container => {
        const cards = container.querySelectorAll('.shutter-card');

        // Function to set min-height for all cards based on container height
        function setMinHeight() {
            if (window.innerWidth <= 768) {
                // Reset min-height for mobile
                cards.forEach(card => {
                    card.style.minHeight = 'auto';
                });
                return;
            }
            let containerHeight = container.offsetHeight;
            cards.forEach(card => {
                card.style.minHeight = `${containerHeight}px`;
            });
        }

        // Throttle function for resize event
        let throttleTimeout = null;
        function throttledSetMinHeight() {
            if (throttleTimeout) {
                clearTimeout(throttleTimeout);
            }
            throttleTimeout = setTimeout(() => {
                setMinHeight();
            }, 50);
        }

        if (cards.length >= 2) {
            // Add inactive class to all but the first card on page load
            cards.forEach((card, index) => {
                if (index > 0) {
                    card.classList.add('shutter-card--inactive');
                }
            });

            // Set initial min-height on page load after inactive classes are applied
            throttledSetMinHeight();

            // Update min-height on throttled resize
            window.addEventListener('resize', throttledSetMinHeight);

            // Recalculate min-height after all resources (images, fonts) are loaded
            window.addEventListener('load', throttledSetMinHeight);

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