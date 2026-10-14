const { test, expect } = require('@playwright/test');

test.describe('Smoke tests', () => {
    test('homepage loads with header and footer', async ({ page }) => {
        const response = await page.goto('/');

        expect(response.status()).toBe(200);
        await expect(page.locator('.header')).toBeVisible();
        await expect(page.locator('.footer')).toBeVisible();
    });

    test('homepage renders the redesign sections', async ({ page }) => {
        await page.goto('/');

        await expect(page.locator('h1')).toContainText('Building digital experiences');
        await expect(page.locator('.is-style-stat-strip > *')).toHaveCount(4);
        await expect(page.locator('.projects-featured__top .project-card')).toHaveCount(2);
        await expect(page.locator('.is-style-pipeline > .wp-block-columns > .wp-block-column')).toHaveCount(5);
        await expect(page.locator('.is-style-pipeline .wp-block-column.is-accent')).toHaveCount(1);
        await expect(page.locator('.is-style-layers > .wp-block-group.is-accent')).toHaveCount(1);
        await expect(page.locator('.is-style-testimonials .wp-block-quote')).toHaveCount(5);
        await expect(page.locator('.is-style-transmission .nous-log__row')).toHaveCount(5);
        await expect(page.locator('.is-style-transmission .nous-log__row.is-new')).toHaveCount(1);
    });

    test('homepage testimonials slide with prev/next controls', async ({ page }) => {
        await page.setViewportSize({ width: 1440, height: 900 });
        await page.goto('/');
        const row = page.locator('.is-style-testimonials');
        const prev = page.locator('.testimonials-controls__prev');
        const next = page.locator('.testimonials-controls__next');

        await expect(prev).toBeDisabled();
        await expect(next).toBeEnabled();

        await next.click();
        await expect.poll(() => row.evaluate((el) => el.scrollLeft)).toBeGreaterThan(0);
        await expect(prev).toBeEnabled();
    });

    test('header and footer render from the default content partials', async ({ page }) => {
        await page.goto('/');

        await expect(page.locator('header.site-header--header')).toHaveCount(1);
        await expect(page.locator('footer.site-footer--footer')).toHaveCount(1);
        await expect(page.locator('.site-footer .footer-cta')).toHaveCount(1);
    });

    test('contact page uses the compact footer partial (no CTA panel)', async ({ page }) => {
        await page.goto('/contact/');

        await expect(page.locator('footer.site-footer--footer-compact')).toHaveCount(1);
        await expect(page.locator('.site-footer .footer-cta')).toHaveCount(0);
    });

    test('projects page loads the archive: stats, filters, featured, index', async ({ page }) => {
        const response = await page.goto('/projects/');

        expect(response.status()).toBe(200);
        await expect(page.locator('.projects-stats')).toBeVisible();
        await expect(page.locator('[data-category-filter="all"]')).toHaveAttribute('aria-pressed', 'true');
        await expect(page.locator('.projects-featured .project-card').first()).toBeVisible();
        await expect(page.locator('.project-row').first()).toBeVisible();
    });

    test('projects category filter narrows the index and updates the URL', async ({ page }) => {
        await page.goto('/projects/');
        const visibleRows = page.locator('.project-row:not([aria-hidden]):not([data-collapsed])');
        const before = await page.locator('.project-row:not([aria-hidden])').count();

        await page.locator('[data-category-filter]:not([data-category-filter="all"])').first().click();

        await expect(page).toHaveURL(/\?category=/);
        await expect(page.locator('.projects-featured')).toBeHidden();
        expect(await page.locator('.project-row:not([aria-hidden])').count()).toBeLessThan(before);
        await expect(visibleRows.first()).toBeVisible();
    });

    test('projects view toggle switches to the card grid via ?view=grid', async ({ page }) => {
        await page.goto('/projects/?view=grid');

        await expect(page.locator('.projects-grid .project-card').first()).toBeVisible();
        await expect(page.locator('.projects-index')).toBeHidden();
        await expect(page.locator('[data-view-toggle="grid"]')).toHaveAttribute('aria-pressed', 'true');
    });

    test('project detail renders from fields: header, meta, sections, contents, next', async ({ page }) => {
        const response = await page.goto('/projects/celebrity-autobiography/');

        expect(response.status()).toBe(200);
        await expect(page.locator('h1.project-detail__title')).toContainText('Celebrity');
        await expect(page.locator('.project-detail__meta dt')).toContainText(['Client', 'Role', 'Year']);
        await expect(page.locator('.project-section')).toHaveCount(3);
        await expect(page.locator('.project-toc [data-toc-link]')).toHaveCount(3);
        await expect(page.locator('.project-next')).toBeVisible();
    });

    test('project detail contents card tracks the section in view', async ({ page }) => {
        await page.goto('/projects/celebrity-autobiography/');

        await page.locator('.project-toc a[href="#results"]').click();

        await expect(page.locator('[data-toc-link="results"]')).toHaveAttribute('aria-current', 'true');
    });

    test('contact page loads with WPForms container', async ({ page }) => {
        const response = await page.goto('/contact/');

        expect(response.status()).toBe(200);
        await expect(page.locator('.wpforms-container')).toBeVisible();
    });

    test('meet me page renders its CMS sections without console errors', async ({ page }) => {
        const errors = [];
        page.on('console', (msg) => {
            if (msg.type() === 'error') {
                errors.push(msg.text());
            }
        });

        const response = await page.goto('/meet-me/');
        await page.waitForLoadState('networkidle');

        expect(response.status()).toBe(200);
        await expect(page.locator('h1')).toContainText('Building websites');
        await expect(page.locator('.is-style-window .is-style-spec')).toBeVisible();
        await expect(page.locator('.is-style-stat-strip')).toBeVisible();
        await expect(page.locator('.is-style-timeline > .wp-block-columns')).toHaveCount(4);
        await expect(page.locator('.is-style-paper img')).toBeVisible();
        expect(errors).toEqual([]);
    });

    test('no console errors on homepage', async ({ page }) => {
        const errors = [];
        page.on('console', (msg) => {
            if (msg.type() === 'error') {
                errors.push(msg.text());
            }
        });

        await page.goto('/');
        await page.waitForLoadState('networkidle');

        expect(errors).toEqual([]);
    });

    test('no console errors on projects page', async ({ page }) => {
        const errors = [];
        page.on('console', (msg) => {
            if (msg.type() === 'error') {
                errors.push(msg.text());
            }
        });

        await page.goto('/projects/');
        await page.waitForLoadState('networkidle');

        expect(errors).toEqual([]);
    });

    test('no console errors on contact page', async ({ page }) => {
        const errors = [];
        page.on('console', (msg) => {
            if (msg.type() === 'error') {
                errors.push(msg.text());
            }
        });

        await page.goto('/contact/');
        await page.waitForLoadState('networkidle');

        expect(errors).toEqual([]);
    });
});
