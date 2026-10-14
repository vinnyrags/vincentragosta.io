import { describe, it, expect, beforeEach } from 'vitest';
import {
    ROW_LIMIT,
    resolveMonth,
    writeUrlState,
    isNarrowed,
    rowMatches,
    groupInMonth,
    render,
    initArchive,
} from '../../../../../../src/Providers/Blog/blocks/blog/view.js';

/**
 * Build a Nous Signal archive block.
 * @param {Array<{key: string, filter: string, rows: Array<[string, string]>}>} groups - rows are [search text, tag slugs]
 * @param {{tag?: string}} options
 */
function createArchive(groups, { tag = '' } = {}) {
    const pills = ['all', ...groups.filter((g) => g.filter !== 'earlier').map((g) => g.key)];
    if (groups.some((g) => g.filter === 'earlier')) pills.push('earlier');

    const sections = groups
        .map(
            (g) => `
            <section data-month-group="${g.key}" data-filter="${g.filter}">
                <h3>${g.key} <span data-month-count></span></h3>
                <ol>${g.rows.map(([s, t]) => `<li data-nous-item data-search="${s}" data-tags="${t}"><a href="#">${s}</a></li>`).join('')}</ol>
                <button data-show-more hidden>Show <span data-show-more-count></span> more</button>
            </section>`
        )
        .join('');

    document.body.innerHTML = `
        <div class="wp-block-ix-blog is-archive">
            ${pills.map((p) => `<button data-month-filter="${p}" aria-pressed="${p === 'all'}"></button>`).join('')}
            ${tag ? `<button data-tag-filter="${tag}"></button>` : ''}
            <input class="nous-search__input" type="search" />
            ${sections}
            <div data-empty hidden><a data-search-all href="https://example.com/?post_type=post"></a></div>
        </div>`;

    return document.querySelector('.is-archive');
}

const rows = (n, prefix = 'signal') => Array.from({ length: n }, (_, i) => [`${prefix} ${i + 1}`, i % 2 ? 'ai chips' : 'ai']);

const visible = (block) => [...block.querySelectorAll('[data-nous-item]')].filter((r) => !r.hidden && !r.hasAttribute('data-collapsed'));

describe('resolveMonth', () => {
    const pills = ['2026-08', '2026-07'];
    const months = ['2026-08', '2026-07', '2026-06', '2026-05'];

    it('keeps a month that has a pill', () => {
        expect(resolveMonth('2026-07', pills, months)).toBe('2026-07');
    });

    it('maps an older month, or "earlier", to the Earlier pill', () => {
        expect(resolveMonth('2026-05', pills, months)).toBe('earlier');
        expect(resolveMonth('earlier', pills, months)).toBe('earlier');
    });

    it('falls back to all for missing or unknown values', () => {
        expect(resolveMonth(null, pills, months)).toBe('all');
        expect(resolveMonth('2019-01', pills, months)).toBe('all');
        expect(resolveMonth('earlier', months, months)).toBe('all');
    });
});

describe('writeUrlState', () => {
    it('writes month and tag and drops defaults, keeping other params', () => {
        expect(writeUrlState('https://x.test/nous-signal/?a=1', { month: '2026-08', tag: 'ai' }))
            .toBe('https://x.test/nous-signal/?a=1&month=2026-08&tag=ai');
        expect(writeUrlState('https://x.test/nous-signal/?month=2026-08&tag=ai', { month: 'all', tag: '' }))
            .toBe('https://x.test/nous-signal/');
    });
});

describe('row matching', () => {
    it('is narrowed by a search or a tag, not by the month', () => {
        expect(isNarrowed({ query: '  ', tag: '' })).toBe(false);
        expect(isNarrowed({ query: 'chip', tag: '' })).toBe(true);
        expect(isNarrowed({ query: '', tag: 'ai' })).toBe(true);
    });

    it('requires every search word and the exact tag slug', () => {
        const row = { search: 'priced on a promise frontier lab', tags: 'ipo openai' };

        expect(rowMatches(row, { query: 'Frontier  PROMISE', tag: '' })).toBe(true);
        expect(rowMatches(row, { query: 'frontier chips', tag: '' })).toBe(false);
        expect(rowMatches(row, { query: '', tag: 'openai' })).toBe(true);
        expect(rowMatches(row, { query: '', tag: 'open' })).toBe(false);
    });

    it('puts a group in the selected pill', () => {
        expect(groupInMonth('earlier', 'all')).toBe(true);
        expect(groupInMonth('earlier', 'earlier')).toBe(true);
        expect(groupInMonth('2026-08', 'earlier')).toBe(false);
    });
});

