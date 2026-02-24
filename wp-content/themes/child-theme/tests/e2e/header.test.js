const { test, expect } = require('@playwright/test');

test.describe('Header interactions', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/');
        await page.waitForLoadState('networkidle');
    });

    test('dark/light mode toggle switches class and persists', async ({ page }) => {
        const modeToggle = page.locator('.header__mode-toggle');
        await expect(modeToggle).toBeVisible();

        // Click toggle to switch to light mode
        await modeToggle.click();
        await expect(page.locator('html')).toHaveClass(/light-mode/);

        // Verify persistence across reload
        await page.reload();
        await page.waitForLoadState('networkidle');
        await expect(page.locator('html')).toHaveClass(/light-mode/);

        // Toggle back to dark mode
        await page.locator('.header__mode-toggle').click();
        await expect(page.locator('html')).not.toHaveClass(/light-mode/);
    });

    test('hamburger menu opens overlay and Escape closes it', async ({ page }) => {
        // Set mobile viewport to trigger hamburger menu
        await page.setViewportSize({ width: 375, height: 812 });
        await page.reload();
        await page.waitForLoadState('networkidle');

        const menuToggle = page.locator('.header__menu-toggle');
        await expect(menuToggle).toBeVisible();

        // Open menu
        await menuToggle.click();
        await expect(page.locator('#nav-overlay')).toBeVisible();
        await expect(menuToggle).toHaveAttribute('aria-expanded', 'true');

        // Close with Escape
        await page.keyboard.press('Escape');
        await expect(menuToggle).toHaveAttribute('aria-expanded', 'false');
    });

    test('focus is trapped within open overlay', async ({ page }) => {
        await page.setViewportSize({ width: 375, height: 812 });
        await page.reload();
        await page.waitForLoadState('networkidle');

        const menuToggle = page.locator('.header__menu-toggle');
        await menuToggle.click();
        await expect(page.locator('#nav-overlay')).toBeVisible();

        // Tab through all focusable elements — should cycle back
        const overlay = page.locator('#nav-overlay');
        const links = overlay.locator('a[href]');
        const linkCount = await links.count();

        // Tab past all links — focus should wrap to menu toggle
        for (let i = 0; i <= linkCount; i++) {
            await page.keyboard.press('Tab');
        }

        // After wrapping, focus should be on the menu toggle
        await expect(menuToggle).toBeFocused();
    });
});
