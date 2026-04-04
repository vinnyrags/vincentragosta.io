import { describe, it, expect, beforeEach, vi } from 'vitest';
import { createModal, trapFocus } from '../../../../../../src/Providers/Theme/assets/js/modal.js';

describe('trapFocus', () => {
    function createContainer() {
        const container = document.createElement('div');
        const btn1 = document.createElement('button');
        btn1.textContent = 'First';
        const btn2 = document.createElement('button');
        btn2.textContent = 'Last';
        container.appendChild(btn1);
        container.appendChild(btn2);
        document.body.appendChild(container);
        return { container, btn1, btn2 };
    }

    it('wraps forward Tab from last to first element', () => {
        const { container, btn1, btn2 } = createContainer();
        btn2.focus();

        const event = new KeyboardEvent('keydown', { key: 'Tab', bubbles: true });
        Object.defineProperty(event, 'preventDefault', { value: vi.fn() });
        trapFocus(container, event);

        expect(event.preventDefault).toHaveBeenCalled();
        expect(document.activeElement).toBe(btn1);
    });

    it('wraps backward Shift+Tab from first to last element', () => {
        const { container, btn1, btn2 } = createContainer();
        btn1.focus();

        const event = new KeyboardEvent('keydown', { key: 'Tab', shiftKey: true, bubbles: true });
        Object.defineProperty(event, 'preventDefault', { value: vi.fn() });
        trapFocus(container, event);

        expect(event.preventDefault).toHaveBeenCalled();
        expect(document.activeElement).toBe(btn2);
    });

    it('does nothing for non-Tab keys', () => {
        const { container, btn1 } = createContainer();
        btn1.focus();

        const event = new KeyboardEvent('keydown', { key: 'Enter', bubbles: true });
        Object.defineProperty(event, 'preventDefault', { value: vi.fn() });
        trapFocus(container, event);

        expect(event.preventDefault).not.toHaveBeenCalled();
    });
});