describe('render', () => {
    let block;

    beforeEach(() => {
        block = createArchive([
            { key: '2026-08', filter: '2026-08', rows: rows(ROW_LIMIT + 3) },
            { key: '2026-07', filter: '2026-07', rows: rows(2, 'july') },
            { key: '2026-03', filter: 'earlier', rows: rows(1, 'march') },
        ]);
    });

    it('collapses each month after the row limit and offers the rest', () => {
        render(block, { month: 'all', query: '', tag: '' }, new Set());

        const august = block.querySelector('[data-month-group="2026-08"]');
        expect(visible(block)).toHaveLength(ROW_LIMIT + 2 + 1);
        expect(august.querySelector('[data-show-more]').hidden).toBe(false);
        expect(august.querySelector('[data-show-more-count]').textContent).toBe('3');
    });

    it('shows a month in full once expanded', () => {
        render(block, { month: 'all', query: '', tag: '' }, new Set(['2026-08']));

        expect(visible(block)).toHaveLength(ROW_LIMIT + 3 + 2 + 1);
        expect(block.querySelector('[data-month-group="2026-08"] [data-show-more]').hidden).toBe(true);
    });

    it('limits groups to the selected pill and presses it', () => {
        render(block, { month: 'earlier', query: '', tag: '' }, new Set());

        expect(block.querySelector('[data-month-group="2026-08"]').hidden).toBe(true);
        expect(block.querySelector('[data-month-group="2026-03"]').hidden).toBe(false);
        expect(block.querySelector('[data-month-filter="earlier"]').getAttribute('aria-pressed')).toBe('true');
        expect(block.querySelector('[data-month-filter="all"]').getAttribute('aria-pressed')).toBe('false');
    });

    it('shows every match uncollapsed while searching and counts matches', () => {
        render(block, { month: 'all', query: 'signal', tag: '' }, new Set());

        expect(visible(block)).toHaveLength(ROW_LIMIT + 3);
        expect(block.querySelector('[data-month-group="2026-08"] [data-month-count]').textContent).toBe(String(ROW_LIMIT + 3));
        expect(block.querySelector('[data-month-group="2026-07"]').hidden).toBe(true);
    });

    it('shows the empty state with a site-search link when nothing matches', () => {
        render(block, { month: 'all', query: 'zzz', tag: '' }, new Set());

        const empty = block.querySelector('[data-empty]');
        expect(empty.hidden).toBe(false);
        expect(empty.querySelector('[data-search-all]').href).toBe('https://example.com/?post_type=post&s=zzz');
    });
});

describe('initArchive', () => {
    it('applies a ?tag= filter until its chip is removed', () => {
        const block = createArchive([{ key: '2026-08', filter: '2026-08', rows: rows(4) }], { tag: 'chips' });
        window.history.replaceState(null, '', '/nous-signal/?tag=chips');

        initArchive(block);
        expect(visible(block)).toHaveLength(2);

        block.querySelector('[data-tag-filter]').click();
        expect(visible(block)).toHaveLength(4);
        expect(window.location.search).toBe('');
    });

    it('reads ?month= and writes the pill back to the URL', () => {
        const block = createArchive([
            { key: '2026-08', filter: '2026-08', rows: rows(2) },
            { key: '2026-03', filter: 'earlier', rows: rows(2, 'march') },
        ]);
        window.history.replaceState(null, '', '/nous-signal/?month=2026-03');

        initArchive(block);
        expect(block.querySelector('[data-month-group="2026-08"]').hidden).toBe(true);

        block.querySelector('[data-month-filter="2026-08"]').click();
        expect(window.location.search).toBe('?month=2026-08');
        expect(block.querySelector('[data-month-group="2026-03"]').hidden).toBe(true);
    });
});
