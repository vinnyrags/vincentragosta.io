import { describe, it, expect } from 'vitest';
import { stepTarget, canScroll, enhance } from '../../../../../../src/Providers/Theme/assets/js/testimonials.js';

// Card starts: a wider first card (556px) then 400px cards, 16px gaps.
const STARTS = [0, 572, 988, 1404, 1820];

describe('stepTarget', () => {
    it('steps forward to the next card start', () => {
        expect(stepTarget(STARTS, 0, 1)).toBe(572);
        expect(stepTarget(STARTS, 572, 1)).toBe(988);
    });

    it('steps back to the previous card start', () => {
        expect(stepTarget(STARTS, 988, -1)).toBe(572);
        expect(stepTarget(STARTS, 572, -1)).toBe(0);
    });

    it('steps from between cards (snap rounding) without stalling', () => {
        expect(stepTarget(STARTS, 700, 1)).toBe(988);
        expect(stepTarget(STARTS, 700, -1)).toBe(572);
    });

    it('is null past either end', () => {
        expect(stepTarget(STARTS, 1820, 1)).toBeNull();
        expect(stepTarget(STARTS, 0, -1)).toBeNull();
    });
});

describe('canScroll', () => {
    it('reports both directions', () => {
        expect(canScroll({ scrollLeft: 0, scrollWidth: 2220, clientWidth: 1392 })).toEqual({ prev: false, next: true });
        expect(canScroll({ scrollLeft: 828, scrollWidth: 2220, clientWidth: 1392 })).toEqual({ prev: true, next: false });
    });
});

describe('enhance', () => {
    const row = (n) => {
        document.body.innerHTML = `<div class="wp-block-group is-style-testimonials">${
            Array.from({ length: n }, () => '<blockquote class="wp-block-quote"><p>q</p></blockquote>').join('')
        }</div>`;
        return document.querySelector('.is-style-testimonials');
    };

    it('adds labelled controls and carousel semantics once', () => {
        const el = row(5);

        enhance(el);
        enhance(el);

        expect(document.querySelectorAll('.testimonials-controls')).toHaveLength(1);
        expect(el.getAttribute('aria-roledescription')).toBe('carousel');
        expect(document.querySelector('.testimonials-controls__prev').getAttribute('aria-label')).toBe('Previous testimonial');
        expect(document.querySelector('.testimonials-controls__next').getAttribute('aria-label')).toBe('Next testimonial');
    });

    it('leaves a single quote alone', () => {
        enhance(row(1));

        expect(document.querySelector('.testimonials-controls')).toBeNull();
    });
});
