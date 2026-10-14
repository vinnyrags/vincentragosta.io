/**
 * Blog block interactive behavior — the Nous Signal archive.
 *
 * Month pills (a month key, "earlier", or "all"), search, an optional tag
 * filter (rendered server-side from ?tag=), per-month "show more", and URL
 * state (?month=, ?tag=). Every row carries [data-nous-item] with
 * data-search (lowercase title + excerpt) and data-tags (slugs).
 */

export const ITEM = '[data-nous-item]';
export const GROUP = '[data-month-group]';
export const ALL = 'all';
export const EARLIER = 'earlier';
export const ROW_LIMIT = 8;

/**
 * Resolve a ?month= value to a pill filter. A month without its own pill
 * resolves to "earlier"; anything unknown resolves to "all".
 * @param {string|null} value - e.g. "2026-08", "earlier"
 * @param {string[]} pillKeys - month keys that have a pill (newest first)
 * @param {string[]} monthKeys - every month in the archive
 * @returns {string}
 */
export function resolveMonth(value, pillKeys, monthKeys) {
    if (!value) return ALL;
    if (pillKeys.includes(value)) return value;
    if (value === EARLIER && monthKeys.some((key) => !pillKeys.includes(key))) return EARLIER;
    if (monthKeys.includes(value)) return EARLIER;
    return ALL;
}

/**
 * Write month + tag into a URL, dropping defaults. Other params are kept.
 * @param {string} href
 * @param {{month: string, tag: string}} state
 * @returns {string}
 */
export function writeUrlState(href, state) {
    const url = new URL(href);

    if (state.month && state.month !== ALL) {
        url.searchParams.set('month', state.month);
    } else {
        url.searchParams.delete('month');
    }

    if (state.tag) {
        url.searchParams.set('tag', state.tag);
    } else {
        url.searchParams.delete('tag');
    }

    return url.toString();
}

/**
 * Whether a search or tag narrows the rows (month pills only choose groups).
 * @param {{query: string, tag: string}} state
 */
export function isNarrowed(state) {
    return state.query.trim() !== '' || state.tag !== '';
}

/**
 * Whether a row matches the search and tag. Every search word must appear.
 * @param {{search: string, tags: string}} row - the row's data attributes
 * @param {{query: string, tag: string}} state
 */
export function rowMatches(row, state) {
    const words = state.query.trim().toLowerCase().split(/\s+/).filter(Boolean);
    const text = row.search || '';

    if (!words.every((word) => text.includes(word))) return false;
    if (state.tag && !(row.tags || '').split(' ').includes(state.tag)) return false;

    return true;
}

/**
 * Whether a month group is in the selected pill.
 * @param {string} groupFilter - the group's data-filter (its key, or "earlier")
 * @param {string} month - the selected pill
 */
export function groupInMonth(groupFilter, month) {
    return month === ALL || groupFilter === month;
}

/**
 * Apply state to the archive DOM.
 * @param {HTMLElement} block
 * @param {{month: string, query: string, tag: string}} state
 * @param {Set<string>} expanded - month keys whose "show more" was used
 */
export function render(block, state, expanded) {
    const narrowed = isNarrowed(state);
    let visibleRows = 0;

    block.querySelectorAll('[data-month-filter]').forEach((pill) => {
        pill.setAttribute('aria-pressed', String(pill.dataset.monthFilter === state.month));
    });

    block.querySelectorAll(GROUP).forEach((group) => {
        const inMonth = groupInMonth(group.dataset.filter, state.month);
        const collapse = !narrowed && !expanded.has(group.dataset.monthGroup);
        let matches = 0;

        group.querySelectorAll(ITEM).forEach((row) => {
            const match = inMonth && rowMatches(row.dataset, state);

            row.hidden = !match;
            if (match) matches += 1;
            row.toggleAttribute('data-collapsed', match && collapse && matches > ROW_LIMIT);
        });

        group.hidden = matches === 0;
        visibleRows += matches;

        const count = group.querySelector('[data-month-count]');
        if (count) count.textContent = String(matches);

        const more = group.querySelector('[data-show-more]');
        if (more) {
            const hiddenRows = collapse ? Math.max(0, matches - ROW_LIMIT) : 0;
            more.hidden = hiddenRows === 0;
            const moreCount = more.querySelector('[data-show-more-count]');
            if (moreCount) moreCount.textContent = String(hiddenRows);
        }
    });

    const empty = block.querySelector('[data-empty]');
    if (empty) {
        empty.hidden = visibleRows > 0;

        const searchAll = empty.querySelector('[data-search-all]');
        if (searchAll) {
            const url = new URL(searchAll.href);
            url.searchParams.set('s', state.query.trim());
            url.searchParams.set('post_type', 'post');
            searchAll.href = url.toString();
        }
    }
}

/**
 * Wire one archive block.
 * @param {HTMLElement} block
 */
export function initArchive(block) {
    const groups = [...block.querySelectorAll(GROUP)];
    const pillKeys = [...block.querySelectorAll('[data-month-filter]')]
        .map((pill) => pill.dataset.monthFilter)
        .filter((key) => key !== ALL && key !== EARLIER);
    const monthKeys = groups.map((group) => group.dataset.monthGroup);
    const tagButton = block.querySelector('[data-tag-filter]');
    const search = block.querySelector('.nous-search__input');
    const expanded = new Set();

    const state = {
        month: resolveMonth(new URLSearchParams(window.location.search).get('month'), pillKeys, monthKeys),
        query: '',
        tag: tagButton ? tagButton.dataset.tagFilter : '',
    };

    const update = (syncUrl) => {
        render(block, state, expanded);
        if (syncUrl) {
            window.history.replaceState(null, '', writeUrlState(window.location.href, state));
        }
    };

    block.querySelectorAll('[data-month-filter]').forEach((pill) => {
        pill.addEventListener('click', () => {
            state.month = pill.dataset.monthFilter;
            update(true);
        });
    });

    if (tagButton) {
        tagButton.addEventListener('click', () => {
            state.tag = '';
            tagButton.remove();
            update(true);
            if (search) search.focus();
        });
    }

    if (search) {
        search.addEventListener('input', () => {
            state.query = search.value;
            update(false);
        });
    }

    groups.forEach((group) => {
        const more = group.querySelector('[data-show-more]');
        if (!more) return;

        more.addEventListener('click', () => {
            const firstHidden = group.querySelector(`${ITEM}[data-collapsed] a`);
            expanded.add(group.dataset.monthGroup);
            update(false);
            if (firstHidden) firstHidden.focus();
        });
    });

    update(false);
}

function init() {
    document.querySelectorAll('.wp-block-ix-blog.is-archive').forEach(initArchive);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
