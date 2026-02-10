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
 * Get all focusable elements within a container
 * @param {HTMLElement} container
 * @returns {HTMLElement[]}
 */
function getFocusableElements(container) {
    return Array.from(
        container.querySelectorAll('a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])')
    );
}

/**
 * Toggle the navigation overlay open or closed
 * @param {HTMLElement} toggle - The hamburger button
 * @param {HTMLElement} overlay - The nav overlay element
 * @param {boolean} isOpen - Whether to open or close
 */
function toggleOverlay(toggle, overlay, isOpen) {
    updateMenuToggleState(toggle, isOpen);

    if (isOpen) {
        overlay.removeAttribute('hidden');
        overlay.offsetHeight; // force reflow
        overlay.classList.add('is-open');
        document.documentElement.classList.add('nav-overlay-open');

        const firstLink = overlay.querySelector('a');
        if (firstLink) {
            firstLink.focus({ preventScroll: true });
        }
    } else {
        overlay.classList.remove('is-open');
        document.documentElement.classList.remove('nav-overlay-open');

        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (prefersReducedMotion) {
            overlay.setAttribute('hidden', '');
        } else {
            overlay.addEventListener('transitionend', function handler(e) {
                if (e.propertyName === 'opacity') {
                    overlay.setAttribute('hidden', '');
                    overlay.removeEventListener('transitionend', handler);
                }
            });
        }
    }
}

/**
 * Initialize hamburger menu toggle functionality
 */
function initMenuToggle() {
    const menuToggle = document.querySelector('.header__menu-toggle');
    const overlay = document.getElementById('nav-overlay');
    if (!menuToggle || !overlay) return;

    menuToggle.addEventListener('click', () => {
        const isExpanded = menuToggle.getAttribute('aria-expanded') === 'true';
        toggleOverlay(menuToggle, overlay, !isExpanded);
    });

    // Close overlay when a nav link is clicked
    overlay.addEventListener('click', (e) => {
        if (e.target.closest('a')) {
            toggleOverlay(menuToggle, overlay, false);
        }
    });

    // Close menu on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            const isExpanded = menuToggle.getAttribute('aria-expanded') === 'true';
            if (isExpanded) {
                toggleOverlay(menuToggle, overlay, false);
                menuToggle.focus();
            }
        }
    });

    // Focus trapping within overlay
    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Tab') return;
        const isExpanded = menuToggle.getAttribute('aria-expanded') === 'true';
        if (!isExpanded) return;

        const focusable = [menuToggle, ...getFocusableElements(overlay)];
        const firstEl = focusable[0];
        const lastEl = focusable[focusable.length - 1];

        if (e.shiftKey) {
            if (document.activeElement === firstEl) {
                e.preventDefault();
                lastEl.focus();
            }
        } else {
            if (document.activeElement === lastEl) {
                e.preventDefault();
                firstEl.focus();
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
