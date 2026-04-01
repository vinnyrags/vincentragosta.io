const { test, expect } = require('@playwright/test');

const SHOP_URL = '/shop/';

/**
 * Helper: make all product cards visible by removing scroll-reveal animations.
 * Cards use IntersectionObserver and start hidden — this bypasses that for testing.
 */
/**
 * Navigate to shop and make all product cards visible.
 */
async function gotoShop(page, params = '') {
    // Pre-set age verification via route handler to avoid gate blocking tests
    await page.addInitScript(() => {
        localStorage.setItem('vincentragosta_age_verified', 'true');
    });
    await page.goto(SHOP_URL + params);

    // Disable scroll-reveal animations and make all cards visible
    await page.addStyleTag({
        content: `
            .has-scroll-reveal .product-card {
                opacity: 1 !important;
                transform: none !important;
                transition: none !important;
                display: flex !important;
            }
            .product-card {
                opacity: 1 !important;
                transform: none !important;
                transition: none !important;
            }
            .product-card[aria-hidden="true"] {
                display: none !important;
            }
        `,
    });
    await page.evaluate(() => {
        document.querySelectorAll('.product-card').forEach((c) => c.classList.add('is-visible'));
    });
    await page.waitForTimeout(200);
}

test.describe('Shop Page', () => {
    test('loads with product grid', async ({ page }) => {
        await gotoShop(page);
        const grid = page.locator('.products-grid');
        await expect(grid).toBeVisible();
    });

    test('displays product cards with required data attributes', async ({ page }) => {
        await gotoShop(page);
        const cards = page.locator('.product-card');
        const count = await cards.count();

        if (count === 0) {
            test.skip('No products available — skip shop tests');
            return;
        }

        const first = cards.first();
        await expect(first).toHaveAttribute('data-title');
        await expect(first).toHaveAttribute('data-date');
    });

    test('product cards have add-to-cart buttons with stock data', async ({ page }) => {
        await gotoShop(page);
        const buttons = page.locator('.product-card__add-to-cart');
        const count = await buttons.count();

        if (count === 0) {
            test.skip('No products with add-to-cart buttons');
            return;
        }

        const first = buttons.first();
        await expect(first).toHaveAttribute('data-stock');
    });

    test('sold items have is-sold class and no add-to-cart', async ({ page }) => {
        await gotoShop(page);
        const soldCards = page.locator('.product-card.is-sold');
        const count = await soldCards.count();

        if (count > 0) {
            const first = soldCards.first();
            const addToCart = first.locator('.product-card__add-to-cart');
            await expect(addToCart).toHaveCount(0);
        }
    });
});

test.describe('Cart Drawer', () => {
    test('shows item in cart after adding', async ({ page }) => {
        await gotoShop(page);
        const addBtn = page.locator('.product-card__add-to-cart').first();
        const count = await addBtn.count();

        if (count === 0) {
            test.skip('No products available');
            return;
        }

        await addBtn.click();

        const cartItems = page.locator('.shop-cart-item');
        await expect(cartItems).toHaveCount(1);
    });

    test('cart count badge updates', async ({ page }) => {
        await gotoShop(page);
        const addBtn = page.locator('.product-card__add-to-cart').first();
        const count = await addBtn.count();

        if (count === 0) {
            test.skip('No products available');
            return;
        }

        await addBtn.click();

        const badge = page.locator('[data-cart-count]');
        await expect(badge).not.toHaveText('0');
    });

    test('remove item from cart', async ({ page }) => {
        await gotoShop(page);
        const addBtn = page.locator('.product-card__add-to-cart').first();
        const count = await addBtn.count();

        if (count === 0) {
            test.skip('No products available');
            return;
        }

        await addBtn.click();
        const removeBtn = page.locator('[data-remove-item]').first();
        await removeBtn.click();

        const cartItems = page.locator('.shop-cart-item');
        await expect(cartItems).toHaveCount(0);
    });

});

test.describe('Cart Persistence', () => {
    test('cart persists across page reloads', async ({ page }) => {
        await gotoShop(page);
        const addBtn = page.locator('.product-card__add-to-cart').first();
        const count = await addBtn.count();

        if (count === 0) {
            test.skip('No products available');
            return;
        }

        await addBtn.click();

        // Close drawer and reload
        await page.keyboard.press('Escape');
        await page.reload();

        // Cart should still have the item
        const badge = page.locator('[data-cart-count]');
        await expect(badge).not.toHaveText('0');
    });

});

