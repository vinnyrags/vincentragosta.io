/**
 * Shop Cart — localStorage-backed cart with slide-out drawer.
 *
 * Scoped to shop pages only (enqueued conditionally by ShopProvider).
 * Listens for 'shop:add-to-cart' events from the products block view.js.
 */

const STORAGE_KEY = 'vincentragosta_cart';
const CART_TIMESTAMP_KEY = 'vincentragosta_cart_updated';
const LIVE_MODE_KEY = 'vincentragosta_live_mode';
const CART_TTL = 24 * 60 * 60 * 1000; // 24 hours in ms
const AGE_VERIFIED_KEY = 'vincentragosta_age_verified';

// Detect ?live=1 in URL and persist to sessionStorage
(() => {
    const params = new URLSearchParams(window.location.search);
    if (params.get('live') === '1') {
        sessionStorage.setItem(LIVE_MODE_KEY, '1');
    }
})();

// ==========================================================================
// AgeGate — modal that fires before mature content is visible
// ==========================================================================

const AgeGate = {
    isVerified() {
        return localStorage.getItem(AGE_VERIFIED_KEY) === 'true';
    },

    verify() {
        localStorage.setItem(AGE_VERIFIED_KEY, 'true');
    },

    /**
     * Check if the age gate should fire. Returns true if mature content
     * is present and the user hasn't verified yet.
     */
    shouldBlock() {
        const config = window.shopConfig || {};
        if (!config.ageGateEnabled) return false;
        if (this.isVerified()) return false;

        // Check if we're filtering to mature category via URL
        const urlCategory = new URLSearchParams(window.location.search).get('category');
        if (urlCategory === (config.matureCategorySlug || 'mature')) return true;

        // Check if any mature products exist in the grid
        const matureSlug = config.matureCategorySlug || 'mature';
        const matureCards = document.querySelectorAll(
            `.product-card[data-category~="${matureSlug}"]`
        );
        return matureCards.length > 0;
    },

    /**
     * Show the age gate modal. Returns a promise that resolves
     * to true (verified) or false (declined).
     */
    show() {
        return new Promise((resolve) => {
            const config = window.shopConfig || {};
            const message = config.ageGateMessage || 'You must be 18 or older to view this content.';

            const overlay = document.createElement('div');
            overlay.className = 'shop-age-gate';
            overlay.setAttribute('role', 'dialog');
            overlay.setAttribute('aria-modal', 'true');
            overlay.setAttribute('aria-label', 'Age verification');
            overlay.innerHTML = `
                <div class="shop-age-gate__panel">
                    <h2 class="shop-age-gate__title">Age Verification Required</h2>
                    <p class="shop-age-gate__message">${message}</p>
                    <div class="shop-age-gate__actions">
                        <button class="shop-age-gate__confirm" data-age-confirm>I am 18 or older</button>
                        <button class="shop-age-gate__decline" data-age-decline>Continue without mature content</button>
                    </div>
                </div>
            `;

            document.body.appendChild(overlay);

            // Focus the confirm button
            const confirmBtn = overlay.querySelector('[data-age-confirm]');
            confirmBtn.focus();

            overlay.addEventListener('click', (e) => {
                if (e.target.closest('[data-age-confirm]')) {
                    AgeGate.verify();
                    overlay.remove();
                    resolve(true);
                }
                if (e.target.closest('[data-age-decline]')) {
                    overlay.remove();
                    // Redirect to shop without the mature filter
                    window.location.href = '/shop/';
                    resolve(false);
                }
            });

            // Trap focus inside the modal
            overlay.addEventListener('keydown', (e) => {
                if (e.key === 'Tab') {
                    const focusable = overlay.querySelectorAll('button');
                    const first = focusable[0];
                    const last = focusable[focusable.length - 1];

                    if (e.shiftKey && document.activeElement === first) {
                        e.preventDefault();
                        last.focus();
                    } else if (!e.shiftKey && document.activeElement === last) {
                        e.preventDefault();
                        first.focus();
                    }
                }
            });
        });
    },

    /**
     * Hide mature products in the DOM until verified.
     */
    hideMatureProducts() {
        const matureSlug = (window.shopConfig || {}).matureCategorySlug || 'mature';
        document.querySelectorAll(`.product-card[data-category~="${matureSlug}"]`).forEach((card) => {
            card.setAttribute('data-mature-hidden', '');
            card.setAttribute('aria-hidden', 'true');
            card.style.display = 'none';
        });
    },

    /**
     * Reveal mature products after verification.
     */
    showMatureProducts() {
        document.querySelectorAll('[data-mature-hidden]').forEach((card) => {
            card.removeAttribute('data-mature-hidden');
            card.removeAttribute('aria-hidden');
            card.style.display = '';
        });
    },
};

