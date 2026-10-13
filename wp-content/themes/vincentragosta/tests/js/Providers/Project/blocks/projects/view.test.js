import { describe, it, expect, beforeEach } from 'vitest';
import {
    isFiltered,
    readUrlState,
    writeUrlState,
    applyFilters,
    applyRowLimit,
    updateYearGroups,
    setView,
    render,
    initArchive,
} from '../../../../../../src/Providers/Project/blocks/projects/view.js';

/**
 * Build an archive block: pills, featured section, year groups of rows,
 * a card grid with the same projects, show-more and empty state.
 * @param {Array<{year: string, items: Array<[string, string]>}>} groups - [title, categories]
 */
function createArchive(groups) {
    const item = (title, categories) =>
        `data-project-item data-title="${title.toLowerCase()}" data-category="${categories}"`;

    const rows = groups
        .map(
            (g) => `
            <section data-year-group>
                <h3>${g.year} <span data-year-count></span></h3>
                <ol>${g.items.map(([t, c]) => `<li class="project-row" ${item(t, c)}><a href="#">${t}</a></li>`).join('')}</ol>
            </section>`
        )
        .join('');

    const cards = groups
        .flatMap((g) => g.items)
        .map(([t, c]) => `<article class="project-card" ${item(t, c)}></article>`)
        .join('');

    document.body.innerHTML = `
        <div class="wp-block-ix-projects is-archive" data-view="index">
            <button data-category-filter="all" aria-pressed="true"></button>
            <button data-category-filter="broadway" aria-pressed="false"></button>
            <button data-category-filter="brand" aria-pressed="false"></button>
            <input class="projects-search__input" />
            <button data-view-toggle="index" aria-pressed="true"></button>
            <button data-view-toggle="grid" aria-pressed="false"></button>
            <section data-featured><article class="project-card"></article></section>
            <div data-view-panel="index">${rows}<button data-show-more hidden><span data-show-more-count></span></button></div>
            <div data-view-panel="grid">${cards}</div>
            <p data-empty hidden></p>
        </div>`;

    return document.querySelector('.wp-block-ix-projects');
}

const FIXTURE = [
    { year: '2026', items: [['Celebrity Autobiography', 'broadway entertainment'], ['itzenzo.tv', 'brand']] },
    { year: '2024', items: [['Outsiders', 'broadway ticketing']] },
    { year: '2018', items: [['Wolfmoon Bourbon', 'brand agency']] },
];

const state = (overrides = {}) => ({ category: 'all', query: '', view: 'index', expanded: false, ...overrides });
const visible = (block, selector) =>
    Array.from(block.querySelectorAll(selector)).filter((el) => !el.hasAttribute('aria-hidden'));

describe('isFiltered', () => {
    it('is false for the default state', () => {
        expect(isFiltered(state())).toBe(false);
    });

    it('is true for a category or a non-blank query', () => {
        expect(isFiltered(state({ category: 'brand' }))).toBe(true);
        expect(isFiltered(state({ query: 'wicked' }))).toBe(true);
        expect(isFiltered(state({ query: '   ' }))).toBe(false);
    });
});

describe('URL state', () => {
    it('reads a known category and view', () => {
        expect(readUrlState('?category=broadway&view=grid', ['all', 'broadway'])).toEqual({
            category: 'broadway',
            view: 'grid',
        });
    });

    it('falls back to defaults for unknown values', () => {
        expect(readUrlState('?category=nope&view=list', ['all', 'broadway'])).toEqual({
            category: 'all',
            view: 'index',
        });
    });

    it('writes non-defaults and drops defaults, keeping other params', () => {
        expect(writeUrlState('https://x.test/projects/?utm=1', { category: 'brand', view: 'grid' })).toBe(
            'https://x.test/projects/?utm=1&category=brand&view=grid'
        );
        expect(
            writeUrlState('https://x.test/projects/?category=brand&view=grid', { category: 'all', view: 'index' })
        ).toBe('https://x.test/projects/');
    });
});

describe('applyFilters', () => {
    let block;
    beforeEach(() => {
        block = createArchive(FIXTURE);
    });

    it('filters rows and cards together by category', () => {
        applyFilters(block, state({ category: 'brand' }));

        expect(visible(block, '.project-row').map((r) => r.dataset.title)).toEqual(['itzenzo.tv', 'wolfmoon bourbon']);
        expect(visible(block, '.project-card[data-project-item]')).toHaveLength(2);
    });

    it('combines search with category', () => {
        applyFilters(block, state({ category: 'brand', query: 'wolf' }));

        expect(visible(block, '.project-row').map((r) => r.dataset.title)).toEqual(['wolfmoon bourbon']);
    });
});

