import { describe, it, expect, vi, beforeEach } from 'vitest';
import {
    STORAGE_KEY,
    LIGHT_MODE_CLASS,
    prefersLightMode,
    updateModeToggleState,
    updateMenuToggleState,
    getFocusableElements,
    toggleOverlay,
    initModeToggle,
} from '../../../../../../src/Providers/Theme/assets/js/header.js';

describe('prefersLightMode', () => {
    it('returns true when localStorage has "light"', () => {
        localStorage.setItem(STORAGE_KEY, 'light');

        expect(prefersLightMode()).toBe(true);
    });

    it('returns false when localStorage has "dark"', () => {
        localStorage.setItem(STORAGE_KEY, 'dark');

        expect(prefersLightMode()).toBe(false);
    });

    it('falls back to matchMedia when no saved preference', () => {
        window.matchMedia = vi.fn().mockImplementation((query) => ({
            matches: query === '(prefers-color-scheme: light)',
            media: query,
            addEventListener: vi.fn(),
            removeEventListener: vi.fn(),
        }));

        expect(prefersLightMode()).toBe(true);
    });

    it('returns false when matchMedia prefers dark and no saved preference', () => {
        window.matchMedia = vi.fn().mockReturnValue({
            matches: false,
            media: '',
            addEventListener: vi.fn(),
            removeEventListener: vi.fn(),
        });

        expect(prefersLightMode()).toBe(false);
    });
});

describe('updateModeToggleState', () => {
    it('sets aria-pressed to true and label for light mode', () => {
        const toggle = document.createElement('button');

        updateModeToggleState(toggle, true);

        expect(toggle.getAttribute('aria-pressed')).toBe('true');
        expect(toggle.getAttribute('aria-label')).toBe('Switch to dark mode');
    });

    it('sets aria-pressed to false and label for dark mode', () => {
        const toggle = document.createElement('button');

        updateModeToggleState(toggle, false);

        expect(toggle.getAttribute('aria-pressed')).toBe('false');
        expect(toggle.getAttribute('aria-label')).toBe('Switch to light mode');
    });
});

describe('updateMenuToggleState', () => {
    it('sets aria-expanded to true and label when open', () => {
        const toggle = document.createElement('button');

        updateMenuToggleState(toggle, true);

        expect(toggle.getAttribute('aria-expanded')).toBe('true');
        expect(toggle.getAttribute('aria-label')).toBe('Close menu');
    });

    it('sets aria-expanded to false and label when closed', () => {
        const toggle = document.createElement('button');

        updateMenuToggleState(toggle, false);

        expect(toggle.getAttribute('aria-expanded')).toBe('false');
        expect(toggle.getAttribute('aria-label')).toBe('Open menu');
    });
});

describe('getFocusableElements', () => {
    it('finds links and buttons', () => {
        const container = document.createElement('div');
        container.innerHTML = `
            <a href="/home">Home</a>
            <button>Click me</button>
            <a href="/about">About</a>
        `;
        document.body.appendChild(container);

        const elements = getFocusableElements(container);

        expect(elements).toHaveLength(3);
    });

    it('excludes disabled buttons', () => {
        const container = document.createElement('div');
        container.innerHTML = `
            <button>Enabled</button>
            <button disabled>Disabled</button>
        `;
        document.body.appendChild(container);

        const elements = getFocusableElements(container);

        expect(elements).toHaveLength(1);
        expect(elements[0].textContent).toBe('Enabled');
    });

    it('excludes elements with tabindex="-1"', () => {
        const container = document.createElement('div');
        container.innerHTML = `
            <a href="/home">Home</a>
            <div tabindex="-1">Not focusable</div>
            <div tabindex="0">Focusable</div>
        `;
        document.body.appendChild(container);

        const elements = getFocusableElements(container);

        expect(elements).toHaveLength(2);
    });

    it('returns empty array for container with no focusable elements', () => {
        const container = document.createElement('div');
        container.innerHTML = '<p>No focusable elements</p>';
        document.body.appendChild(container);

        const elements = getFocusableElements(container);

        expect(elements).toHaveLength(0);
    });
});

