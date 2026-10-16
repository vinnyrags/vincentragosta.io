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

    test('projects "show more" disappears once every row is shown', async ({ page }) => {
        await page.goto('/projects/');
        const more = page.locator('[data-show-more]');

        await expect(more).toBeVisible();
        await more.click();
        await expect(more).toBeHidden();

        await page.locator('[data-category-filter]').nth(2).click();
        await expect(more).toBeHidden();
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
        await expect(page.locator('.toc [data-toc-link]')).toHaveCount(3);
        await expect(page.locator('.project-next')).toBeVisible();
    });

    test('project detail contents card tracks the section in view', async ({ page }) => {
        await page.goto('/projects/celebrity-autobiography/');

        await page.locator('.toc a[href="#results"]').click();

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

    test('privacy page contents card tracks sections and uses the compact footer', async ({ page }) => {
        const response = await page.goto('/privacy-policy/');

        expect(response.status()).toBe(200);
        await expect(page.locator('.toc [data-toc-link]')).toHaveCount(6);
        await expect(page.locator('footer.site-footer--footer-compact')).toHaveCount(1);

        await page.locator('.toc a[href="#cookies"]').click();
        await expect(page.locator('.toc a[href="#cookies"]')).toHaveAttribute('aria-current', 'true');
    });

    test('framework page lists all seven sections in its contents card', async ({ page }) => {
        const errors = [];
        page.on('console', (msg) => {
            if (msg.type() === 'error') {
                errors.push(msg.text());
            }
        });

        const response = await page.goto('/framework/');
        await page.waitForLoadState('networkidle');

        expect(response.status()).toBe(200);
        await expect(page.locator('h1')).toContainText('The framework');
        await expect(page.locator('.is-style-layers')).toBeVisible();
        await expect(page.locator('.is-style-stat-strip')).toBeVisible();
        await expect(page.locator('.toc [data-toc-link]')).toHaveCount(7);

        await page.locator('.toc a[href="#security"]').click();
        await expect(page.locator('.toc a[href="#security"]')).toHaveAttribute('aria-current', 'true');
        expect(errors).toEqual([]);
    });

    test('delivery page lists all five sections and links back to the framework', async ({ page }) => {
        const errors = [];
        page.on('console', (msg) => {
            if (msg.type() === 'error') {
                errors.push(msg.text());
            }
        });

        const response = await page.goto('/framework/delivery/');
        await page.waitForLoadState('networkidle');

        expect(response.status()).toBe(200);
        await expect(page.locator('h1')).toContainText('From push to');
        await expect(page.locator('.is-style-layers')).toBeVisible();
        await expect(page.locator('.is-style-stat-strip > *')).toHaveCount(4);
        await expect(page.locator('.toc [data-toc-link]')).toHaveCount(5);
        await expect(page.locator('.site-content a[href="/framework/"]')).toHaveCount(1);

        await page.locator('.toc a[href="#hardening"]').click();
        await expect(page.locator('.toc a[href="#hardening"]')).toHaveAttribute('aria-current', 'true');
        expect(errors).toEqual([]);
    });

    test('nous signal archive: stats, latest, month pills, collapsed months', async ({ page }) => {
        const errors = [];
        page.on('console', (msg) => {
            if (msg.type() === 'error') {
                errors.push(msg.text());
            }
        });

        const response = await page.goto('/nous-signal/');
        await page.waitForLoadState('networkidle');

        expect(response.status()).toBe(200);
        await expect(page.locator('body')).toHaveClass(/nous-signal-page/);
        await expect(page.locator('.nous-stats')).toBeVisible();
        await expect(page.locator('.nous-latest')).toBeVisible();
        await expect(page.locator('.nous-month').first().locator('.nous-row:visible')).toHaveCount(8);

        const second = page.locator('[data-month-filter]').nth(1);
        const month = await second.getAttribute('data-month-filter');
        await second.click();
        await expect(second).toHaveAttribute('aria-pressed', 'true');
        await expect(page).toHaveURL(new RegExp(`month=${month}`));
        await expect(page.locator('.nous-month:visible')).toHaveCount(1);
        expect(errors).toEqual([]);
    });

    test('nous signal search narrows rows and shows the empty state', async ({ page }) => {
        await page.goto('/nous-signal/');
        await page.locator('.nous-search__input').fill('zzzz-no-such-signal');
        await expect(page.locator('[data-empty]')).toBeVisible();
        await expect(page.locator('.nous-row:visible')).toHaveCount(0);
    });

    test('nous post renders its header, contents, and the way back to the archive', async ({ page }) => {
        const errors = [];
        page.on('console', (msg) => {
            if (msg.type() === 'error') {
                errors.push(msg.text());
            }
        });

        await page.goto('/nous-signal/');
        await page.locator('.nous-row__link').first().click();
        await page.waitForLoadState('networkidle');

        await expect(page.locator('body')).toHaveClass(/nous-signal-page/);
        await expect(page.locator('.nous-post__title')).toBeVisible();
        await expect(page.locator('.nous-post__number')).toContainText('signal №');
        await expect(page.locator('.nous-post__back')).toHaveAttribute('href', /\/nous-signal\/$/);
        expect(await page.locator('.nous-post .toc [data-toc-link]').count()).toBeGreaterThan(0);
        await expect(page.locator('.nous-post__content a:has-text("Back To Nous")')).toHaveCount(0);
        await expect(page.locator('.nous-pn[rel="prev"]')).toBeVisible();

        await page.locator('.nous-post__back').click();
        await expect(page).toHaveURL(/\/nous-signal\/$/);
        expect(errors).toEqual([]);
    });

    test('404 renders the Not Found partial with the compact footer', async ({ page }) => {
        const response = await page.goto('/this-page-does-not-exist/');

        expect(response.status()).toBe(404);
        await expect(page.locator('h1')).toContainText('This page');
        await expect(page.locator('.site-content .wp-block-search')).toBeVisible();
        await expect(page.locator('footer.site-footer--footer-compact')).toHaveCount(1);

        // A linked panel is clickable as a whole, not just its heading text.
        const panel = page.locator('.is-style-panel', { has: page.locator('a[href="/projects/"]') });
        await panel.click({ position: { x: 20, y: 140 } });
        await expect(page).toHaveURL(/\/projects\/$/);
    });

    test('search lists highlighted results and filters by type', async ({ page }) => {
        const response = await page.goto('/?s=nvidia');

        expect(response.status()).toBe(200);
        await expect(page.locator('.search-hero__title')).toContainText('nvidia');
        expect(await page.locator('.search-row').count()).toBeGreaterThan(0);
        await expect(page.locator('.search-row__excerpt strong').first()).toBeVisible();

        await page.locator('.search-pill', { hasText: 'Nous Signal' }).click();
        await expect(page).toHaveURL(/post_type=post/);
        await expect(page.locator('.search-pill[aria-current]')).toContainText('Nous Signal');
        await expect(page.locator('.search-row__type').filter({ hasNotText: 'Nous Signal' })).toHaveCount(0);
    });

    test('search ignores unsearchable types and shows a way forward when empty', async ({ page }) => {
        await page.goto('/?s=nvidia&post_type=product');
        expect(await page.locator('.search-row').count()).toBeGreaterThan(0);

        await page.goto('/?s=zzzqqqxyz');
        await expect(page.locator('.search-empty')).toBeVisible();
        await expect(page.locator('.search-toolbar')).toHaveCount(0);
        await expect(page.locator('.search-paths .is-style-panel')).toHaveCount(3);
    });

    test('on a phone the contents card is a sticky bar that opens and jumps', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await page.emulateMedia({ reducedMotion: 'reduce' });
        await page.goto('/framework/');

        const toc = page.locator('.toc');
        const toggle = page.locator('.toc__toggle');
        await expect(toggle).toBeVisible();
        await expect(toggle).toContainText('01 / 07');
        await expect(page.locator('.toc__panel')).toBeHidden();

        await page.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight / 2));
        await expect.poll(() => toc.evaluate((el) => Math.round(el.getBoundingClientRect().top)))
            .toBe(await page.evaluate(() => parseInt(getComputedStyle(document.documentElement).getPropertyValue('--header-height'), 10)));

        await toggle.click();
        await expect(toggle).toHaveAttribute('aria-expanded', 'true');
        await page.locator('.toc__panel a[href="#security"]').click();
        await expect(page.locator('.toc__panel')).toBeHidden();
        await expect(toggle).toContainText('Security');
    });

    test('on a desktop the contents card shows in full, without the bar toggle', async ({ page }) => {
        await page.setViewportSize({ width: 1440, height: 900 });
        await page.goto('/framework/');

        await expect(page.locator('.toc__toggle')).toBeHidden();
        await expect(page.locator('.toc [data-toc-link]').first()).toBeVisible();
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
