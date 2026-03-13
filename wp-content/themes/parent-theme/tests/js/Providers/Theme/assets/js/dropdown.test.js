import { describe, it, expect, vi, beforeEach } from 'vitest';
import { closeDropdown, openDropdown, selectItem, getItems, moveFocus, initDropdowns } from '../../../../../../src/Providers/Theme/assets/js/dropdown.js';

/**
 * Build dropdown HTML fixture
 */
function createDropdown({ items = ['Alpha', 'Beta', 'Gamma'], selectedIndex = -1 } = {}) {
    const dropdown = document.createElement('div');
    dropdown.classList.add('dropdown');

    const toggle = document.createElement('button');
    toggle.classList.add('dropdown__toggle');
    toggle.setAttribute('aria-expanded', 'false');

    const label = document.createElement('span');
    label.classList.add('dropdown__label');
    label.textContent = 'Select';
    toggle.appendChild(label);

    const menu = document.createElement('ul');
    menu.classList.add('dropdown__menu');
    menu.setAttribute('role', 'listbox');

    items.forEach((text, i) => {
        const item = document.createElement('li');
        item.classList.add('dropdown__item');
        item.id = `item-${i}`;
        item.setAttribute('role', 'option');
        item.setAttribute('tabindex', '-1');
        item.setAttribute('aria-selected', i === selectedIndex ? 'true' : 'false');
        item.setAttribute('aria-disabled', i === selectedIndex ? 'true' : 'false');
        item.dataset.value = text.toLowerCase();
        item.textContent = text;
        menu.appendChild(item);
    });

    dropdown.appendChild(toggle);
    dropdown.appendChild(menu);
    document.body.appendChild(dropdown);

    return { dropdown, toggle, menu, label, items: menu.querySelectorAll('.dropdown__item') };
}

describe('closeDropdown', () => {
    it('sets aria-expanded to false', () => {
        const toggle = document.createElement('button');
        toggle.setAttribute('aria-expanded', 'true');

        closeDropdown(toggle);

        expect(toggle.getAttribute('aria-expanded')).toBe('false');
    });
});

describe('openDropdown', () => {
    it('sets aria-expanded to true', () => {
        const toggle = document.createElement('button');
        toggle.setAttribute('aria-expanded', 'false');

        openDropdown(toggle);

        expect(toggle.getAttribute('aria-expanded')).toBe('true');
    });
});

describe('selectItem', () => {
    it('updates ARIA attributes on all items', () => {
        const { dropdown, items } = createDropdown();
        const target = items[1]; // Beta

        selectItem(dropdown, target);

        expect(items[0].getAttribute('aria-selected')).toBe('false');
        expect(items[0].getAttribute('aria-disabled')).toBe('false');
        expect(items[1].getAttribute('aria-selected')).toBe('true');
        expect(items[1].getAttribute('aria-disabled')).toBe('true');
        expect(items[2].getAttribute('aria-selected')).toBe('false');
        expect(items[2].getAttribute('aria-disabled')).toBe('false');
    });

    it('updates label text to selected item', () => {
        const { dropdown, label, items } = createDropdown();

        selectItem(dropdown, items[2]); // Gamma

        expect(label.textContent).toBe('Gamma');
    });

    it('dispatches CustomEvent with value and label', () => {
        const { dropdown, items } = createDropdown();
        const handler = vi.fn();
        dropdown.addEventListener('change', handler);

        selectItem(dropdown, items[0]); // Alpha

        expect(handler).toHaveBeenCalledTimes(1);
        const event = handler.mock.calls[0][0];
        expect(event.detail.value).toBe('alpha');
        expect(event.detail.label).toBe('Alpha');
    });

    it('closes the dropdown after selection', () => {
        const { dropdown, toggle, items } = createDropdown();
        toggle.setAttribute('aria-expanded', 'true');

        selectItem(dropdown, items[0]);

        expect(toggle.getAttribute('aria-expanded')).toBe('false');
    });

    it('sets aria-activedescendant on the menu', () => {
        const { dropdown, menu, items } = createDropdown();

        selectItem(dropdown, items[1]);

        expect(menu.getAttribute('aria-activedescendant')).toBe('item-1');
    });

    it('skips disabled items', () => {
        const { dropdown, items } = createDropdown();
        items[1].setAttribute('aria-disabled', 'true');
        const handler = vi.fn();
        dropdown.addEventListener('change', handler);

        selectItem(dropdown, items[1]);

        expect(handler).not.toHaveBeenCalled();
    });
});

