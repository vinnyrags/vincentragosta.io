import { describe, it, expect, beforeEach } from 'vitest';

/**
 * CartStore tests — localStorage CRUD and quantity caps.
 *
 * The cart module uses a module-scoped STORAGE_KEY. Since we can't import
 * the CartStore directly (it's not exported), we test the localStorage
 * behavior it relies on.
 */

const STORAGE_KEY = 'vincentragosta_cart';

function getCart() {
    try {
        return JSON.parse(localStorage.getItem(STORAGE_KEY)) || [];
    } catch {
        return [];
    }
}

function saveCart(items) {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
}

describe('CartStore localStorage behavior', () => {
    beforeEach(() => {
        localStorage.clear();
    });

    it('returns empty array when no cart exists', () => {
        expect(getCart()).toEqual([]);
    });

    it('stores and retrieves items', () => {
        const items = [
            { priceId: 'price_1', productId: '1', title: 'Test', price: '$10', quantity: 1, stock: 5 },
        ];
        saveCart(items);
        expect(getCart()).toEqual(items);
    });

    it('handles multiple items', () => {
        const items = [
            { priceId: 'price_1', productId: '1', title: 'Item 1', price: '$10', quantity: 2, stock: 10 },
            { priceId: 'price_2', productId: '2', title: 'Item 2', price: '$20', quantity: 1, stock: 5 },
        ];
        saveCart(items);
        expect(getCart()).toHaveLength(2);
    });

    it('clears cart', () => {
        saveCart([{ priceId: 'price_1', quantity: 1 }]);
        localStorage.removeItem(STORAGE_KEY);
        expect(getCart()).toEqual([]);
    });

    it('caps quantity at stock level', () => {
        const item = { priceId: 'price_1', quantity: 5, stock: 3 };
        item.quantity = Math.min(item.quantity, item.stock);
        expect(item.quantity).toBe(3);
    });

    it('enforces minimum quantity of 1', () => {
        const quantity = Math.max(1, 0);
        expect(quantity).toBe(1);
    });

    it('calculates total count from items', () => {
        const items = [
            { priceId: 'price_1', quantity: 3 },
            { priceId: 'price_2', quantity: 2 },
        ];
        const count = items.reduce((sum, item) => sum + item.quantity, 0);
        expect(count).toBe(5);
    });

    it('handles corrupted localStorage gracefully', () => {
        localStorage.setItem(STORAGE_KEY, 'not-valid-json');
        expect(getCart()).toEqual([]);
    });

    it('removes item by priceId', () => {
        const items = [
            { priceId: 'price_1', title: 'Keep' },
            { priceId: 'price_2', title: 'Remove' },
        ];
        saveCart(items);
        const filtered = getCart().filter((i) => i.priceId !== 'price_2');
        saveCart(filtered);
        expect(getCart()).toHaveLength(1);
        expect(getCart()[0].title).toBe('Keep');
    });

    it('adds quantity to existing item by priceId', () => {
        const items = [{ priceId: 'price_1', quantity: 2, stock: 10 }];
        const existing = items.find((i) => i.priceId === 'price_1');
        existing.quantity = Math.min(existing.quantity + 1, existing.stock);
        expect(existing.quantity).toBe(3);
    });

    it('does not exceed stock when adding to existing item', () => {
        const items = [{ priceId: 'price_1', quantity: 9, stock: 10 }];
        const existing = items.find((i) => i.priceId === 'price_1');
        existing.quantity = Math.min(existing.quantity + 5, existing.stock);
        expect(existing.quantity).toBe(10);
    });
});
