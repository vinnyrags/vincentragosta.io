import { describe, it, expect } from 'vitest';
import {
    sortProjects,
    filterProjects,
    filterByCategory,
} from '../../../../../../src/Providers/Project/blocks/projects/view.js';

/**
 * Build a projects grid with cards
 */
function createProjectsGrid(projects) {
    const grid = document.createElement('div');
    grid.classList.add('projects-grid');

    projects.forEach(({ title, date, category }) => {
        const card = document.createElement('div');
        card.classList.add('project-card');
        card.dataset.title = title;
        card.dataset.date = date;
        if (category) card.dataset.category = category;
        card.textContent = title;
        grid.appendChild(card);
    });

    document.body.appendChild(grid);
    return grid;
}

function getCardTitles(grid) {
    return Array.from(grid.querySelectorAll('.project-card')).map((c) => c.dataset.title);
}

function getVisibleCards(grid) {
    return Array.from(grid.querySelectorAll('.project-card:not(.is-hidden)'));
}

describe('sortProjects', () => {
    const projects = [
        { title: 'Charlie', date: '2024-03-15' },
        { title: 'Alpha', date: '2024-01-10' },
        { title: 'Bravo', date: '2024-02-20' },
    ];

    it('sorts by title ascending', () => {
        const grid = createProjectsGrid(projects);

        sortProjects(grid, 'title', 'asc');

        expect(getCardTitles(grid)).toEqual(['Alpha', 'Bravo', 'Charlie']);
    });

    it('sorts by title descending', () => {
        const grid = createProjectsGrid(projects);

        sortProjects(grid, 'title', 'desc');

        expect(getCardTitles(grid)).toEqual(['Charlie', 'Bravo', 'Alpha']);
    });

    it('sorts by date ascending', () => {
        const grid = createProjectsGrid(projects);

        sortProjects(grid, 'date', 'asc');

        const titles = getCardTitles(grid);
        expect(titles).toEqual(['Alpha', 'Bravo', 'Charlie']);
    });

    it('sorts by date descending', () => {
        const grid = createProjectsGrid(projects);

        sortProjects(grid, 'date', 'desc');

        const titles = getCardTitles(grid);
        expect(titles).toEqual(['Charlie', 'Bravo', 'Alpha']);
    });

    it('removes is-visible class from cards after sorting', () => {
        const grid = createProjectsGrid(projects);
        grid.querySelectorAll('.project-card').forEach((c) => c.classList.add('is-visible'));

        sortProjects(grid, 'title', 'asc');

        grid.querySelectorAll('.project-card').forEach((card) => {
            expect(card.classList.contains('is-visible')).toBe(false);
        });
    });

    it('handles missing data attributes gracefully', () => {
        const grid = createProjectsGrid([
            { title: '', date: '' },
            { title: 'Zulu', date: '2024-06-01' },
            { title: 'Alpha', date: '' },
        ]);

        sortProjects(grid, 'title', 'asc');

        const titles = getCardTitles(grid);
        expect(titles[0]).toBe('');
        expect(titles[1]).toBe('Alpha');
        expect(titles[2]).toBe('Zulu');
    });

    it('maintains all cards in the grid after sorting', () => {
        const grid = createProjectsGrid(projects);

        sortProjects(grid, 'title', 'asc');

        expect(grid.querySelectorAll('.project-card')).toHaveLength(3);
    });

    it('can sort the same grid multiple times', () => {
        const grid = createProjectsGrid(projects);

        sortProjects(grid, 'title', 'asc');
        expect(getCardTitles(grid)).toEqual(['Alpha', 'Bravo', 'Charlie']);

        sortProjects(grid, 'title', 'desc');
        expect(getCardTitles(grid)).toEqual(['Charlie', 'Bravo', 'Alpha']);

        sortProjects(grid, 'date', 'asc');
        expect(getCardTitles(grid)).toEqual(['Alpha', 'Bravo', 'Charlie']);
    });
});

describe('filterProjects', () => {
    const projects = [
        { title: 'alpha project', date: '2024-01-10', category: 'web' },
        { title: 'bravo project', date: '2024-02-20', category: 'branding' },
        { title: 'charlie delta', date: '2024-03-15', category: 'web' },
    ];

    it('filters cards by partial title match', () => {
        const grid = createProjectsGrid(projects);

        filterProjects(grid, 'alpha');

        const visible = getVisibleCards(grid);
        expect(visible).toHaveLength(1);
        expect(visible[0].dataset.title).toBe('alpha project');
    });

    it('shows all cards when query is empty', () => {
        const grid = createProjectsGrid(projects);

        filterProjects(grid, 'alpha');
        filterProjects(grid, '');

        expect(getVisibleCards(grid)).toHaveLength(3);
    });

    it('is case insensitive', () => {
        const grid = createProjectsGrid(projects);

        filterProjects(grid, 'BRAVO');

        const visible = getVisibleCards(grid);
        expect(visible).toHaveLength(1);
        expect(visible[0].dataset.title).toBe('bravo project');
    });

    it('matches multiple cards with shared term', () => {
        const grid = createProjectsGrid(projects);

        filterProjects(grid, 'project');

        expect(getVisibleCards(grid)).toHaveLength(2);
    });

    it('hides all cards when query matches nothing', () => {
        const grid = createProjectsGrid(projects);

        filterProjects(grid, 'zzzzz');

        expect(getVisibleCards(grid)).toHaveLength(0);
    });

    it('removes is-visible from hidden cards', () => {
        const grid = createProjectsGrid(projects);
        grid.querySelectorAll('.project-card').forEach((c) => c.classList.add('is-visible'));

        filterProjects(grid, 'charlie');

        const hidden = grid.querySelectorAll('.project-card.is-hidden');
        hidden.forEach((card) => {
            expect(card.classList.contains('is-visible')).toBe(false);
        });
    });

    it('works with whitespace-only query (shows all)', () => {
        const grid = createProjectsGrid(projects);

        filterProjects(grid, '   ');

        expect(getVisibleCards(grid)).toHaveLength(3);
    });

    it('works correctly after sorting', () => {
        const grid = createProjectsGrid(projects);

        sortProjects(grid, 'title', 'desc');
        filterProjects(grid, 'delta');

        const visible = getVisibleCards(grid);
        expect(visible).toHaveLength(1);
        expect(visible[0].dataset.title).toBe('charlie delta');
    });
});

