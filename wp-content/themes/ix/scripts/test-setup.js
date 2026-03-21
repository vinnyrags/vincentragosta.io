/**
 * Shared Vitest Setup
 *
 * Runs before every test file across all themes that use this parent's base config.
 * Provides:
 *   - @testing-library/jest-dom custom matchers (toBeVisible, toHaveAttribute, etc.)
 *   - matchMedia mock (jsdom doesn't implement it)
 *   - IntersectionObserver mock with a triggerable helper
 *   - afterEach cleanup (DOM, localStorage, documentElement)
 */

import '@testing-library/jest-dom/vitest';

// Mock matchMedia (jsdom doesn't implement it)
Object.defineProperty(window, 'matchMedia', {
    writable: true,
    value: vi.fn().mockImplementation((query) => ({
        matches: false,
        media: query,
        onchange: null,
        addListener: vi.fn(),
        removeListener: vi.fn(),
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
        dispatchEvent: vi.fn(),
    })),
});

// Mock IntersectionObserver
const intersectionObserverEntries = new Map();

class MockIntersectionObserver {
    constructor(callback, options) {
        this._callback = callback;
        this._options = options;
        this._elements = new Set();
        intersectionObserverEntries.set(this, this._elements);
    }

    observe(element) {
        this._elements.add(element);
    }

    unobserve(element) {
        this._elements.delete(element);
    }

    disconnect() {
        this._elements.clear();
    }

    // Test helper: trigger intersection for observed elements
    trigger(entries) {
        this._callback(entries, this);
    }
}

Object.defineProperty(window, 'IntersectionObserver', {
    writable: true,
    value: MockIntersectionObserver,
});

// Cleanup after each test
afterEach(() => {
    document.body.innerHTML = '';
    document.documentElement.className = '';
    document.documentElement.removeAttribute('style');
    localStorage.clear();
    intersectionObserverEntries.clear();
});
