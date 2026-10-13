/**
 * Header functionality
 *
 * The header is a content partial built from core blocks; core/navigation
 * owns the mobile overlay (open/close, focus, Escape). This module adds:
 * - Dynamic header height (CSS custom property, 0 when a page has no header)
 * - A Cmd/Ctrl+K shortcut that focuses the header's core/search input
 */

export const SEARCH_INPUT_SELECTOR = '.header .wp-block-search__input';

/**
 * Calculate and set header height as CSS custom property.
 * Pages whose header partial is disabled render no header — use 0.
 */
export function setHeaderHeight() {
    const header = document.querySelector('.header');
    const height = header ? header.offsetHeight : 0;

    document.documentElement.style.setProperty('--header-height', `${height}px`);
}

/**
 * Initialize header height calculation with resize handling
 */
export function initHeaderHeight() {
    setHeaderHeight();

    // Recalculate on resize with debounce
    let resizeTimeout;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(setHeaderHeight, 100);
    });
}

/**
 * Whether a keydown event is the search shortcut (Cmd+K on macOS, Ctrl+K elsewhere).
 * @param {KeyboardEvent} e
 * @returns {boolean}
 */
export function isSearchShortcut(e) {
    return (e.metaKey || e.ctrlKey) && !e.altKey && !e.shiftKey && e.key.toLowerCase() === 'k';
}

/**
 * Focus the header search input. Returns false when there is no search block
 * in the header (e.g. a custom header partial without one).
 * @returns {boolean}
 */
export function focusSearch() {
    const input = document.querySelector(SEARCH_INPUT_SELECTOR);
    if (!input) return false;

    input.focus();
    input.select();
    return true;
}

/**
 * Initialize the Cmd/Ctrl+K search shortcut and Escape-to-blur
 */
export function initSearchShortcut() {
    document.addEventListener('keydown', (e) => {
        if (isSearchShortcut(e)) {
            if (focusSearch()) {
                e.preventDefault();
            }
            return;
        }

        if (e.key === 'Escape' && document.activeElement?.matches(SEARCH_INPUT_SELECTOR)) {
            document.activeElement.blur();
        }
    });
}

/**
 * Initialize all header functionality
 */
export function initHeader() {
    initHeaderHeight();
    initSearchShortcut();
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initHeader);
} else {
    initHeader();
}
