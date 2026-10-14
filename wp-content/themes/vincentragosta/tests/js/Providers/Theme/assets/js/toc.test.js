import { describe, it, expect } from 'vitest';
import { activeIndex, progress, update, targets } from '../../../../../../src/Providers/Theme/assets/js/toc.js';

describe('activeIndex', () => {
    it('is -1 before the first section reaches the line', () => {
        expect(activeIndex([400, 900, 1400], 300)).toBe(-1);
    });

    it('is the last section whose top has passed the line', () => {
        expect(activeIndex([-600, -100, 500], 300)).toBe(1);
        expect(activeIndex([-1600, -1100, -200], 300)).toBe(2);
    });
});

describe('progress', () => {
    it('clamps to 0–1', () => {
        expect(progress(500, 1000, 300)).toBe(0);
        expect(progress(-2000, 1000, 300)).toBe(1);
    });

    it('is the fraction of the region past the line', () => {
        expect(progress(-200, 1000, 300)).toBe(0.5);
    });

    it('is 0 for an empty region', () => {
        expect(progress(0, 0, 300)).toBe(0);
    });
});

describe('update', () => {
    it('marks only the active link and sets the progress bar', () => {
        document.body.innerHTML = `
            <nav data-toc>
                <a data-toc-link="background"></a><a data-toc-link="results"></a>
                <span data-toc-progress></span>
            </nav>
            <div id="region">
                <section id="background" data-toc-section></section>
                <section id="results" data-toc-section></section>
            </div>`;
        const toc = document.querySelector('[data-toc]');
        const sections = [...document.querySelectorAll('[data-toc-section]')];
        const rect = (top, height = 0) => () => ({ top, height, bottom: top + height });
        sections[0].getBoundingClientRect = rect(-400);
        sections[1].getBoundingClientRect = rect(600);
        document.getElementById('region').getBoundingClientRect = rect(-400, 1400);

        update(toc, sections, 300);

        expect(toc.querySelector('[data-toc-link="background"]').getAttribute('aria-current')).toBe('true');
        expect(toc.querySelector('[data-toc-link="results"]').hasAttribute('aria-current')).toBe(false);
        expect(toc.querySelector('[data-toc-progress]').style.transform).toBe('scaleX(0.5)');
    });
});

describe('targets', () => {
    it('resolves the elements the links point at, in link order, skipping missing ids', () => {
        document.body.innerHTML = `
            <nav data-toc>
                <a data-toc-link="b"></a><a data-toc-link="missing"></a><a data-toc-link="a"></a>
            </nav>
            <h2 id="a"></h2><h2 id="b"></h2>`;
        const ids = targets(document.querySelector('[data-toc]')).map((el) => el.id);
        expect(ids).toEqual(['b', 'a']);
    });
});
