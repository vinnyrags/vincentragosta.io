/**
 * Header functionality
 * - Dark/light mode toggle with localStorage persistence and system preference fallback
 * - Hamburger menu toggle with ARIA updates
 * - Dynamic header height calculation
 */

const STORAGE_KEY = 'theme-mode';
const LIGHT_MODE_CLASS = 'light-mode';

/**
 * Calculate and set header height as CSS custom property
 */
function setHeaderHeight() {
    const header = document.querySelector('.header');
    if (!header) return;

    const height = header.offsetHeight;
    document.documentElement.style.setProperty('--header-height', `${height}px`);
}

/**
 * Initialize header height calculation with resize handling
 */
function initHeaderHeight() {
    setHeaderHeight();

    // Recalculate on resize with debounce
    let resizeTimeout;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(setHeaderHeight, 100);
    });
}

/**
 * Check if user prefers light mode (from localStorage or system preference)
 * @returns {boolean}
 */
function prefersLightMode() {
    const saved = localStorage.getItem(STORAGE_KEY);
    if (saved) {
        return saved === 'light';
    }
    return window.matchMedia('(prefers-color-scheme: light)').matches;
}

/**
 * Initialize mode toggle functionality
 */
function initModeToggle() {
    const modeToggle = document.querySelector('.header__mode-toggle');
    if (!modeToggle) return;

    // Check current state (may have been set by inline script in head)
    const isLightMode = document.documentElement.classList.contains(LIGHT_MODE_CLASS);
    updateModeToggleState(modeToggle, isLightMode);

    // Handle toggle click
    modeToggle.addEventListener('click', () => {
        const newIsLightMode = document.documentElement.classList.toggle(LIGHT_MODE_CLASS);
        updateModeToggleState(modeToggle, newIsLightMode);

        // Persist preference
        localStorage.setItem(STORAGE_KEY, newIsLightMode ? 'light' : 'dark');
    });

    // Listen for system preference changes (when no saved preference)
    window.matchMedia('(prefers-color-scheme: light)').addEventListener('change', (e) => {
        if (!localStorage.getItem(STORAGE_KEY)) {
            const shouldBeLightMode = e.matches;
            document.documentElement.classList.toggle(LIGHT_MODE_CLASS, shouldBeLightMode);
            updateModeToggleState(modeToggle, shouldBeLightMode);
        }
    });
}

/**
 * Update mode toggle ARIA attributes
 * @param {HTMLElement} toggle - The toggle button element
 * @param {boolean} isLightMode - Whether light mode is active
 */
function updateModeToggleState(toggle, isLightMode) {
    toggle.setAttribute('aria-pressed', isLightMode ? 'true' : 'false');
    toggle.setAttribute(
        'aria-label',
        isLightMode ? 'Switch to dark mode' : 'Switch to light mode'
    );
}

/**
 * Initialize hamburger menu toggle functionality
 */
function initMenuToggle() {
    const menuToggle = document.querySelector('.header__menu-toggle');
    if (!menuToggle) return;

    menuToggle.addEventListener('click', () => {
        const isExpanded = menuToggle.getAttribute('aria-expanded') === 'true';
        updateMenuToggleState(menuToggle, !isExpanded);
    });

    // Close menu on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            const isExpanded = menuToggle.getAttribute('aria-expanded') === 'true';
            if (isExpanded) {
                updateMenuToggleState(menuToggle, false);
                menuToggle.focus();
            }
        }
    });
}

/**
 * Update menu toggle ARIA attributes
 * @param {HTMLElement} toggle - The toggle button element
 * @param {boolean} isOpen - Whether the menu is open
 */
function updateMenuToggleState(toggle, isOpen) {
    toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    toggle.setAttribute('aria-label', isOpen ? 'Close menu' : 'Open menu');
}

/**
 * Initialize all header functionality
 */
function initHeader() {
    initHeaderHeight();
    initModeToggle();
    initMenuToggle();
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initHeader);
} else {
    initHeader();
}
