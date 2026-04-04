/**
 * Modal — generic promise-based modal utility.
 *
 * Shared across blocks/providers via relative import (esbuild inlines it).
 * Base styles live in ix/common/_modal.scss.
 */

/**
 * Trap Tab/Shift+Tab focus within a container.
 * Attach to the container's `keydown` event.
 *
 * @param {HTMLElement} container
 * @param {KeyboardEvent} event
 */
export function trapFocus(container, event) {
    if (event.key !== 'Tab') return;

    const focusable = container.querySelectorAll(
        'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
    );
    if (!focusable.length) return;

    const first = focusable[0];
    const last = focusable[focusable.length - 1];

    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
}

/**
 * Create and display a modal dialog. Returns a promise that resolves to the
 * clicked button's `value`, or `null` on Escape.
 *
 * @param {Object} options
 * @param {string} options.title - Modal heading text
 * @param {string} [options.message] - Plain-text body (mutually exclusive with html)
 * @param {string} [options.html] - Raw HTML body (mutually exclusive with message)
 * @param {Array<{label: string, value: string, primary?: boolean}>} options.buttons
 * @param {string} [options.ariaLabel] - Accessible label (defaults to title)
 * @param {string} [options.className] - Extra class on the overlay
 * @param {boolean} [options.dismissOnBackdrop=false] - Resolve null on backdrop click
 * @param {*} [options.backdropDismissValue=null] - Value returned on backdrop dismiss
 * @returns {Promise<string|null>}
 */
export function createModal(options) {
    const {
        title,
        message,
        html,
        buttons = [],
        ariaLabel,
        className,
        dismissOnBackdrop = false,
        backdropDismissValue = null,
    } = options;

    return new Promise((resolve) => {
        const overlay = document.createElement('div');
        overlay.className = `modal${className ? ` ${className}` : ''}`;
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-label', ariaLabel || title);

        // Build body content
        let bodyHtml = '';
        if (message) {
            bodyHtml = `<p class="modal__message">${message}</p>`;
        } else if (html) {
            bodyHtml = `<div class="modal__body">${html}</div>`;
        }

        // Build buttons
        const buttonsHtml = buttons
            .map((btn) => {
                const cls = btn.primary
                    ? 'modal__button modal__button--primary'
                    : 'modal__button modal__button--secondary';
                const attrs = btn.primary ? ' data-modal-primary' : '';
                return `<button class="${cls}" data-modal-value="${btn.value}"${attrs}>${btn.label}</button>`;
            })
            .join('');

        overlay.innerHTML = `
            <div class="modal__panel">
                ${title ? `<h2 class="modal__title">${title}</h2>` : ''}
                ${bodyHtml}
                <div class="modal__actions">${buttonsHtml}</div>
            </div>
        `;

        // Lock scroll
        document.documentElement.classList.add('modal-open');
        document.body.appendChild(overlay);

        // Focus primary button, or first button
        const primaryBtn = overlay.querySelector('[data-modal-primary]');
        const firstBtn = overlay.querySelector('.modal__button');
        (primaryBtn || firstBtn)?.focus();

        function cleanup(value) {
            overlay.remove();
            document.documentElement.classList.remove('modal-open');
            resolve(value);
        }

        // Button clicks
        overlay.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-modal-value]');
            if (btn) {
                cleanup(btn.dataset.modalValue);
                return;
            }

            // Backdrop click — only if the click is directly on the overlay (not the panel)
            if (dismissOnBackdrop && e.target === overlay) {
                cleanup(backdropDismissValue);
            }
        });

        // Keyboard
        overlay.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                cleanup(null);
                return;
            }
            trapFocus(overlay, e);
        });
    });
}
