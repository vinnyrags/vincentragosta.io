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

describe('activateCard', () => {
    it('sets is-inactive on non-active cards', () => {
        const { cards } = createShutterCards();

        activateCard(cards, cards[1]);

        expect(cards[0].classList.contains('is-inactive')).toBe(true);
        expect(cards[1].classList.contains('is-inactive')).toBe(false);
        expect(cards[2].classList.contains('is-inactive')).toBe(true);
    });

    it('sets aria-expanded on inner card', () => {
        const { cards } = createShutterCards();

        activateCard(cards, cards[0]);

        expect(cards[0].querySelector('.shutter-card').getAttribute('aria-expanded')).toBe('true');
        expect(cards[1].querySelector('.shutter-card').getAttribute('aria-expanded')).toBe('false');
    });

    it('sets tabindex=-1 on active card, tabindex=0 on inactive', () => {
        const { cards } = createShutterCards();

        activateCard(cards, cards[0]);

        expect(cards[0].getAttribute('tabindex')).toBe('-1');
        expect(cards[1].getAttribute('tabindex')).toBe('0');
        expect(cards[2].getAttribute('tabindex')).toBe('0');
    });

    it('sets role=button on inactive cards, removes from active', () => {
        const { cards } = createShutterCards();

        activateCard(cards, cards[1]);

        expect(cards[0].getAttribute('role')).toBe('button');
        expect(cards[1].hasAttribute('role')).toBe(false);
        expect(cards[2].getAttribute('role')).toBe('button');
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
        expect(cards[0].classList.contains('is-inactive')).toBe(false);
        expect(cards[1].classList.contains('is-inactive')).toBe(true);
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

        expect(cards[2].classList.contains('is-inactive')).toBe(false);
        expect(cards[0].classList.contains('is-inactive')).toBe(true);
    });

    it('activates card on Enter key', () => {
        createShutterCards();

        initShutterCards();

        const cards = document.querySelectorAll('.wp-block-child-theme-shutter-card');
        cards[1].dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));

        expect(cards[1].classList.contains('is-inactive')).toBe(false);
        expect(cards[0].classList.contains('is-inactive')).toBe(true);
    });

    it('activates card on Space key', () => {
        createShutterCards();

        initShutterCards();

        const cards = document.querySelectorAll('.wp-block-child-theme-shutter-card');
        cards[1].dispatchEvent(new KeyboardEvent('keydown', { key: ' ', bubbles: true }));

        expect(cards[1].classList.contains('is-inactive')).toBe(false);
    });

    it('does not activate already-active card on click', () => {
        createShutterCards();

        initShutterCards();

        const cards = document.querySelectorAll('.wp-block-child-theme-shutter-card');
        // First card is already active, clicking it should not change state
        cards[0].click();

        expect(cards[0].classList.contains('is-inactive')).toBe(false);
    });

    it('toggle button collapses all cards when active', () => {
        createShutterCards();

        initShutterCards();

        const cards = document.querySelectorAll('.wp-block-child-theme-shutter-card');
        // cards[0] is active — click its toggle to collapse all
        const toggle = cards[0].querySelector('.shutter-card__toggle');
        toggle.click();

        expect(cards[0].classList.contains('is-inactive')).toBe(true);
        expect(cards[1].classList.contains('is-inactive')).toBe(true);
        expect(cards[2].classList.contains('is-inactive')).toBe(true);
    });

    it('toggle button on last card collapses all cards', () => {
        createShutterCards();

        initShutterCards();

        const cards = document.querySelectorAll('.wp-block-child-theme-shutter-card');
        // Activate last card, then toggle
        cards[2].click();
        const toggle = cards[2].querySelector('.shutter-card__toggle');
        toggle.click();

        expect(cards[0].classList.contains('is-inactive')).toBe(true);
        expect(cards[1].classList.contains('is-inactive')).toBe(true);
        expect(cards[2].classList.contains('is-inactive')).toBe(true);
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
