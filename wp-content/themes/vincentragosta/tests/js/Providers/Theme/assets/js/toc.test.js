import { describe, it, expect } from 'vitest';
import { activeIndex, progress, update, targets, summary, enhance } from '../../../../../../src/Providers/Theme/assets/js/toc.js';

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

/**
 * A contents card as the templates render it.
 */
function card() {
    document.body.innerHTML = `
        <nav class="toc" aria-labelledby="t-heading" data-toc>
            <p class="toc__heading" id="t-heading">On this page</p>
            <ol>
                <li><a href="#one" data-toc-link="one">
                    Overview <span aria-hidden="true">01</span></a></li>
                <li><a href="#two" data-toc-link="two">
                    Cookies <span aria-hidden="true">02</span></a></li>
            </ol>
            <div class="toc__progress"><span data-toc-progress></span></div>
        </nav>`;
    return document.querySelector('.toc');
}

describe('summary', () => {
    it('names the first section until one is reached', () => {
        expect(summary(card())).toEqual({ count: '01 / 02', label: 'Overview' });
    });

    it('follows the current section', () => {
        const toc = card();
        toc.querySelector('[data-toc-link="two"]').setAttribute('aria-current', 'true');
        expect(summary(toc)).toEqual({ count: '02 / 02', label: 'Cookies' });
    });
});

describe('enhance', () => {
    it('adds a toggle controlling a panel, and keeps the progress bar outside it', () => {
        const toc = card();
        enhance(toc);

        const toggle = toc.querySelector('.toc__toggle');
        const panel = toc.querySelector('.toc__panel');
        expect(toggle.getAttribute('aria-expanded')).toBe('false');
        expect(toggle.getAttribute('aria-controls')).toBe(panel.id);
        expect(panel.querySelector('ol')).not.toBeNull();
        expect(panel.querySelector('.toc__progress')).toBeNull();
        expect(toc.lastElementChild.classList.contains('toc__progress')).toBe(true);
        expect(toggle.textContent).toContain('01 / 02');
        expect(toggle.textContent).toContain('Overview');
    });

    it('opens on the toggle and closes when a section is chosen or on Escape', () => {
        const toc = card();
        enhance(toc);
        const toggle = toc.querySelector('.toc__toggle');

        toggle.click();
        expect(toc.classList.contains('is-open')).toBe(true);
        expect(toggle.getAttribute('aria-expanded')).toBe('true');

        toc.querySelector('[data-toc-link="two"]').click();
        expect(toc.classList.contains('is-open')).toBe(false);

        toggle.click();
        toc.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
        expect(toc.classList.contains('is-open')).toBe(false);
    });

    it('enhances a card only once', () => {
        const toc = card();
        enhance(toc);
        enhance(toc);
        expect(toc.querySelectorAll('.toc__toggle')).toHaveLength(1);
    });
});
