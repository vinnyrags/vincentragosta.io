(function () {
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (prefersReducedMotion) return;

    const STAGGER_DELAY = 0.1; // seconds between sibling reveals

    // Selectors for elements that should animate in on scroll
    const selectors = [
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        '.site-main p',
        '.site-main ul', '.site-main ol', '.site-main dl',
        '.site-main .wp-block-image',
        '.site-main .wp-block-button',
        '.site-main .wp-block-buttons',
        '.footer__contact-heading',
        '.footer__contact-body',
        '.footer__bar',
    ].join(',');

    const elements = document.querySelectorAll(selectors);
    if (!elements.length) return;

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        },
        { rootMargin: '0px 0px -50px 0px' }
    );

    // Track stagger index per parent container
    const parentCounters = new Map();

    elements.forEach((el) => {
        const rect = el.getBoundingClientRect();
        const inViewport = rect.top < window.innerHeight && rect.bottom > 0;

        el.classList.add('fade-up');

        // Calculate stagger delay based on sibling index within same parent
        const parent = el.parentElement;
        if (parent) {
            const index = parentCounters.get(parent) || 0;
            if (index > 0) {
                el.style.transitionDelay = `${index * STAGGER_DELAY}s`;
            }
            parentCounters.set(parent, index + 1);
        }

        if (inViewport) {
            el.classList.add('is-visible');
        } else {
            observer.observe(el);
        }
    });

    // The -50px bottom rootMargin means elements at the very end of the
    // document can never intersect — the user can't scroll them 50px above
    // the viewport bottom. Reveal any remaining elements when we hit the
    // bottom of the page.
    const revealRemaining = () => {
        const atBottom =
            window.innerHeight + window.scrollY >=
            document.documentElement.scrollHeight - 50;
        if (atBottom) {
            document.querySelectorAll('.fade-up:not(.is-visible)').forEach((el) => {
                el.classList.add('is-visible');
                observer.unobserve(el);
            });
            window.removeEventListener('scroll', revealRemaining);
        }
    };
    window.addEventListener('scroll', revealRemaining, { passive: true });
})();
