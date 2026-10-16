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
    // Audit the settled render. With motion enabled, axe can sample elements
    // mid scroll-reveal / fade-up and report near-invisible text as contrast
    // failures; reduced motion disables those transitions (IX scroll-reveal).
    test.beforeEach(async ({ page }) => {
        await page.emulateMedia({ reducedMotion: 'reduce' });
    });

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

    test('project detail page has no accessibility violations', async ({ page }) => {
        await page.goto('/projects/celebrity-autobiography/');
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

    test('meet me page has no accessibility violations', async ({ page }) => {
        await page.goto('/meet-me/');
        await page.waitForLoadState('networkidle');

        const results = await runAxe(page);

        expect(results.violations).toEqual([]);
    });

    test('framework page has no accessibility violations', async ({ page }) => {
        await page.goto('/framework/');
        await page.waitForLoadState('networkidle');

        const results = await runAxe(page);

        expect(results.violations).toEqual([]);
    });

    test('delivery page has no accessibility violations', async ({ page }) => {
        await page.goto('/framework/delivery/');
        await page.waitForLoadState('networkidle');

        const results = await runAxe(page);

        expect(results.violations).toEqual([]);
    });

    test('nous signal page has no accessibility violations', async ({ page }) => {
        await page.goto('/nous-signal/');
        await page.waitForLoadState('networkidle');

        const results = await runAxe(page);

        expect(results.violations).toEqual([]);
    });

    test('nous post has no accessibility violations', async ({ page }) => {
        await page.goto('/guaranteed-by-the-seller/');
        await page.waitForLoadState('networkidle');

        const results = await runAxe(page);

        expect(results.violations).toEqual([]);
    });

    test('404 page has no accessibility violations', async ({ page }) => {
        await page.goto('/this-page-does-not-exist/');
        await page.waitForLoadState('networkidle');

        const results = await runAxe(page);

        expect(results.violations).toEqual([]);
    });

    test('search results have no accessibility violations', async ({ page }) => {
        await page.goto('/?s=nvidia');
        await page.waitForLoadState('networkidle');

        const results = await runAxe(page);

        expect(results.violations).toEqual([]);
    });

    test('privacy page has no accessibility violations', async ({ page }) => {
        await page.goto('/privacy-policy/');
        await page.waitForLoadState('networkidle');

        const results = await runAxe(page);

        expect(results.violations).toEqual([]);
    });
});
