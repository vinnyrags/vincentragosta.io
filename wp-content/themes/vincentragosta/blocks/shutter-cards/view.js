/**
 * Frontend script for the Shutter Cards block.
 * Handles the click-to-open functionality.
 */
document.addEventListener('DOMContentLoaded', function () {
    const shutterBlocks = document.querySelectorAll('.wp-block-vincentragosta-shutter-cards');

    shutterBlocks.forEach((block) => {
        const cards = block.querySelectorAll('.shutter-card');

        cards.forEach((card) => {
            const handleClick = () => {
                // If the card is already open, do nothing.
                if (card.classList.contains('is-open')) {
                    return;
                }

                // Close any currently open card in this block instance.
                const currentlyOpen = block.querySelector('.shutter-card.is-open');
                if (currentlyOpen) {
                    currentlyOpen.classList.remove('is-open');
                }

                // Open the clicked card.
                card.classList.add('is-open');
            };

            card.addEventListener('click', handleClick);
            card.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    handleClick();
                }
            });
        });
    });
});