describe('createModal', () => {
    beforeEach(() => {
        document.documentElement.classList.remove('modal-open');
    });

    it('renders title and message', async () => {
        const promise = createModal({
            title: 'Test Title',
            message: 'Test message',
            buttons: [{ label: 'OK', value: 'ok', primary: true }],
        });

        const overlay = document.querySelector('.modal');
        expect(overlay).not.toBeNull();
        expect(overlay.querySelector('.modal__title').textContent).toBe('Test Title');
        expect(overlay.querySelector('.modal__message').textContent).toBe('Test message');

        // Resolve by clicking
        overlay.querySelector('[data-modal-value="ok"]').click();
        expect(await promise).toBe('ok');
    });

    it('renders html option instead of message', async () => {
        const promise = createModal({
            title: 'HTML Modal',
            html: '<strong>Bold text</strong>',
            buttons: [{ label: 'OK', value: 'ok', primary: true }],
        });

        const body = document.querySelector('.modal__body');
        expect(body).not.toBeNull();
        expect(body.innerHTML).toContain('<strong>Bold text</strong>');
        expect(document.querySelector('.modal__message')).toBeNull();

        document.querySelector('[data-modal-value="ok"]').click();
        await promise;
    });

    it('resolves with clicked button value', async () => {
        const promise = createModal({
            title: 'Choose',
            buttons: [
                { label: 'Yes', value: 'yes', primary: true },
                { label: 'No', value: 'no' },
            ],
        });

        document.querySelector('[data-modal-value="no"]').click();
        expect(await promise).toBe('no');
    });

    it('focuses primary button on open', async () => {
        const promise = createModal({
            title: 'Focus Test',
            buttons: [
                { label: 'Secondary', value: 'sec' },
                { label: 'Primary', value: 'pri', primary: true },
            ],
        });

        const primary = document.querySelector('[data-modal-primary]');
        expect(document.activeElement).toBe(primary);

        primary.click();
        await promise;
    });

    it('focuses first button when no primary exists', async () => {
        const promise = createModal({
            title: 'No Primary',
            buttons: [
                { label: 'First', value: 'first' },
                { label: 'Second', value: 'second' },
            ],
        });

        const first = document.querySelector('.modal__button');
        expect(document.activeElement).toBe(first);

        first.click();
        await promise;
    });

    it('resolves null on Escape key', async () => {
        const promise = createModal({
            title: 'Escape Test',
            buttons: [{ label: 'OK', value: 'ok', primary: true }],
        });

        const overlay = document.querySelector('.modal');
        overlay.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));

        expect(await promise).toBeNull();
    });

    it('does not dismiss on backdrop click by default', async () => {
        const promise = createModal({
            title: 'Backdrop Test',
            buttons: [{ label: 'OK', value: 'ok', primary: true }],
        });

        const overlay = document.querySelector('.modal');
        // Click the overlay itself (backdrop)
        overlay.click();

        // Modal should still be in DOM
        expect(document.querySelector('.modal')).not.toBeNull();

        // Clean up
        overlay.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
        await promise;
    });

    it('dismisses on backdrop click when dismissOnBackdrop is true', async () => {
        const promise = createModal({
            title: 'Backdrop Dismiss',
            buttons: [{ label: 'OK', value: 'ok', primary: true }],
            dismissOnBackdrop: true,
        });

        const overlay = document.querySelector('.modal');
        overlay.click();

        expect(await promise).toBeNull();
    });

    it('returns custom backdropDismissValue', async () => {
        const promise = createModal({
            title: 'Custom Backdrop Value',
            buttons: [{ label: 'OK', value: 'ok', primary: true }],
            dismissOnBackdrop: true,
            backdropDismissValue: 'dismissed',
        });

        document.querySelector('.modal').click();
        expect(await promise).toBe('dismissed');
    });

    it('removes overlay from DOM after resolution', async () => {
        const promise = createModal({
            title: 'Cleanup',
            buttons: [{ label: 'OK', value: 'ok', primary: true }],
        });

        expect(document.querySelector('.modal')).not.toBeNull();
        document.querySelector('[data-modal-value="ok"]').click();
        await promise;

        expect(document.querySelector('.modal')).toBeNull();
    });

    it('adds and removes scroll lock class', async () => {
        const promise = createModal({
            title: 'Scroll Lock',
            buttons: [{ label: 'OK', value: 'ok', primary: true }],
        });

        expect(document.documentElement.classList.contains('modal-open')).toBe(true);

        document.querySelector('[data-modal-value="ok"]').click();
        await promise;

        expect(document.documentElement.classList.contains('modal-open')).toBe(false);
    });

    it('applies custom className', async () => {
        const promise = createModal({
            title: 'Custom Class',
            buttons: [{ label: 'OK', value: 'ok', primary: true }],
            className: 'modal--age-gate',
        });

        const overlay = document.querySelector('.modal');
        expect(overlay.classList.contains('modal--age-gate')).toBe(true);

        document.querySelector('[data-modal-value="ok"]').click();
        await promise;
    });

    it('uses custom ariaLabel', async () => {
        const promise = createModal({
            title: 'Title',
            buttons: [{ label: 'OK', value: 'ok', primary: true }],
            ariaLabel: 'Custom label',
        });

        const overlay = document.querySelector('.modal');
        expect(overlay.getAttribute('aria-label')).toBe('Custom label');

        document.querySelector('[data-modal-value="ok"]').click();
        await promise;
    });

    it('defaults ariaLabel to title', async () => {
        const promise = createModal({
            title: 'My Title',
            buttons: [{ label: 'OK', value: 'ok', primary: true }],
        });

        expect(document.querySelector('.modal').getAttribute('aria-label')).toBe('My Title');

        document.querySelector('[data-modal-value="ok"]').click();
        await promise;
    });

    it('sets role=dialog and aria-modal=true', async () => {
        const promise = createModal({
            title: 'ARIA',
            buttons: [{ label: 'OK', value: 'ok', primary: true }],
        });

        const overlay = document.querySelector('.modal');
        expect(overlay.getAttribute('role')).toBe('dialog');
        expect(overlay.getAttribute('aria-modal')).toBe('true');

        document.querySelector('[data-modal-value="ok"]').click();
        await promise;
    });

    it('traps focus within the modal', async () => {
        const promise = createModal({
            title: 'Focus Trap',
            buttons: [
                { label: 'First', value: 'first', primary: true },
                { label: 'Last', value: 'last' },
            ],
        });

        const overlay = document.querySelector('.modal');
        const buttons = overlay.querySelectorAll('.modal__button');
        const first = buttons[0];
        const last = buttons[buttons.length - 1];

        // Focus last button, Tab should wrap to first
        last.focus();
        const tabEvent = new KeyboardEvent('keydown', { key: 'Tab', bubbles: true });
        Object.defineProperty(tabEvent, 'preventDefault', { value: vi.fn() });
        overlay.dispatchEvent(tabEvent);

        expect(tabEvent.preventDefault).toHaveBeenCalled();
        expect(document.activeElement).toBe(first);

        // Focus first button, Shift+Tab should wrap to last
        first.focus();
        const shiftTabEvent = new KeyboardEvent('keydown', { key: 'Tab', shiftKey: true, bubbles: true });
        Object.defineProperty(shiftTabEvent, 'preventDefault', { value: vi.fn() });
        overlay.dispatchEvent(shiftTabEvent);

        expect(shiftTabEvent.preventDefault).toHaveBeenCalled();
        expect(document.activeElement).toBe(last);

        // Clean up
        overlay.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
        await promise;
    });
});