describe('applyRowLimit', () => {
    let block;
    beforeEach(() => {
        block = createArchive(FIXTURE);
    });

    it('collapses rows past the limit when unfiltered', () => {
        expect(applyRowLimit(block, state(), 2)).toBe(2);
        expect(block.querySelectorAll('.project-row[data-collapsed]')).toHaveLength(2);
    });

    it('shows everything once expanded or filtered', () => {
        expect(applyRowLimit(block, state({ expanded: true }), 2)).toBe(0);
        expect(applyRowLimit(block, state({ category: 'brand' }), 2)).toBe(0);
        expect(block.querySelectorAll('.project-row[data-collapsed]')).toHaveLength(0);
    });
});

describe('updateYearGroups', () => {
    it('hides empty groups and recounts matches', () => {
        const block = createArchive(FIXTURE);
        applyFilters(block, state({ category: 'broadway' }));

        updateYearGroups(block);

        const groups = block.querySelectorAll('[data-year-group]');
        expect([...groups].map((g) => g.hidden)).toEqual([false, false, true]);
        expect(groups[0].querySelector('[data-year-count]').textContent).toBe('1 project');
    });

    it('hides a group whose rows are all collapsed', () => {
        const block = createArchive(FIXTURE);
        applyRowLimit(block, state(), 2);

        updateYearGroups(block);

        const groups = block.querySelectorAll('[data-year-group]');
        expect([...groups].map((g) => g.hidden)).toEqual([false, true, true]);
    });
});

describe('setView', () => {
    it('sets data-view and toggle pressed states', () => {
        const block = createArchive(FIXTURE);

        setView(block, 'grid');

        expect(block.dataset.view).toBe('grid');
        expect(block.querySelector('[data-view-toggle="grid"]').getAttribute('aria-pressed')).toBe('true');
        expect(block.querySelector('[data-view-toggle="index"]').getAttribute('aria-pressed')).toBe('false');
    });
});

describe('render', () => {
    it('hides featured while filtering and shows the empty state with no matches', () => {
        const block = createArchive(FIXTURE);

        render(block, state({ query: 'no such project' }));

        expect(block.querySelector('[data-featured]').hidden).toBe(true);
        expect(block.querySelector('[data-empty]').hidden).toBe(false);
    });

    it('shows featured and the show-more count when unfiltered', () => {
        const block = createArchive(FIXTURE);

        render(block, state());

        expect(block.querySelector('[data-featured]').hidden).toBe(false);
        expect(block.querySelector('[data-empty]').hidden).toBe(true);
        expect(block.querySelector('[data-show-more]').hidden).toBe(true); // 4 rows < 20
    });

    it('marks the active category pill', () => {
        const block = createArchive(FIXTURE);

        render(block, state({ category: 'brand' }));

        expect(block.querySelector('[data-category-filter="brand"]').getAttribute('aria-pressed')).toBe('true');
        expect(block.querySelector('[data-category-filter="all"]').getAttribute('aria-pressed')).toBe('false');
    });
});

describe('initArchive', () => {
    it('applies ?category and ?view from the URL on load', () => {
        window.history.replaceState(null, '', '/projects/?category=brand&view=grid');
        const block = createArchive(FIXTURE);

        initArchive(block);

        expect(block.dataset.view).toBe('grid');
        expect(visible(block, '.project-card[data-project-item]')).toHaveLength(2);
        window.history.replaceState(null, '', '/');
    });

    it('pill clicks filter and update the URL', () => {
        window.history.replaceState(null, '', '/projects/');
        const block = createArchive(FIXTURE);
        initArchive(block);

        block.querySelector('[data-category-filter="broadway"]').click();

        expect(window.location.search).toBe('?category=broadway');
        expect(visible(block, '.project-row')).toHaveLength(2);
        window.history.replaceState(null, '', '/');
    });

    it('search input filters without touching the URL', () => {
        window.history.replaceState(null, '', '/projects/');
        const block = createArchive(FIXTURE);
        initArchive(block);
        const input = block.querySelector('.projects-search__input');

        input.value = 'outsiders';
        input.dispatchEvent(new Event('input'));

        expect(visible(block, '.project-row').map((r) => r.dataset.title)).toEqual(['outsiders']);
        expect(window.location.search).toBe('');
        window.history.replaceState(null, '', '/');
    });
});
