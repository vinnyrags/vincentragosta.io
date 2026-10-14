const { test, expect } = require('@playwright/test');

// The header is the `header` content partial: core/site-title, core/navigation
// (with its own mobile overlay), core/search, and a CTA button.
const OPEN = '.header .wp-block-navigation__responsive-container-open';
const OVERLAY = '.header .wp-block-navigation__responsive-container';

test.describe('Header interactions', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/');
        await page.waitForLoadState('networkidle');
    });

    test('desktop shows inline nav, search, and CTA without the overlay toggle', async ({ page }) => {
        await page.setViewportSize({ width: 1440, height: 900 });

        await expect(page.locator('.header .wp-block-navigation-item__content').first()).toBeVisible();
        await expect(page.locator('.header .wp-block-search__input')).toBeVisible();
        await expect(page.locator('.header__cta .wp-block-button__link')).toBeVisible();
        await expect(page.locator(OPEN)).toBeHidden();
    });

    test('Cmd/Ctrl+K focuses search and Escape blurs it', async ({ page }) => {
        await page.setViewportSize({ width: 1440, height: 900 });
        const input = page.locator('.header .wp-block-search__input');

        await page.keyboard.press('ControlOrMeta+k');
        await expect(input).toBeFocused();

        await page.keyboard.press('Escape');
        await expect(input).not.toBeFocused();
    });

    test('tablet collapses navigation into the overlay toggle', async ({ page }) => {
        await page.setViewportSize({ width: 820, height: 1000 });

        await expect(page.locator(OPEN)).toBeVisible();
        await expect(page.locator('.header .wp-block-navigation-item__content').first()).toBeHidden();
    });

    test('mobile overlay opens, shows links, and Escape closes it', async ({ page }) => {
        await page.setViewportSize({ width: 375, height: 812 });

        await page.locator(OPEN).click();
        await expect(page.locator(`${OVERLAY}.is-menu-open`)).toBeVisible();
        await expect(page.locator(`${OVERLAY} .wp-block-navigation-item__content`).first()).toBeVisible();

        await page.keyboard.press('Escape');
        await expect(page.locator(`${OVERLAY}.is-menu-open`)).toHaveCount(0);
    });

    test('overlay close button sits exactly where the hamburger was', async ({ page }) => {
        await page.setViewportSize({ width: 375, height: 812 });

        const open = await page.locator(OPEN).boundingBox();
        await page.locator(OPEN).click();
        const close = await page.locator('.header .wp-block-navigation__responsive-container-close').boundingBox();

        expect(Math.round(close.x)).toBe(Math.round(open.x));
        expect(Math.round(close.y)).toBe(Math.round(open.y));
        expect(Math.round(close.width)).toBe(Math.round(open.width));
        // the brand stays visible above the open overlay
        await expect(page.locator('.header__brand')).toBeVisible();
    });

    test('focus stays inside the open overlay', async ({ page }) => {
        await page.setViewportSize({ width: 375, height: 812 });

        await page.locator(OPEN).click();
        const overlay = page.locator(`${OVERLAY}.is-menu-open`);
        await expect(overlay).toBeVisible();

        const linkCount = await overlay.locator('a[href]').count();
        for (let i = 0; i <= linkCount + 1; i++) {
            await page.keyboard.press('Tab');
        }

        const focusInside = await overlay.evaluate((el) => el.contains(document.activeElement));
        expect(focusInside).toBe(true);
    });
});
