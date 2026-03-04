(function () {
    const SELECTORS = [
        '.site-content > .wp-block-cover[id]',
        '.site-content > .wp-block-columns[id]',
        '.site-content > .wp-block-group[id]',
        '.project-detail__section[id]',
    ].join(',');

    const MAX_VISIBLE = 5;

    const sections = document.querySelectorAll(SELECTORS);
    if (!sections.length) return;

    /**
     * Convert an HTML anchor ID to a readable label.
     * "contact-form" → "Contact Form"
     */
    function formatLabel(id) {
        return id
            .replace(/[-_]/g, ' ')
            .replace(/\b\w/g, (c) => c.toUpperCase());
    }

    // Build the nav element
    const nav = document.createElement('nav');
    nav.className = 'section-nav';
    nav.setAttribute('aria-label', 'Page sections');

    const list = document.createElement('div');
    list.className = 'section-nav__list';

    const pills = [];

    sections.forEach((section) => {
        const link = document.createElement('a');
        link.href = `#${section.id}`;
        link.className = 'section-nav__pill';
        link.textContent = formatLabel(section.id);
        pills.push({ link, section });
    });

    // Split into visible and overflow
    const visiblePills = pills.slice(0, MAX_VISIBLE);
    const overflowPills = pills.slice(MAX_VISIBLE);
    const overflowSectionIds = new Set(overflowPills.map(({ section }) => section.id));

    visiblePills.forEach(({ link }) => list.appendChild(link));

    let overflowToggle = null;
    let overflowMenu = null;
    let overflowItems = [];

    if (overflowPills.length) {
        const overflowWrapper = document.createElement('div');
        overflowWrapper.className = 'section-nav__overflow';

        overflowToggle = document.createElement('button');
        overflowToggle.type = 'button';
        overflowToggle.className = 'section-nav__pill section-nav__overflow-toggle';
        overflowToggle.setAttribute('aria-expanded', 'false');
        overflowToggle.setAttribute('aria-controls', 'section-nav-overflow-menu');
        overflowToggle.setAttribute('aria-label', 'More sections');
        overflowToggle.textContent = '\u2026';

        overflowMenu = document.createElement('div');
        overflowMenu.className = 'section-nav__overflow-menu';
        overflowMenu.id = 'section-nav-overflow-menu';
        overflowMenu.setAttribute('role', 'list');

        overflowPills.forEach(({ link }) => {
            const item = document.createElement('a');
            item.href = link.href;
            item.className = 'section-nav__overflow-item';
            item.setAttribute('role', 'listitem');
            item.textContent = link.textContent;
            overflowItems.push(item);
            overflowMenu.appendChild(item);
        });

        overflowWrapper.appendChild(overflowToggle);
        overflowWrapper.appendChild(overflowMenu);
        list.appendChild(overflowWrapper);

        // Toggle click
        overflowToggle.addEventListener('click', () => {
            const expanded = overflowToggle.getAttribute('aria-expanded') === 'true';
            overflowToggle.setAttribute('aria-expanded', String(!expanded));
            if (!expanded && overflowItems.length) {
                overflowItems[0].focus();
            }
        });

        // Outside click
        document.addEventListener('click', (e) => {
            if (!overflowWrapper.contains(e.target)) {
                overflowToggle.setAttribute('aria-expanded', 'false');
            }
        });

        // Item click — close menu
        overflowItems.forEach((item) => {
            item.addEventListener('click', () => {
                overflowToggle.setAttribute('aria-expanded', 'false');
            });
        });

        // Keyboard navigation
        overflowWrapper.addEventListener('keydown', (e) => {
            const isExpanded = overflowToggle.getAttribute('aria-expanded') === 'true';

            switch (e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    if (!isExpanded) {
                        overflowToggle.setAttribute('aria-expanded', 'true');
                        overflowItems[0]?.focus();
                    } else {
                        const currentDown = overflowItems.indexOf(document.activeElement);
                        const nextIndex = (currentDown + 1) % overflowItems.length;
                        overflowItems[nextIndex].focus();
                    }
                    break;

                case 'ArrowUp':
                    e.preventDefault();
                    if (isExpanded) {
                        const currentUp = overflowItems.indexOf(document.activeElement);
                        const prevIndex = (currentUp - 1 + overflowItems.length) % overflowItems.length;
                        overflowItems[prevIndex].focus();
                    }
                    break;

                case 'Escape':
                    if (isExpanded) {
                        overflowToggle.setAttribute('aria-expanded', 'false');
                        overflowToggle.focus();
                    }
                    break;

                case 'Tab':
                    if (isExpanded) {
                        overflowToggle.setAttribute('aria-expanded', 'false');
                    }
                    break;
            }
        });
    }

    nav.appendChild(list);
    nav.classList.add('is-hidden');
    document.body.appendChild(nav);

    // Show/hide nav based on scroll position — hidden at top, visible when scrolled
    let footerVisible = false;

    function updateVisibility() {
        const atTop = window.scrollY === 0;
        nav.classList.toggle('is-hidden', atTop || footerVisible);
    }

    window.addEventListener('scroll', updateVisibility);
    updateVisibility();

    // Track active section via IntersectionObserver
    const visibilityMap = new Map();

    function updateActive() {
        let maxRatio = 0;
        let activeSection = null;

        visibilityMap.forEach((ratio, section) => {
            if (ratio > maxRatio) {
                maxRatio = ratio;
                activeSection = section;
            }
        });

        pills.forEach(({ link, section }) => {
            const isActive = section === activeSection && maxRatio > 0;
            link.classList.toggle('is-active', isActive);
            if (isActive) {
                link.setAttribute('aria-current', 'true');
            } else {
                link.removeAttribute('aria-current');
            }
        });

        // Propagate active state to overflow
        if (overflowToggle) {
            const overflowIsActive = activeSection && overflowSectionIds.has(activeSection.id) && maxRatio > 0;
            overflowToggle.classList.toggle('is-active', overflowIsActive);

            overflowItems.forEach((item, i) => {
                const itemSection = overflowPills[i].section;
                const itemIsActive = itemSection === activeSection && maxRatio > 0;
                item.classList.toggle('is-active', itemIsActive);
            });
        }
    }

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                visibilityMap.set(entry.target, entry.intersectionRatio);
            });
            updateActive();
        },
        { threshold: [0, 0.1, 0.25, 0.5, 0.75, 1] }
    );

    sections.forEach((section) => observer.observe(section));

    // Hide nav when footer enters viewport to prevent overlap
    const footer = document.querySelector('.footer');
    if (footer) {
        const footerObserver = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    footerVisible = entry.isIntersecting;
                    updateVisibility();
                });
            },
            { threshold: 0 }
        );
        footerObserver.observe(footer);
    }
})();