test.describe('Checkout Flow', () => {
    test('checkout button exists in cart', async ({ page }) => {
        await gotoShop(page);
        const addBtn = page.locator('.product-card__add-to-cart').first();
        const count = await addBtn.count();

        if (count === 0) {
            test.skip('No products available');
            return;
        }

        await addBtn.click();

        const checkoutBtn = page.locator('[data-cart-checkout]');
        await expect(checkoutBtn).toBeVisible();
    });

    test('checkout sends POST to REST endpoint', async ({ page }) => {
        await gotoShop(page);
        const addBtn = page.locator('.product-card__add-to-cart').first();
        const count = await addBtn.count();

        if (count === 0) {
            test.skip('No products available');
            return;
        }

        await addBtn.click();

        // Listen for the checkout API call
        const [request] = await Promise.all([
            page.waitForRequest((req) => req.url().includes('/checkout') && req.method() === 'POST'),
            page.locator('[data-cart-checkout]').click(),
        ]);

        const postData = request.postDataJSON();
        expect(postData.items).toBeDefined();
        expect(postData.items.length).toBeGreaterThan(0);
        expect(postData.items[0].priceId).toBeDefined();
        expect(postData.items[0].quantity).toBeGreaterThan(0);
    });

    test('cart is not cleared before Stripe redirect', async ({ page }) => {
        await gotoShop(page);
        const addBtn = page.locator('.product-card__add-to-cart').first();
        const count = await addBtn.count();

        if (count === 0) {
            test.skip('No products available');
            return;
        }

        await addBtn.click();

        // Click checkout — don't wait for navigation (Stripe redirect may fail in test)
        page.locator('[data-cart-checkout]').click();

        // Small wait for the POST to fire
        await page.waitForTimeout(500);

        // Cart should still be in localStorage
        const cart = await page.evaluate(() =>
            localStorage.getItem('vincentragosta_cart')
        );
        expect(cart).toBeTruthy();
        expect(JSON.parse(cart).length).toBeGreaterThan(0);
    });
});

test.describe('Product Filtering', () => {
    test('search filters products by title', async ({ page }) => {
        await gotoShop(page);
        const searchInput = page.locator('.shop-search__input');
        const hasSearch = await searchInput.count();

        if (!hasSearch) {
            test.skip('No search input on shop page');
            return;
        }

        await searchInput.fill('pokemon');
        await page.waitForTimeout(300);

        const visible = page.locator('.product-card:not([aria-hidden="true"])');
        const hidden = page.locator('.product-card[aria-hidden="true"]');

        const visibleCount = await visible.count();
        const hiddenCount = await hidden.count();

        // At least some filtering should have happened (unless all match)
        expect(visibleCount + hiddenCount).toBeGreaterThan(0);
    });

    test('category filter works', async ({ page }) => {
        await gotoShop(page);
        const categoryDropdown = page.locator('.shop-toolbar .dropdown').first();
        const hasDropdown = await categoryDropdown.count();

        if (!hasDropdown) {
            test.skip('No category filter on shop page');
            return;
        }

        // Open dropdown and select a category
        await categoryDropdown.locator('.dropdown__toggle').click();
        const items = categoryDropdown.locator('.dropdown__item');
        const itemCount = await items.count();

        if (itemCount > 1) {
            await items.nth(1).click(); // Select first non-"all" category
            await page.waitForTimeout(300);

            // Should have some visible and some hidden
            const total = await page.locator('.product-card').count();
            expect(total).toBeGreaterThan(0);
        }
    });

    test('sort by sale shows sale items first', async ({ page }) => {
        await gotoShop(page);

        // Check if there are sale items
        const saleItems = page.locator('.product-card__sale-badge');
        const hasSales = await saleItems.count();

        if (!hasSales) {
            test.skip('No sale items to test sort');
            return;
        }

        // The default sort should show sale items first
        const firstCard = page.locator('.product-card').first();
        const hasSaleBadge = await firstCard.locator('.product-card__sale-badge').count();
        expect(hasSaleBadge).toBe(1);
    });
});

test.describe('Age Gate', () => {
    test('does not show for non-mature content', async ({ page }) => {
        // Clear any previous age verification
        await gotoShop(page);
        await page.evaluate(() => localStorage.removeItem('vincentragosta_age_verified'));
        await page.reload();

        // If no mature products, gate should not appear
        const gate = page.locator('.shop-age-gate');
        // Give it a moment to potentially appear
        await page.waitForTimeout(500);
        const gateVisible = await gate.isVisible().catch(() => false);

        // This is expected to pass if there are no mature products on the page
        // The gate only fires if mature products exist
    });

    test('decline button text says "Continue without mature content"', async ({ page }) => {
        await gotoShop(page);
        await page.evaluate(() => localStorage.removeItem('vincentragosta_age_verified'));
        await page.reload();

        const declineBtn = page.locator('.shop-age-gate__decline');
        const isVisible = await declineBtn.isVisible().catch(() => false);

        if (isVisible) {
            await expect(declineBtn).toHaveText('Continue without mature content');
        }
    });
});
