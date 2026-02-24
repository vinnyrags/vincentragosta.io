import { describe, it, expect } from 'vitest';
import { sortProjects } from '../../../../../../src/Providers/Project/blocks/projects/view.js';

/**
 * Build a projects grid with cards
 */
function createProjectsGrid(projects) {
    const grid = document.createElement('div');
    grid.classList.add('projects-grid');

    projects.forEach(({ title, date }) => {
        const card = document.createElement('div');
        card.classList.add('project-card');
        card.dataset.title = title;
        card.dataset.date = date;
        card.textContent = title;
        grid.appendChild(card);
    });

    document.body.appendChild(grid);
    return grid;
}

function getCardTitles(grid) {
    return Array.from(grid.querySelectorAll('.project-card')).map((c) => c.dataset.title);
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
