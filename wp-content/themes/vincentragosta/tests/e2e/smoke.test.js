const { test, expect } = require('@playwright/test');

test.describe('Smoke tests', () => {
    test('homepage loads with header and footer', async ({ page }) => {
        const response = await page.goto('/');

        expect(response.status()).toBe(200);
        await expect(page.locator('.header')).toBeVisible();
        await expect(page.locator('.footer')).toBeVisible();
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

    test('projects page loads with project cards and sort dropdown', async ({ page }) => {
        const response = await page.goto('/projects/');

        expect(response.status()).toBe(200);
        await expect(page.locator('.project-card').first()).toBeVisible();
        await expect(page.locator('[data-dropdown="sort"]')).toBeVisible();
    });

    test('contact page loads with WPForms container', async ({ page }) => {
        const response = await page.goto('/contact/');

        expect(response.status()).toBe(200);
        await expect(page.locator('.wpforms-container')).toBeVisible();
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
