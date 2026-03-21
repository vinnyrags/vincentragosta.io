/**
 * Testimonials carousel
 * Initializes Splide on testimonial blocks for accessible carousel behavior.
 */

import Splide from '@splidejs/splide';

export const SPLIDE_CONFIG = {
    type: 'loop',
    perPage: 1,
    pagination: false,
    arrows: true,
    autoplay: false,
    speed: 400,
    gap: '2rem',
    pauseOnHover: true,
    i18n: {
        prev: 'Previous testimonial',
        next: 'Next testimonial',
        slideX: 'Go to testimonial %s',
        pageX: 'Go to page %s',
    },
};

/**
 * Initialize testimonial carousels on the page.
 * Skips initialization if fewer than 2 slides.
 */
export function initTestimonials() {
    const carousels = document.querySelectorAll('.testimonials.splide:not(.is-initialized)');

    carousels.forEach((carousel) => {
        const slides = carousel.querySelectorAll('.splide__slide');

        if (slides.length < 2) return;

        new Splide(carousel, SPLIDE_CONFIG).mount();
    });
}

// WordPress loads viewScripts with defer strategy. Deferred scripts run
// after parsing (readyState = 'interactive') but DOMContentLoaded may have
// already fired. Always try immediately since the DOM is ready by the time
// any deferred script executes.
initTestimonials();
