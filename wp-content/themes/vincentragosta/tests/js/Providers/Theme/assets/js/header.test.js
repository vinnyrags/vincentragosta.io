import { describe, it, expect } from 'vitest';
import {
    setHeaderHeight,
    isSearchShortcut,
    focusSearch,
    initSearchShortcut,
} from '../../../../../../src/Providers/Theme/assets/js/header.js';

const searchHeader = () => {
    document.body.innerHTML = `
        <header class="header">
            <form class="wp-block-search">
                <input class="wp-block-search__input" type="search" value="query" />
            </form>
        </header>`;
    return document.querySelector('.wp-block-search__input');
};

describe('setHeaderHeight', () => {
    it('sets --header-height from the header element', () => {
        document.body.innerHTML = '<header class="header"></header>';
        Object.defineProperty(document.querySelector('.header'), 'offsetHeight', { value: 82 });

        setHeaderHeight();

        expect(document.documentElement.style.getPropertyValue('--header-height')).toBe('82px');
    });

    it('sets --header-height to 0 when the page has no header', () => {
        document.body.innerHTML = '';

        setHeaderHeight();

        expect(document.documentElement.style.getPropertyValue('--header-height')).toBe('0px');
    });
});

describe('isSearchShortcut', () => {
    const key = (init) => new KeyboardEvent('keydown', init);

    it('matches Cmd+K', () => {
        expect(isSearchShortcut(key({ key: 'k', metaKey: true }))).toBe(true);
    });

    it('matches Ctrl+K and uppercase K', () => {
        expect(isSearchShortcut(key({ key: 'K', ctrlKey: true }))).toBe(true);
    });

    it('ignores plain K and other modifier combinations', () => {
        expect(isSearchShortcut(key({ key: 'k' }))).toBe(false);
        expect(isSearchShortcut(key({ key: 'k', metaKey: true, shiftKey: true }))).toBe(false);
        expect(isSearchShortcut(key({ key: 'k', ctrlKey: true, altKey: true }))).toBe(false);
        expect(isSearchShortcut(key({ key: 'j', metaKey: true }))).toBe(false);
    });
});

describe('focusSearch', () => {
    it('focuses the header search input and reports success', () => {
        const input = searchHeader();

        expect(focusSearch()).toBe(true);
        expect(document.activeElement).toBe(input);
    });

    it('returns false when the header has no search block', () => {
        document.body.innerHTML = '<header class="header"></header>';

        expect(focusSearch()).toBe(false);
    });
});

describe('initSearchShortcut', () => {
    it('Cmd+K focuses search and prevents the browser default', () => {
        const input = searchHeader();
        initSearchShortcut();

        const event = new KeyboardEvent('keydown', { key: 'k', metaKey: true, cancelable: true });
        document.dispatchEvent(event);

        expect(document.activeElement).toBe(input);
        expect(event.defaultPrevented).toBe(true);
    });

    it('leaves the browser default alone when there is no search block', () => {
        document.body.innerHTML = '<header class="header"></header>';
        initSearchShortcut();

        const event = new KeyboardEvent('keydown', { key: 'k', ctrlKey: true, cancelable: true });
        document.dispatchEvent(event);

        expect(event.defaultPrevented).toBe(false);
    });

    it('Escape blurs the focused search input', () => {
        const input = searchHeader();
        initSearchShortcut();
        input.focus();

        input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));

        expect(document.activeElement).not.toBe(input);
    });
});
