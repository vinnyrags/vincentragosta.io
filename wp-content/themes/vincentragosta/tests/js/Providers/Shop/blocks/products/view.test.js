import { describe, it, expect, beforeEach } from 'vitest';
import {
    sortCards,
    filterBySearch,
    filterByTaxonomy,
    applyVisibility,
} from '../../../../../../src/Providers/Theme/assets/js/grid-toolkit.js';

const CARD = '.product-card';
const HIDDEN_ATTRS = ['data-search-hidden', 'data-category-hidden'];

/**
 * Build a product grid with cards.
 */
function createProductGrid(products) {
    const grid = document.createElement('div');
    grid.classList.add('products-grid');

    products.forEach(({ title, date, category, stock, sale }) => {
        const card = document.createElement('div');
        card.classList.add('product-card');
        card.dataset.title = title.toLowerCase();
        card.dataset.date = date;
        if (category) card.dataset.category = category;
        if (stock !== undefined) card.dataset.stock = String(stock);
        if (sale !== undefined) card.dataset.sale = sale ? '1' : '0';
        card.textContent = title;
        grid.appendChild(card);
    });

    document.body.appendChild(grid);
    return grid;
}

function getCardTitles(grid) {
    return Array.from(grid.querySelectorAll(CARD)).map((c) => c.textContent);
}

function getVisibleCards(grid) {
    return Array.from(grid.querySelectorAll('.product-card:not([aria-hidden="true"])'));
}

describe('sortCards with numeric values', () => {
    const products = [
        { title: 'Low Stock', date: '2026-01-01', stock: 5, sale: false },
        { title: 'High Stock', date: '2026-02-01', stock: 45, sale: false },
        { title: 'Mid Stock', date: '2026-03-01', stock: 20, sale: false },
    ];

    it('sorts by stock descending', () => {
        const grid = createProductGrid(products);
        sortCards(grid, CARD, 'stock', 'desc');
        expect(getCardTitles(grid)).toEqual(['High Stock', 'Mid Stock', 'Low Stock']);
    });

    it('sorts by stock ascending', () => {
        const grid = createProductGrid(products);
        sortCards(grid, CARD, 'stock', 'asc');
        expect(getCardTitles(grid)).toEqual(['Low Stock', 'Mid Stock', 'High Stock']);
    });
});

describe('sortCards by title', () => {
    const products = [
        { title: 'Charlie', date: '2026-01-01', stock: 10, sale: false },
        { title: 'Alpha', date: '2026-02-01', stock: 10, sale: false },
        { title: 'Bravo', date: '2026-03-01', stock: 10, sale: false },
    ];

    it('sorts A-Z', () => {
        const grid = createProductGrid(products);
        sortCards(grid, CARD, 'title', 'asc');
        expect(getCardTitles(grid)).toEqual(['Alpha', 'Bravo', 'Charlie']);
    });

    it('sorts Z-A', () => {
        const grid = createProductGrid(products);
        sortCards(grid, CARD, 'title', 'desc');
        expect(getCardTitles(grid)).toEqual(['Charlie', 'Bravo', 'Alpha']);
    });
});

describe('sortCards by date', () => {
    const products = [
        { title: 'Oldest', date: '2025-01-01', stock: 10, sale: false },
        { title: 'Newest', date: '2026-06-01', stock: 10, sale: false },
        { title: 'Middle', date: '2026-03-01', stock: 10, sale: false },
    ];

    it('sorts newest first', () => {
        const grid = createProductGrid(products);
        sortCards(grid, CARD, 'date', 'desc');
        expect(getCardTitles(grid)).toEqual(['Newest', 'Middle', 'Oldest']);
    });
});

describe('sortCards by sale', () => {
    const products = [
        { title: 'Not On Sale', date: '2026-03-01', stock: 10, sale: false },
        { title: 'On Sale', date: '2026-01-01', stock: 10, sale: true },
        { title: 'Also Not', date: '2026-02-01', stock: 10, sale: false },
    ];

    it('sorts sale items to top', () => {
        const grid = createProductGrid(products);
        sortCards(grid, CARD, 'sale', 'desc');
        expect(getCardTitles(grid)[0]).toBe('On Sale');
    });
});

describe('filterBySearch', () => {
    const products = [
        { title: 'Prismatic Evolutions', date: '2026-01-01', stock: 10, sale: false },
        { title: 'Obsidian Flames', date: '2026-02-01', stock: 10, sale: false },
        { title: 'Crown Zenith', date: '2026-03-01', stock: 10, sale: false },
    ];

    it('filters by search query', () => {
        const grid = createProductGrid(products);
        filterBySearch(grid, CARD, 'prismatic', HIDDEN_ATTRS);
        expect(getVisibleCards(grid)).toHaveLength(1);
        expect(getVisibleCards(grid)[0].textContent).toBe('Prismatic Evolutions');
    });

    it('shows all when query is empty', () => {
        const grid = createProductGrid(products);
        filterBySearch(grid, CARD, 'prismatic', HIDDEN_ATTRS);
        filterBySearch(grid, CARD, '', HIDDEN_ATTRS);
        expect(getVisibleCards(grid)).toHaveLength(3);
    });

    it('is case insensitive', () => {
        const grid = createProductGrid(products);
        filterBySearch(grid, CARD, 'OBSIDIAN', HIDDEN_ATTRS);
        expect(getVisibleCards(grid)).toHaveLength(1);
    });
});

describe('filterByTaxonomy', () => {
    const products = [
        { title: 'Pokemon Box', date: '2026-01-01', category: 'pokemon', stock: 10, sale: false },
        { title: 'Anime Box', date: '2026-02-01', category: 'anime', stock: 10, sale: false },
        { title: 'Mature Box', date: '2026-03-01', category: 'mature', stock: 10, sale: false },
    ];

    it('filters by category', () => {
        const grid = createProductGrid(products);
        filterByTaxonomy(grid, CARD, 'category', 'data-category-hidden', 'pokemon', HIDDEN_ATTRS);
        expect(getVisibleCards(grid)).toHaveLength(1);
        expect(getVisibleCards(grid)[0].textContent).toBe('Pokemon Box');
    });

    it('shows all when value is "all"', () => {
        const grid = createProductGrid(products);
        filterByTaxonomy(grid, CARD, 'category', 'data-category-hidden', 'pokemon', HIDDEN_ATTRS);
        filterByTaxonomy(grid, CARD, 'category', 'data-category-hidden', 'all', HIDDEN_ATTRS);
        expect(getVisibleCards(grid)).toHaveLength(3);
    });
});

describe('composite filtering', () => {
    const products = [
        { title: 'Pokemon A', date: '2026-01-01', category: 'pokemon', stock: 10, sale: false },
        { title: 'Pokemon B', date: '2026-02-01', category: 'pokemon', stock: 10, sale: false },
        { title: 'Anime C', date: '2026-03-01', category: 'anime', stock: 10, sale: false },
    ];

    it('search + category filter work together', () => {
        const grid = createProductGrid(products);
        filterByTaxonomy(grid, CARD, 'category', 'data-category-hidden', 'pokemon', HIDDEN_ATTRS);
        filterBySearch(grid, CARD, 'pokemon b', HIDDEN_ATTRS);
        expect(getVisibleCards(grid)).toHaveLength(1);
        expect(getVisibleCards(grid)[0].textContent).toBe('Pokemon B');
    });
});