// ==========================================================================
// CartStore — localStorage CRUD
// ==========================================================================

const CartStore = {
    getItems() {
        try {
            const lastUpdated = parseInt(localStorage.getItem(CART_TIMESTAMP_KEY) || '0', 10);
            if (lastUpdated && Date.now() - lastUpdated > CART_TTL) {
                this.clear();
                return [];
            }
            return JSON.parse(localStorage.getItem(STORAGE_KEY)) || [];
        } catch {
            return [];
        }
    },

    save(items) {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
        localStorage.setItem(CART_TIMESTAMP_KEY, String(Date.now()));
        document.dispatchEvent(new CustomEvent('shop:cart-updated', {
            detail: { items, count: this.getCount(items) },
        }));
    },

    addItem(item) {
        const items = this.getItems();
        const existing = items.find((i) => i.priceId === item.priceId);

        if (existing) {
            existing.quantity = Math.min(existing.quantity + item.quantity, item.stock || Infinity);
        } else {
            items.push({ ...item });
        }

        this.save(items);
    },

    removeItem(priceId) {
        const items = this.getItems().filter((i) => i.priceId !== priceId);
        this.save(items);
    },

    updateQuantity(priceId, quantity) {
        const items = this.getItems();
        const item = items.find((i) => i.priceId === priceId);

        if (item) {
            item.quantity = Math.min(Math.max(1, quantity), item.stock || Infinity);
            this.save(items);
        }
    },

    clear() {
        localStorage.removeItem(STORAGE_KEY);
        localStorage.removeItem(CART_TIMESTAMP_KEY);
        document.dispatchEvent(new CustomEvent('shop:cart-updated', {
            detail: { items: [], count: 0 },
        }));
    },

    getCount(items) {
        const list = items || this.getItems();
        return list.reduce((sum, item) => sum + item.quantity, 0);
    },
};

// ==========================================================================
// CartDrawer — slide-out panel
// ==========================================================================

const CartDrawer = {
    el: null,

    create() {
        const drawer = document.createElement('div');
        drawer.className = 'shop-cart-drawer';
        drawer.setAttribute('aria-hidden', 'true');
        drawer.innerHTML = `
            <div class="shop-cart-drawer__backdrop" data-cart-close></div>
            <div class="shop-cart-drawer__panel" role="dialog" aria-label="Shopping cart">
                <div class="shop-cart-drawer__header">
                    <h2 class="shop-cart-drawer__title">Cart</h2>
                    <button class="shop-cart-drawer__close" data-cart-close aria-label="Close cart">&times;</button>
                </div>
                <div class="shop-cart-drawer__items"></div>
                <div class="shop-cart-drawer__footer">
                    <button class="shop-cart-drawer__checkout" data-cart-checkout disabled>Checkout</button>
                </div>
            </div>
        `;

        document.body.appendChild(drawer);
        this.el = drawer;
        this.bindEvents();
        return drawer;
    },

    open() {
        if (!this.el) this.create();
        this.render();
        this.el.setAttribute('aria-hidden', 'false');
        this.el.classList.add('is-open');
    },

    close() {
        if (!this.el) return;
        this.el.setAttribute('aria-hidden', 'true');
        this.el.classList.remove('is-open');
    },

    render() {
        if (!this.el) return;

        const items = CartStore.getItems();
        const container = this.el.querySelector('.shop-cart-drawer__items');
        const checkoutBtn = this.el.querySelector('[data-cart-checkout]');

        if (!items.length) {
            container.innerHTML = '<p class="shop-cart-drawer__empty">Your cart is empty.</p>';
            checkoutBtn.disabled = true;
            return;
        }

        container.innerHTML = items.map((item) => `
            <div class="shop-cart-item" data-price-id="${item.priceId}">
                ${item.image ? `<img class="shop-cart-item__image" src="${item.image}" alt="" width="60" height="80" />` : ''}
                <div class="shop-cart-item__details">
                    <span class="shop-cart-item__title">${item.title}</span>
                    <span class="shop-cart-item__price">${item.price}</span>
                </div>
                <div class="shop-cart-item__actions">
                    ${item.stock > 1 ? `
                        <input type="number" class="shop-cart-item__quantity" aria-label="Quantity"
                            data-quantity-input min="1" max="${item.stock}" value="${item.quantity}" />
                    ` : ''}
                    <button class="shop-cart-item__remove" data-remove-item aria-label="Remove ${item.title}">&times;</button>
                </div>
            </div>
        `).join('');

        checkoutBtn.disabled = false;
    },

    bindEvents() {
        if (!this.el) return;

        // Close buttons and backdrop
        this.el.addEventListener('click', (e) => {
            if (e.target.closest('[data-cart-close]')) {
                this.close();
            }
        });

        // Remove item
        this.el.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-remove-item]');
            if (!btn) return;
            const item = btn.closest('.shop-cart-item');
            if (item) {
                CartStore.removeItem(item.dataset.priceId);
                this.render();
            }
        });

        // Quantity change
        this.el.addEventListener('change', (e) => {
            const input = e.target.closest('[data-quantity-input]');
            if (!input) return;
            const item = input.closest('.shop-cart-item');
            if (item) {
                CartStore.updateQuantity(item.dataset.priceId, parseInt(input.value, 10));
                this.render();
            }
        });

        // Checkout
        this.el.addEventListener('click', (e) => {
            if (e.target.closest('[data-cart-checkout]')) {
                CartCheckout.submit();
            }
        });

        // Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.el.classList.contains('is-open')) {
                this.close();
            }
        });
    },
};