describe('toggleOverlay', () => {
    function createOverlayFixture() {
        const toggle = document.createElement('button');
        toggle.setAttribute('aria-expanded', 'false');

        const overlay = document.createElement('div');
        overlay.id = 'nav-overlay';
        overlay.setAttribute('hidden', '');
        overlay.innerHTML = `
            <a href="/home">Home</a>
            <a href="/about">About</a>
        `;

        document.body.appendChild(toggle);
        document.body.appendChild(overlay);

        return { toggle, overlay };
    }

    it('opens overlay: removes hidden, adds is-open, adds nav-overlay-open to html', () => {
        const { toggle, overlay } = createOverlayFixture();

        toggleOverlay(toggle, overlay, true);

        expect(overlay.hasAttribute('hidden')).toBe(false);
        expect(overlay.classList.contains('is-open')).toBe(true);
        expect(document.documentElement.classList.contains('nav-overlay-open')).toBe(true);
    });

    it('updates toggle ARIA when opening', () => {
        const { toggle, overlay } = createOverlayFixture();

        toggleOverlay(toggle, overlay, true);

        expect(toggle.getAttribute('aria-expanded')).toBe('true');
        expect(toggle.getAttribute('aria-label')).toBe('Close menu');
    });

    it('focuses first link when opening', () => {
        const { toggle, overlay } = createOverlayFixture();
        const firstLink = overlay.querySelector('a');
        const focusSpy = vi.spyOn(firstLink, 'focus');

        toggleOverlay(toggle, overlay, true);

        expect(focusSpy).toHaveBeenCalledWith({ preventScroll: true });
    });

    it('closes overlay: removes is-open, removes nav-overlay-open', () => {
        const { toggle, overlay } = createOverlayFixture();

        // Open first
        toggleOverlay(toggle, overlay, true);

        // Mock reduced motion for synchronous hidden
        window.matchMedia = vi.fn().mockReturnValue({
            matches: true,
            media: '',
            addEventListener: vi.fn(),
            removeEventListener: vi.fn(),
        });

        toggleOverlay(toggle, overlay, false);

        expect(overlay.classList.contains('is-open')).toBe(false);
        expect(document.documentElement.classList.contains('nav-overlay-open')).toBe(false);
        expect(overlay.hasAttribute('hidden')).toBe(true);
    });

    it('updates toggle ARIA when closing', () => {
        const { toggle, overlay } = createOverlayFixture();
        toggleOverlay(toggle, overlay, true);

        window.matchMedia = vi.fn().mockReturnValue({
            matches: true,
            media: '',
            addEventListener: vi.fn(),
            removeEventListener: vi.fn(),
        });

        toggleOverlay(toggle, overlay, false);

        expect(toggle.getAttribute('aria-expanded')).toBe('false');
        expect(toggle.getAttribute('aria-label')).toBe('Open menu');
    });
});

describe('initModeToggle', () => {
    it('toggles light-mode class on click', () => {
        const modeToggle = document.createElement('button');
        modeToggle.classList.add('header__mode-toggle');
        document.body.appendChild(modeToggle);

        initModeToggle();
        modeToggle.click();

        expect(document.documentElement.classList.contains(LIGHT_MODE_CLASS)).toBe(true);
    });

    it('persists preference to localStorage on click', () => {
        const modeToggle = document.createElement('button');
        modeToggle.classList.add('header__mode-toggle');
        document.body.appendChild(modeToggle);

        initModeToggle();
        modeToggle.click();

        expect(localStorage.getItem(STORAGE_KEY)).toBe('light');
    });

    it('toggles back to dark on second click', () => {
        const modeToggle = document.createElement('button');
        modeToggle.classList.add('header__mode-toggle');
        document.body.appendChild(modeToggle);

        initModeToggle();
        modeToggle.click(); // light
        modeToggle.click(); // dark

        expect(document.documentElement.classList.contains(LIGHT_MODE_CLASS)).toBe(false);
        expect(localStorage.getItem(STORAGE_KEY)).toBe('dark');
    });

    it('does nothing when toggle element is missing', () => {
        // No .header__mode-toggle in DOM
        expect(() => initModeToggle()).not.toThrow();
    });
});
