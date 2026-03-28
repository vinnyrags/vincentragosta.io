/**
 * Shop Cart — localStorage-backed cart with slide-out drawer.
 *
 * Scoped to shop pages only (enqueued conditionally by ShopProvider).
 * Listens for 'shop:add-to-cart' events from the products block view.js.
 */

const STORAGE_KEY = 'vincentragosta_cart';

// ==========================================================================
// CartStore — localStorage CRUD
// ==========================================================================

const CartStore = {
    getItems() {
        try {
            return JSON.parse(localStorage.getItem(STORAGE_KEY)) || [];
        } catch {
            return [];
        }
    },

    save(items) {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
        document.dispatchEvent(new CustomEvent('shop:cart-updated', {
            detail: { items, count: this.getCount(items) },
        }));
    },

    addItem(item) {
        const items = this.getItems();
        const existing = items.find((i) => i.priceId === item.priceId);

        if (existing) {
            existing.quantity += item.quantity;
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
            item.quantity = Math.max(1, quantity);
            this.save(items);
        }
    },

    clear() {
        localStorage.removeItem(STORAGE_KEY);
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
                    <select class="shop-cart-item__quantity" aria-label="Quantity" data-quantity-select>
                        ${[1, 2, 3, 4, 5].map((n) =>
                            `<option value="${n}" ${n === item.quantity ? 'selected' : ''}>${n}</option>`
                        ).join('')}
                    </select>
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
            const select = e.target.closest('[data-quantity-select]');
            if (!select) return;
            const item = select.closest('.shop-cart-item');
            if (item) {
                CartStore.updateQuantity(item.dataset.priceId, parseInt(select.value, 10));
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

            // Clear cart and redirect to Stripe
            CartStore.clear();
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
            <h2 class="shop-thank-you__title">Thank you for your order!</h2>
            <p class="shop-thank-you__message">Your payment was successful. You'll receive a confirmation email from Stripe shortly.</p>
            <a href="/shop/" class="shop-thank-you__continue">Back to Shop</a>
        `;
    },
};

// ==========================================================================
// Init
// ==========================================================================

function initCart() {
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