// ==========================================================================
// CartCheckout — POST to REST endpoint + redirect
// ==========================================================================

const CartCheckout = {
    async submit() {
        const items = CartStore.getItems();
        if (!items.length) return;

        const checkoutBtn = document.querySelector('[data-cart-checkout]');
        if (checkoutBtn) {
            checkoutBtn.disabled = true;
            checkoutBtn.textContent = 'Processing...';
        }

        try {
            const response = await fetch(`${window.shopConfig.restUrl}checkout`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.shopConfig.nonce,
                },
                body: JSON.stringify({
                    items: items.map((i) => ({
                        priceId: i.priceId,
                        quantity: i.quantity,
                    })),
                    live: sessionStorage.getItem(LIVE_MODE_KEY) === '1',
                }),
            });

            const data = await response.json();

            if (!response.ok) {
                const message = data.message || 'Checkout failed. Please try again.';
                alert(message);
                if (checkoutBtn) {
                    checkoutBtn.disabled = false;
                    checkoutBtn.textContent = 'Checkout';
                }
                return;
            }

            // Redirect to Stripe — cart is cleared on the thank-you page after successful payment
            window.location.href = data.url;
        } catch {
            alert('Something went wrong. Please try again.');
            if (checkoutBtn) {
                checkoutBtn.disabled = false;
                checkoutBtn.textContent = 'Checkout';
            }
        }
    },
};

// ==========================================================================
// ThankYouPage — clear cart and show confirmation
// ==========================================================================

const ThankYouPage = {
    init() {
        const el = document.querySelector('[data-thank-you]');
        if (!el) return;

        CartStore.clear();
        el.innerHTML = `
            <h2>Thank you for your order!</h2>
            <p class="is-style-muted">Your payment was successful. You'll receive a confirmation email from Stripe shortly.</p>
            <div class="wp-block-buttons">
                <div class="wp-block-button">
                    <a class="wp-block-button__link wp-element-button" href="/shop/">Back to Shop</a>
                </div>
            </div>
        `;
    },
};

// ==========================================================================
// Init
// ==========================================================================

async function initCart() {
    // Age gate — hide mature products and prompt if needed
    if (AgeGate.shouldBlock()) {
        AgeGate.hideMatureProducts();
        const verified = await AgeGate.show();
        if (verified) {
            AgeGate.showMatureProducts();
        }
    }

    // Listen for add-to-cart events from the products block
    document.addEventListener('shop:add-to-cart', (e) => {
        CartStore.addItem(e.detail);
        CartDrawer.open();
    });

    // Cart toggle button in the toolbar
    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-cart-toggle]')) {
            CartDrawer.open();
        }
    });

    // Update cart count badges on cart change
    document.addEventListener('shop:cart-updated', (e) => {
        document.querySelectorAll('[data-cart-count]').forEach((el) => {
            el.textContent = e.detail.count;
        });
    });

    // Initialize count on load
    document.querySelectorAll('[data-cart-count]').forEach((el) => {
        el.textContent = CartStore.getCount();
    });

    // Page-specific initializations
    ThankYouPage.init();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCart);
} else {
    initCart();
}
