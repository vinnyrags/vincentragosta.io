(function () {
    const SELECTORS = [
        '.site-main > .wp-block-cover[id]',
        '.site-main > .wp-block-columns[id]',
        '.site-main > .wp-block-group[id]',
        '.project-detail__section[id]',
    ].join(',');

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
        list.appendChild(link);
    });

    nav.appendChild(list);
    document.body.appendChild(nav);

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
})();
