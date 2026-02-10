/**
 * Reusable dropdown component
 * Targets all .dropdown elements, manages ARIA state, dispatches change events.
 */

(function () {
    const dropdowns = document.querySelectorAll('.dropdown');
    if (!dropdowns.length) return;

    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    /**
     * Close a dropdown by setting aria-expanded to false
     * @param {HTMLElement} toggle
     */
    function closeDropdown(toggle) {
        toggle.setAttribute('aria-expanded', 'false');
    }

    /**
     * Open a dropdown by setting aria-expanded to true
     * @param {HTMLElement} toggle
     */
    function openDropdown(toggle) {
        toggle.setAttribute('aria-expanded', 'true');
    }

    /**
     * Select an item within a dropdown
     * @param {HTMLElement} dropdown - The .dropdown container
     * @param {HTMLElement} item - The selected .dropdown__item
     */
    function selectItem(dropdown, item) {
        const toggle = dropdown.querySelector('.dropdown__toggle');
        const label = dropdown.querySelector('.dropdown__label');
        const menu = dropdown.querySelector('.dropdown__menu');
        const items = menu.querySelectorAll('.dropdown__item');

        // Update aria-selected on all items
        items.forEach((i) => {
            i.setAttribute('aria-selected', i === item ? 'true' : 'false');
        });

        // Update label text
        label.textContent = item.textContent.trim();

        // Track focused item
        menu.setAttribute('aria-activedescendant', item.id);

        // Close menu and return focus
        closeDropdown(toggle);
        toggle.focus();

        // Dispatch change event on the dropdown container
        dropdown.dispatchEvent(
            new CustomEvent('change', {
                bubbles: true,
                detail: {
                    value: item.dataset.value,
                    label: item.textContent.trim(),
                },
            })
        );
    }

    /**
     * Get all items in a dropdown menu
     * @param {HTMLElement} dropdown
     * @returns {HTMLElement[]}
     */
    function getItems(dropdown) {
        return Array.from(dropdown.querySelectorAll('.dropdown__item'));
    }

    /**
     * Move focus to the next or previous item
     * @param {HTMLElement} dropdown
     * @param {number} direction - 1 for next, -1 for previous
     */
    function moveFocus(dropdown, direction) {
        const items = getItems(dropdown);
        const menu = dropdown.querySelector('.dropdown__menu');
        const currentId = menu.getAttribute('aria-activedescendant');
        let currentIndex = items.findIndex((i) => i.id === currentId);

        if (currentIndex === -1) {
            currentIndex = direction === 1 ? -1 : items.length;
        }

        let nextIndex = currentIndex + direction;
        if (nextIndex < 0) nextIndex = items.length - 1;
        if (nextIndex >= items.length) nextIndex = 0;

        const nextItem = items[nextIndex];
        nextItem.focus();
        menu.setAttribute('aria-activedescendant', nextItem.id);
    }

    dropdowns.forEach((dropdown) => {
        const toggle = dropdown.querySelector('.dropdown__toggle');
        const menu = dropdown.querySelector('.dropdown__menu');
        if (!toggle || !menu) return;

        // Toggle open/close on click
        toggle.addEventListener('click', () => {
            const isExpanded = toggle.getAttribute('aria-expanded') === 'true';

            if (isExpanded) {
                closeDropdown(toggle);
            } else {
                openDropdown(toggle);

                // Focus the selected item or first item
                const selected = menu.querySelector('[aria-selected="true"]');
                const target = selected || menu.querySelector('.dropdown__item');
                if (target) {
                    target.focus();
                    menu.setAttribute('aria-activedescendant', target.id);
                }
            }
        });

        // Item click
        menu.addEventListener('click', (e) => {
            const item = e.target.closest('.dropdown__item');
            if (item) {
                selectItem(dropdown, item);
            }
        });

        // Keyboard navigation
        dropdown.addEventListener('keydown', (e) => {
            const isExpanded = toggle.getAttribute('aria-expanded') === 'true';

            switch (e.key) {
                case 'Escape':
                    if (isExpanded) {
                        e.preventDefault();
                        closeDropdown(toggle);
                        toggle.focus();
                    }
                    break;

                case 'ArrowDown':
                    e.preventDefault();
                    if (!isExpanded) {
                        openDropdown(toggle);
                        const selected = menu.querySelector('[aria-selected="true"]');
                        const target = selected || menu.querySelector('.dropdown__item');
                        if (target) {
                            target.focus();
                            menu.setAttribute('aria-activedescendant', target.id);
                        }
                    } else {
                        moveFocus(dropdown, 1);
                    }
                    break;

                case 'ArrowUp':
                    e.preventDefault();
                    if (isExpanded) {
                        moveFocus(dropdown, -1);
                    }
                    break;

                case 'Enter':
                case ' ':
                    if (isExpanded && document.activeElement.closest('.dropdown__item')) {
                        e.preventDefault();
                        selectItem(dropdown, document.activeElement);
                    }
                    break;

                case 'Tab':
                    if (isExpanded) {
                        closeDropdown(toggle);
                    }
                    break;
            }
        });
    });

    // Close all dropdowns when clicking outside
    document.addEventListener('click', (e) => {
        dropdowns.forEach((dropdown) => {
            if (!dropdown.contains(e.target)) {
                const toggle = dropdown.querySelector('.dropdown__toggle');
                if (toggle && toggle.getAttribute('aria-expanded') === 'true') {
                    closeDropdown(toggle);
                }
            }
        });
    });
})();
