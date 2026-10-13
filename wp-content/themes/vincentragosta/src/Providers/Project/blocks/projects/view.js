/**
 * Projects block interactive behavior.
 *
 * Archive ("all" mode, .is-archive): category pills, search, Index/Grid view
 * toggle, year-group visibility, "show more", and URL state (?category=, ?view=).
 * Every filterable project — index rows and grid cards — carries
 * [data-project-item] with data-title / data-category.
 *
 * Other modes render a plain card grid; only scroll reveal applies.
 */

import {
    filterBySearch,
    filterByTaxonomy,
    createScrollReveal,
} from '../../../Theme/assets/js/grid-toolkit';

export const ITEM = '[data-project-item]';
export const ROW = '.project-row';
const HIDDEN_ATTRS = ['data-search-hidden', 'data-category-hidden'];
export const DEFAULT_VIEW = 'index';
export const VIEWS = ['index', 'grid'];
export const ROW_LIMIT = 20;

/**
 * Whether any filter is active.
 * @param {{category: string, query: string}} state
 */
export function isFiltered(state) {
    return state.category !== 'all' || state.query.trim() !== '';
}

/**
 * Read archive state from a query string.
 * @param {string} search - e.g. window.location.search
 * @param {string[]} knownCategories - slugs that have a filter pill
 * @returns {{category: string, view: string}}
 */
export function readUrlState(search, knownCategories) {
    const params = new URLSearchParams(search);
    const category = params.get('category');
    const view = params.get('view');

    return {
        category: category && knownCategories.includes(category) ? category : 'all',
        view: VIEWS.includes(view) ? view : DEFAULT_VIEW,
    };
}

/**
 * Write category + view into a URL, dropping defaults. Other params are kept.
 * @param {string} href
 * @param {{category: string, view: string}} state
 * @returns {string}
 */
export function writeUrlState(href, state) {
    const url = new URL(href);

    if (state.category && state.category !== 'all') {
        url.searchParams.set('category', state.category);
    } else {
        url.searchParams.delete('category');
    }

    if (state.view && state.view !== DEFAULT_VIEW) {
        url.searchParams.set('view', state.view);
    } else {
        url.searchParams.delete('view');
    }

    return url.toString();
}

/**
 * Apply category + search filters to every project item in the block.
 * @param {HTMLElement} block
 * @param {{category: string, query: string}} state
 */
export function applyFilters(block, state) {
    filterByTaxonomy(block, ITEM, 'category', 'data-category-hidden', state.category, HIDDEN_ATTRS);
    filterBySearch(block, ITEM, state.query, HIDDEN_ATTRS);
}

/**
 * Collapse index rows past the limit when unfiltered and not expanded.
 * Returns how many rows are collapsed.
 * @param {HTMLElement} block
 * @param {{category: string, query: string, expanded: boolean}} state
 * @param {number} limit
 * @returns {number}
 */
export function applyRowLimit(block, state, limit = ROW_LIMIT) {
    const collapse = !state.expanded && !isFiltered(state);
    let collapsed = 0;

    block.querySelectorAll(ROW).forEach((row, index) => {
        const hide = collapse && index >= limit;
        row.toggleAttribute('data-collapsed', hide);
        if (hide) collapsed++;
    });

    return collapsed;
}

/**
 * Hide year groups with no visible rows and refresh their counts.
 * @param {HTMLElement} block
 */
export function updateYearGroups(block) {
    block.querySelectorAll('[data-year-group]').forEach((group) => {
        const rows = Array.from(group.querySelectorAll(ROW));
        const visible = rows.filter(
            (row) => !row.hasAttribute('aria-hidden') && !row.hasAttribute('data-collapsed')
        ).length;
        const matching = rows.filter((row) => !row.hasAttribute('aria-hidden')).length;

        group.hidden = visible === 0;

        const count = group.querySelector('[data-year-count]');
        if (count) {
            count.textContent = `${matching} ${matching === 1 ? 'project' : 'projects'}`;
        }
    });
}

/**
 * Switch between index and grid views.
 * @param {HTMLElement} block
 * @param {string} view
 */
export function setView(block, view) {
    block.dataset.view = view;
    block.querySelectorAll('[data-view-toggle]').forEach((button) => {
        button.setAttribute('aria-pressed', String(button.dataset.viewToggle === view));
    });
}

/**
 * Reflect the active category on the pills.
 * @param {HTMLElement} block
 * @param {string} category
 */
export function setActivePill(block, category) {
    block.querySelectorAll('[data-category-filter]').forEach((pill) => {
        pill.setAttribute('aria-pressed', String(pill.dataset.categoryFilter === category));
    });
}

/**
 * Re-render everything that depends on state.
 * @param {HTMLElement} block
 * @param {{category: string, query: string, view: string, expanded: boolean}} state
 */
export function render(block, state) {
    applyFilters(block, state);
    const collapsed = applyRowLimit(block, state);
    updateYearGroups(block);
    setView(block, state.view);
    setActivePill(block, state.category);

    const featured = block.querySelector('[data-featured]');
    if (featured) featured.hidden = isFiltered(state);

    const more = block.querySelector('[data-show-more]');
    if (more) {
        more.hidden = collapsed === 0;
        const count = more.querySelector('[data-show-more-count]');
        if (count) count.textContent = String(collapsed);
    }

    const panelSelector = `[data-view-panel="${state.view}"] ${ITEM}`;
    const anyVisible = Array.from(block.querySelectorAll(panelSelector)).some(
        (item) => !item.hasAttribute('aria-hidden')
    );
    const empty = block.querySelector('[data-empty]');
    if (empty) empty.hidden = anyVisible;
}

/**
 * Wire up one archive block.
 * @param {HTMLElement} block
 */
export function initArchive(block) {
    const knownCategories = Array.from(block.querySelectorAll('[data-category-filter]')).map(
        (pill) => pill.dataset.categoryFilter
    );
    const fromUrl = readUrlState(window.location.search, knownCategories);
    const state = { category: fromUrl.category, view: fromUrl.view, query: '', expanded: false };
    const reveal = createScrollReveal(block, block, '.project-card');

    const update = (syncUrl = true) => {
        render(block, state);
        reveal.reinit();
        if (syncUrl) {
            window.history.replaceState(null, '', writeUrlState(window.location.href, state));
        }
    };

    block.querySelectorAll('[data-category-filter]').forEach((pill) => {
        pill.addEventListener('click', () => {
            state.category = pill.dataset.categoryFilter;
            update();
        });
    });

    block.querySelectorAll('[data-view-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            state.view = button.dataset.viewToggle;
            update();
        });
    });

    const search = block.querySelector('.projects-search__input');
    if (search) {
        search.addEventListener('input', () => {
            state.query = search.value;
            update(false);
        });
    }

    const more = block.querySelector('[data-show-more]');
    if (more) {
        more.addEventListener('click', () => {
            const firstHidden = block.querySelector(`${ROW}[data-collapsed]`);
            state.expanded = true;
            update(false);
            firstHidden?.querySelector('a')?.focus();
        });
    }

    update(false);
}

/**
 * Initialize projects block functionality.
 */
export function initProjects() {
    document.querySelectorAll('.wp-block-ix-projects').forEach((block) => {
        if (block.classList.contains('is-archive')) {
            initArchive(block);
            return;
        }

        const grid = block.querySelector('.projects-grid');
        if (grid) createScrollReveal(block, grid, '.project-card');
    });
}

// Auto-init when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initProjects);
} else {
    initProjects();
}