describe('getItems', () => {
    it('returns all dropdown items as an array', () => {
        const { dropdown } = createDropdown({ items: ['A', 'B', 'C', 'D'] });

        const result = getItems(dropdown);

        expect(result).toHaveLength(4);
        expect(Array.isArray(result)).toBe(true);
    });
});

describe('moveFocus', () => {
    it('moves focus forward', () => {
        const { dropdown, menu } = createDropdown();
        menu.setAttribute('aria-activedescendant', 'item-0');

        moveFocus(dropdown, 1);

        expect(menu.getAttribute('aria-activedescendant')).toBe('item-1');
    });

    it('moves focus backward', () => {
        const { dropdown, menu } = createDropdown();
        menu.setAttribute('aria-activedescendant', 'item-1');

        moveFocus(dropdown, -1);

        expect(menu.getAttribute('aria-activedescendant')).toBe('item-0');
    });

    it('wraps forward from last to first', () => {
        const { dropdown, menu } = createDropdown();
        menu.setAttribute('aria-activedescendant', 'item-2');

        moveFocus(dropdown, 1);

        expect(menu.getAttribute('aria-activedescendant')).toBe('item-0');
    });

    it('wraps backward from first to last', () => {
        const { dropdown, menu } = createDropdown();
        menu.setAttribute('aria-activedescendant', 'item-0');

        moveFocus(dropdown, -1);

        expect(menu.getAttribute('aria-activedescendant')).toBe('item-2');
    });

    it('starts at first item when no current active descendant', () => {
        const { dropdown, menu } = createDropdown();

        moveFocus(dropdown, 1);

        expect(menu.getAttribute('aria-activedescendant')).toBe('item-0');
    });
});

describe('initDropdowns', () => {
    it('opens dropdown on toggle click', () => {
        const { toggle } = createDropdown();

        initDropdowns();
        toggle.click();

        expect(toggle.getAttribute('aria-expanded')).toBe('true');
    });

    it('closes dropdown on second toggle click', () => {
        const { toggle } = createDropdown();

        initDropdowns();
        toggle.click(); // open
        toggle.click(); // close

        expect(toggle.getAttribute('aria-expanded')).toBe('false');
    });

    it('closes dropdown on Escape key', () => {
        const { dropdown, toggle } = createDropdown();

        initDropdowns();
        toggle.click(); // open
        expect(toggle.getAttribute('aria-expanded')).toBe('true');

        dropdown.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));

        expect(toggle.getAttribute('aria-expanded')).toBe('false');
    });

    it('opens and focuses first item on ArrowDown when closed', () => {
        const { dropdown, toggle, menu } = createDropdown();

        initDropdowns();
        dropdown.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true }));

        expect(toggle.getAttribute('aria-expanded')).toBe('true');
        expect(menu.getAttribute('aria-activedescendant')).toBe('item-0');
    });

    it('closes dropdown on Tab key', () => {
        const { dropdown, toggle } = createDropdown();

        initDropdowns();
        toggle.click(); // open

        dropdown.dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab', bubbles: true }));

        expect(toggle.getAttribute('aria-expanded')).toBe('false');
    });

    it('closes dropdown when clicking outside', () => {
        const { toggle } = createDropdown();
        const outside = document.createElement('div');
        document.body.appendChild(outside);

        initDropdowns();
        toggle.click(); // open
        expect(toggle.getAttribute('aria-expanded')).toBe('true');

        outside.click();

        expect(toggle.getAttribute('aria-expanded')).toBe('false');
    });

    it('selects item on click', () => {
        const { dropdown, toggle, items, label } = createDropdown();
        const handler = vi.fn();
        dropdown.addEventListener('change', handler);

        initDropdowns();
        toggle.click(); // open
        items[2].click(); // click Gamma

        expect(label.textContent).toBe('Gamma');
        expect(handler).toHaveBeenCalledTimes(1);
    });
});
