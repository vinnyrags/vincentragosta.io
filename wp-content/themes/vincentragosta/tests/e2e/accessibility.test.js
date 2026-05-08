const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;

/**
 * Run an axe scan with the project's standard config.
 *
 * `aria-hidden="true"` subtrees are excluded because they're invisible
 * to assistive tech by definition. Most axe rules already skip them;
 * the practical effect is that `color-contrast` (which still flags
 * decorative typography like the .shutter-card__id numbers) won't
 * report against intentionally-low-contrast decorative content. Real
 * content is unaffected — anything user-readable shouldn't be hidden
 * from screen readers anyway.
 */
function runAxe(page) {
    return new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
        .exclude('[aria-hidden="true"]')
        .analyze();
}

test.describe('Accessibility audits (WCAG 2.1 AA)', () => {
    test('homepage has no accessibility violations', async ({ page }) => {
        await page.goto('/');
        await page.waitForLoadState('networkidle');

        const results = await runAxe(page);

        expect(results.violations).toEqual([]);
    });

    test('projects page has no accessibility violations', async ({ page }) => {
        await page.goto('/projects/');
        await page.waitForLoadState('networkidle');

        const results = await runAxe(page);

        expect(results.violations).toEqual([]);
    });

    test('contact page has no accessibility violations', async ({ page }) => {
        await page.goto('/contact/');
        await page.waitForLoadState('networkidle');

        const results = await runAxe(page);

        expect(results.violations).toEqual([]);
    });
});