describe('filterByCategory', () => {
    const projects = [
        { title: 'alpha', date: '2024-01-10', category: 'web' },
        { title: 'bravo', date: '2024-02-20', category: 'branding' },
        { title: 'charlie', date: '2024-03-15', category: 'web' },
        { title: 'delta', date: '2024-04-01' }, // no category
    ];

    it('filters cards to show only matching category', () => {
        const grid = createProjectsGrid(projects);

        filterByCategory(grid, 'web');

        const visible = getVisibleCards(grid);
        expect(visible).toHaveLength(2);
        expect(visible[0].dataset.title).toBe('alpha');
        expect(visible[1].dataset.title).toBe('charlie');
    });

    it('shows all cards when category is "all"', () => {
        const grid = createProjectsGrid(projects);

        filterByCategory(grid, 'web');
        filterByCategory(grid, 'all');

        expect(getVisibleCards(grid)).toHaveLength(4);
    });

    it('hides cards with no category when a specific category is selected', () => {
        const grid = createProjectsGrid(projects);

        filterByCategory(grid, 'branding');

        const visible = getVisibleCards(grid);
        expect(visible).toHaveLength(1);
        expect(visible[0].dataset.title).toBe('bravo');
    });

    it('hides all cards when category matches nothing', () => {
        const grid = createProjectsGrid(projects);

        filterByCategory(grid, 'nonexistent');

        expect(getVisibleCards(grid)).toHaveLength(0);
    });

    it('removes is-visible from hidden cards', () => {
        const grid = createProjectsGrid(projects);
        grid.querySelectorAll('.project-card').forEach((c) => c.classList.add('is-visible'));

        filterByCategory(grid, 'branding');

        const hidden = grid.querySelectorAll('.project-card.is-hidden');
        hidden.forEach((card) => {
            expect(card.classList.contains('is-visible')).toBe(false);
        });
    });

    it('works correctly after sorting', () => {
        const grid = createProjectsGrid(projects);

        sortProjects(grid, 'title', 'desc');
        filterByCategory(grid, 'web');

        const visible = getVisibleCards(grid);
        expect(visible).toHaveLength(2);
    });
});

describe('filter composition', () => {
    const projects = [
        { title: 'alpha web', date: '2024-01-10', category: 'web' },
        { title: 'bravo brand', date: '2024-02-20', category: 'branding' },
        { title: 'charlie web', date: '2024-03-15', category: 'web' },
    ];

    it('search + category: both must match for card to be visible', () => {
        const grid = createProjectsGrid(projects);

        filterByCategory(grid, 'web');
        filterProjects(grid, 'charlie');

        const visible = getVisibleCards(grid);
        expect(visible).toHaveLength(1);
        expect(visible[0].dataset.title).toBe('charlie web');
    });

    it('card hidden by category stays hidden even if search matches', () => {
        const grid = createProjectsGrid(projects);

        filterByCategory(grid, 'web');
        filterProjects(grid, 'bravo');

        // bravo matches search but is branding category — stays hidden
        expect(getVisibleCards(grid)).toHaveLength(0);
    });

    it('clearing search restores category-filtered view', () => {
        const grid = createProjectsGrid(projects);

        filterByCategory(grid, 'web');
        filterProjects(grid, 'alpha');
        filterProjects(grid, '');

        // search cleared, category still active — both web cards visible
        const visible = getVisibleCards(grid);
        expect(visible).toHaveLength(2);
    });

    it('clearing category restores search-filtered view', () => {
        const grid = createProjectsGrid(projects);

        filterProjects(grid, 'alpha');
        filterByCategory(grid, 'branding');
        filterByCategory(grid, 'all');

        // category cleared, search still active — only alpha matches
        const visible = getVisibleCards(grid);
        expect(visible).toHaveLength(1);
        expect(visible[0].dataset.title).toBe('alpha web');
    });

    it('clearing both filters shows all cards', () => {
        const grid = createProjectsGrid(projects);

        filterByCategory(grid, 'web');
        filterProjects(grid, 'charlie');
        filterByCategory(grid, 'all');
        filterProjects(grid, '');

        expect(getVisibleCards(grid)).toHaveLength(3);
    });
});
