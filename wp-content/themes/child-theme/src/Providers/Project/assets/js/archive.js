(function () {
    const archive = document.querySelector('.projects-archive');
    if (!archive) return;

    archive.classList.add('has-scroll-reveal');

    const cards = archive.querySelectorAll('.project-card');

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        },
        { rootMargin: '50px' }
    );

    cards.forEach((card) => observer.observe(card));
})();
