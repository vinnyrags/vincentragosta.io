import { describe, it, expect, vi, beforeEach } from 'vitest';

// Mock Splide — factory must not reference outer variables (hoisted)
vi.mock('@splidejs/splide', () => {
    const mount = vi.fn();
    const Splide = vi.fn(() => ({ mount }));
    return { default: Splide, __mount: mount };
});

import { default as Splide, __mount as mountMock } from '@splidejs/splide';
import { SPLIDE_CONFIG, initTestimonials } from '../../../../../../src/Providers/Theme/blocks/testimonials/view.js';

/**
 * Build a testimonials carousel with N slides
 */
function createTestimonials(count = 3) {
    const carousel = document.createElement('div');
    carousel.classList.add('testimonials', 'splide');
    carousel.setAttribute('aria-label', 'Testimonials');

    const track = document.createElement('div');
    track.classList.add('splide__track');

    const list = document.createElement('ul');
    list.classList.add('splide__list');

    for (let i = 0; i < count; i++) {
        const slide = document.createElement('li');
        slide.classList.add('splide__slide');

        const quote = document.createElement('blockquote');
        quote.classList.add('wp-block-quote');
        quote.innerHTML = `<p>Testimonial ${i + 1}</p><cite>Author ${i + 1}</cite>`;

        slide.appendChild(quote);
        list.appendChild(slide);
    }

    track.appendChild(list);
    carousel.appendChild(track);
    document.body.appendChild(carousel);
    return carousel;
}

beforeEach(() => {
    Splide.mockClear();
    mountMock.mockClear();
});

describe('SPLIDE_CONFIG', () => {
    it('sets type to loop', () => {
        expect(SPLIDE_CONFIG.type).toBe('loop');
    });

    it('shows one slide at a time', () => {
        expect(SPLIDE_CONFIG.perPage).toBe(1);
    });

    it('disables pagination and enables arrows', () => {
        expect(SPLIDE_CONFIG.pagination).toBe(false);
        expect(SPLIDE_CONFIG.arrows).toBe(true);
    });

    it('disables autoplay', () => {
        expect(SPLIDE_CONFIG.autoplay).toBe(false);
    });

    it('has accessible i18n labels', () => {
        expect(SPLIDE_CONFIG.i18n.prev).toBe('Previous testimonial');
        expect(SPLIDE_CONFIG.i18n.next).toBe('Next testimonial');
        expect(SPLIDE_CONFIG.i18n.slideX).toBe('Go to testimonial %s');
        expect(SPLIDE_CONFIG.i18n.pageX).toBe('Go to page %s');
    });
});

describe('initTestimonials', () => {
    it('initializes Splide on carousel with 2+ slides', () => {
        const carousel = createTestimonials(3);

        initTestimonials();

        expect(Splide).toHaveBeenCalledWith(carousel, SPLIDE_CONFIG);
        expect(mountMock).toHaveBeenCalled();
    });

    it('skips initialization for fewer than 2 slides', () => {
        createTestimonials(1);

        initTestimonials();

        expect(Splide).not.toHaveBeenCalled();
        expect(mountMock).not.toHaveBeenCalled();
    });

    it('initializes multiple carousels independently', () => {
        createTestimonials(3);
        createTestimonials(2);

        initTestimonials();

        expect(Splide).toHaveBeenCalledTimes(2);
        expect(mountMock).toHaveBeenCalledTimes(2);
    });

    it('initializes carousel with exactly 2 slides', () => {
        createTestimonials(2);

        initTestimonials();

        expect(Splide).toHaveBeenCalledTimes(1);
        expect(mountMock).toHaveBeenCalledTimes(1);
    });
});
