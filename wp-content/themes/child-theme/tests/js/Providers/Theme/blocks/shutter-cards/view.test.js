import { describe, it, expect, vi } from 'vitest';
import { activateCard, initShutterCards } from '../../../../../../src/Providers/Theme/blocks/shutter-cards/view.js';

/**
 * Build a shutter-cards container with N cards
 */
function createShutterCards(count = 3) {
    const container = document.createElement('div');
    container.classList.add('shutter-cards', 'shutter-cards--preload');

    const cards = [];
    for (let i = 0; i < count; i++) {
        const card = document.createElement('div');
        card.classList.add('wp-block-child-theme-shutter-card');

        const inner = document.createElement('div');
        inner.classList.add('shutter-card');

        const toggle = document.createElement('button');
        toggle.classList.add('shutter-card__toggle');
        toggle.setAttribute('aria-label', 'Expand card');
        toggle.setAttribute('aria-expanded', 'false');

        inner.appendChild(toggle);
        card.appendChild(inner);
        container.appendChild(card);
        cards.push(card);
    }

    document.body.appendChild(container);
    return { container, cards };
}

/**
 * Check if a card is inactive by reading aria-expanded on the inner .shutter-card
 */
function isInactive(card) {
    return card.querySelector('.shutter-card')?.getAttribute('aria-expanded') === 'false';
}

describe('activateCard', () => {
    it('sets aria-expanded="false" on non-active cards', () => {
        const { cards } = createShutterCards();

        activateCard(cards, cards[1]);

        expect(isInactive(cards[0])).toBe(true);
        expect(isInactive(cards[1])).toBe(false);
        expect(isInactive(cards[2])).toBe(true);
    });

    it('sets aria-expanded on inner card', () => {
        const { cards } = createShutterCards();

        activateCard(cards, cards[0]);

        expect(cards[0].querySelector('.shutter-card').getAttribute('aria-expanded')).toBe('true');
        expect(cards[1].querySelector('.shutter-card').getAttribute('aria-expanded')).toBe('false');
    });

    it('removes tabindex and role from all cards — toggle button is sole tab stop', () => {
        const { cards } = createShutterCards();

        activateCard(cards, cards[0]);

        cards.forEach((card) => {
            expect(card.hasAttribute('tabindex')).toBe(false);
            expect(card.hasAttribute('role')).toBe(false);
        });
    });

    it('updates toggle button aria-label and aria-expanded', () => {
        const { cards } = createShutterCards();

        activateCard(cards, cards[0]);

        const activeToggle = cards[0].querySelector('.shutter-card__toggle');
        expect(activeToggle.getAttribute('aria-label')).toBe('Collapse card');
        expect(activeToggle.getAttribute('aria-expanded')).toBe('true');

        const inactiveToggle = cards[1].querySelector('.shutter-card__toggle');
        expect(inactiveToggle.getAttribute('aria-label')).toBe('Expand card');
        expect(inactiveToggle.getAttribute('aria-expanded')).toBe('false');
    });
});

describe('initShutterCards', () => {
    it('activates first card by default', () => {
        createShutterCards();

        initShutterCards();

        const cards = document.querySelectorAll('.wp-block-child-theme-shutter-card');
        expect(isInactive(cards[0])).toBe(false);
        expect(isInactive(cards[1])).toBe(true);
    });

    it('removes preload class from container', () => {
        const { container } = createShutterCards();

        initShutterCards();

        expect(container.classList.contains('shutter-cards--preload')).toBe(false);
    });

    it('activates card on click', () => {
        createShutterCards();

        initShutterCards();

        const cards = document.querySelectorAll('.wp-block-child-theme-shutter-card');
        cards[2].click(); // Click third card

        expect(isInactive(cards[2])).toBe(false);
        expect(isInactive(cards[0])).toBe(true);
    });

    it('activates card when toggle button is clicked on inactive card', () => {
        createShutterCards();

        initShutterCards();

        const cards = document.querySelectorAll('.wp-block-child-theme-shutter-card');
        const toggle = cards[1].querySelector('.shutter-card__toggle');
        toggle.click();

        expect(isInactive(cards[1])).toBe(false);
        expect(isInactive(cards[0])).toBe(true);
    });

    it('does not activate already-active card on click', () => {
        createShutterCards();

        initShutterCards();

        const cards = document.querySelectorAll('.wp-block-child-theme-shutter-card');
        // First card is already active, clicking it should not change state
        cards[0].click();

        expect(isInactive(cards[0])).toBe(false);
    });

    it('toggle button collapses all cards when active', () => {
        createShutterCards();

        initShutterCards();

        const cards = document.querySelectorAll('.wp-block-child-theme-shutter-card');
        // cards[0] is active — click its toggle to collapse all
        const toggle = cards[0].querySelector('.shutter-card__toggle');
        toggle.click();

        expect(isInactive(cards[0])).toBe(true);
        expect(isInactive(cards[1])).toBe(true);
        expect(isInactive(cards[2])).toBe(true);
    });

    it('toggle button on last card collapses all cards', () => {
        createShutterCards();

        initShutterCards();

        const cards = document.querySelectorAll('.wp-block-child-theme-shutter-card');
        // Activate last card, then toggle
        cards[2].click();
        const toggle = cards[2].querySelector('.shutter-card__toggle');
        toggle.click();

        expect(isInactive(cards[0])).toBe(true);
        expect(isInactive(cards[1])).toBe(true);
        expect(isInactive(cards[2])).toBe(true);
    });

    it('skips containers with fewer than 2 cards', () => {
        // Create a container with only 1 card
        const container = document.createElement('div');
        container.classList.add('shutter-cards', 'shutter-cards--preload');
        const card = document.createElement('div');
        card.classList.add('wp-block-child-theme-shutter-card');
        container.appendChild(card);
        document.body.appendChild(container);

        initShutterCards();

        // Preload class should remain (not initialized)
        expect(container.classList.contains('shutter-cards--preload')).toBe(true);
    });
});
