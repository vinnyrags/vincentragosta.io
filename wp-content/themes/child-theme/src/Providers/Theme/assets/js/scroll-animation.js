(function () {
    const headings = document.querySelectorAll('h1, h2, h3, h4, h5, h6');

    if (!headings.length) return;

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

    headings.forEach((heading) => {
        const rect = heading.getBoundingClientRect();
        const inViewport = rect.top < window.innerHeight && rect.bottom > 0;

        heading.classList.add('fade-up');

        if (inViewport) {
            heading.classList.add('is-visible');
        } else {
            observer.observe(heading);
        }
    });
})();
