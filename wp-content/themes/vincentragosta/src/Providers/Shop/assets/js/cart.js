/**
 * Shop Cart — localStorage-backed cart with slide-out drawer.
 *
 * Scoped to shop pages only (enqueued conditionally by ShopProvider).
 * Listens for 'shop:add-to-cart' events from the products block view.js.
 */

import { createModal } from '../../../Theme/assets/js/modal';

const STORAGE_KEY = 'vincentragosta_cart';
const CART_TIMESTAMP_KEY = 'vincentragosta_cart_updated';
const INTL_MODE_KEY = 'vincentragosta_international';
const EMAIL_KEY = 'vincentragosta_buyer_email';
const CART_TTL = 24 * 60 * 60 * 1000; // 24 hours in ms
const AGE_VERIFIED_KEY = 'vincentragosta_age_verified';

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
    async show() {
        const config = window.shopConfig || {};
        const message = config.ageGateMessage || 'You must be 18 or older to view this content.';

        const result = await createModal({
            title: 'Age Verification Required',
            message,
            buttons: [
                { label: 'I am 18 or older', value: 'confirm', primary: true },
                { label: 'Continue without mature content', value: 'decline' },
            ],
            ariaLabel: 'Age verification',
            className: 'modal--age-gate',
        });

        if (result === 'confirm') {
            AgeGate.verify();
            return true;
        }

        // Declined or dismissed — redirect to shop without the mature filter
        window.location.href = (window.shopConfig || {}).shopUrl || '/shop/';
        return false;
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
// CountryToggle — shipping region selector
// ==========================================================================

const CountryToggle = {
    isInternational() {
        return sessionStorage.getItem(INTL_MODE_KEY) === '1';
    },

    set(international) {
        if (international) {
            sessionStorage.setItem(INTL_MODE_KEY, '1');
        } else {
            sessionStorage.removeItem(INTL_MODE_KEY);
        }
        document.dispatchEvent(new CustomEvent('shop:country-changed', {
            detail: { international },
        }));
    },

    init() {
        const container = document.querySelector('[data-country-toggle]');
        if (!container) return;

        const select = document.createElement('select');
        select.className = 'shop-country-toggle__select';
        select.setAttribute('aria-label', 'Shipping region');
        select.innerHTML = `
            <option value="US">🇺🇸 US — $10 shipping</option>
            <option value="INTL">🌍 International — $25 shipping</option>
        `;

        if (this.isInternational()) {
            select.value = 'INTL';
        }

        select.addEventListener('change', () => {
            this.set(select.value === 'INTL');
        });

        container.appendChild(select);
    },
};

// ==========================================================================
// EmailCapture — modal-based email collection with localStorage persistence
// ==========================================================================

function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

const EmailCapture = {
    getEmail() {
        return localStorage.getItem(EMAIL_KEY) || null;
    },

    setEmail(email) {
        localStorage.setItem(EMAIL_KEY, email.toLowerCase().trim());
    },

    clearEmail() {
        localStorage.removeItem(EMAIL_KEY);
    },

    async prompt() {
        let emailValue = '';

        // Attach listener after modal renders to capture input value
        requestAnimationFrame(() => {
            const input = document.getElementById('modal-email');
            if (input) {
                input.addEventListener('input', (e) => { emailValue = e.target.value; });
                input.focus();
            }
        });

        const result = await createModal({
            title: 'Before You Checkout',
            html: `
                <label class="modal__label" for="modal-email">Email address</label>
                <input type="email" id="modal-email" class="modal__input" placeholder="you@example.com" autocomplete="email" />
                <p class="modal__helper">We use your email to check if your shipping is already covered and to speed up checkout. You only need to enter this once.</p>
            `,
            buttons: [
                { label: 'Continue', value: 'continue', primary: true },
                { label: 'Skip', value: 'skip' },
            ],
            ariaLabel: 'Email for shipping lookup',
            className: 'modal--email-capture',
        });

        if (result === 'continue' && emailValue && isValidEmail(emailValue)) {
            this.setEmail(emailValue);
            return emailValue.toLowerCase().trim();
        }

        if (result === 'skip') {
            return 'skip';
        }

        return null; // Escape / dismiss
    },
};

// ==========================================================================
// ShippingLookup — check shipping status via WP proxy to bot
// ==========================================================================

const ShippingLookup = {
    cache: null,

    async lookup(email) {
        try {
            const response = await fetch(
                `${window.shopConfig.restUrl}shipping-lookup?email=${encodeURIComponent(email)}`,
                { headers: { 'X-WP-Nonce': window.shopConfig.nonce } }
            );
            if (!response.ok) return null;
            this.cache = await response.json();
            return this.cache;
        } catch {
            return null;
        }
    },

    getCached() {
        return this.cache;
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
                    <div class="shop-cart-drawer__shipping-status" data-shipping-status></div>
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

    renderShippingStatus() {
        if (!this.el) return;
        const container = this.el.querySelector('[data-shipping-status]');
        if (!container) return;

        const email = EmailCapture.getEmail();
        const lookup = ShippingLookup.getCached();

        if (!email) {
            container.innerHTML = '';
            return;
        }

        if (lookup?.covered) {
            container.innerHTML = `
                <div class="shop-cart-drawer__shipping-badge shop-cart-drawer__shipping-badge--covered">
                    Shipping covered this ${lookup.international ? 'month' : 'week'}!
                </div>
                <button class="shop-cart-drawer__email-change" data-change-email type="button">${email}</button>
            `;
        } else if (lookup && lookup.countryKnown) {
            const rate = `$${(lookup.rate / 100).toFixed(2)}`;
            container.innerHTML = `
                <div class="shop-cart-drawer__shipping-badge">
                    ${lookup.international ? 'International' : 'US'} shipping: ${rate}
                </div>
                <button class="shop-cart-drawer__email-change" data-change-email type="button">${email}</button>
            `;
        } else if (lookup) {
            container.innerHTML = `
                <div class="shop-cart-drawer__shipping-badge">
                    Shipping: $10 US / $25 International
                </div>
                <button class="shop-cart-drawer__email-change" data-change-email type="button">${email}</button>
            `;
        } else {
            container.innerHTML = `
                <button class="shop-cart-drawer__email-change" data-change-email type="button">${email}</button>
            `;
        }
    },

    bindEvents() {
        if (!this.el) return;

        // Change email
        this.el.addEventListener('click', (e) => {
            if (e.target.closest('[data-change-email]')) {
                EmailCapture.clearEmail();
                ShippingLookup.cache = null;
                this.renderShippingStatus();
            }
        });

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

        // Step 1: Ensure we have an email (from localStorage or modal prompt)
        let email = EmailCapture.getEmail();
        if (!email) {
            const result = await EmailCapture.prompt();
            if (result === null) return; // Dismissed — abort checkout
            if (result !== 'skip') {
                email = result;
            }
        }

        // Step 2: Look up shipping status if we have an email
        let shippingCovered = false;
        let international = CountryToggle.isInternational();
        let countryKnown = true;
        let discordLinked = false;

        if (email) {
            const lookup = await ShippingLookup.lookup(email);
            if (lookup) {
                shippingCovered = lookup.covered;
                international = lookup.international;
                countryKnown = lookup.countryKnown ?? false;
                discordLinked = lookup.known ?? false;
            }
            CartDrawer.renderShippingStatus();
        } else {
            // No email — country is unknown, offer both options
            countryKnown = false;
        }

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
                    international,
                    country_known: countryKnown,
                    email: email || '',
                    shipping_covered: shippingCovered,
                    discord_linked: discordLinked,
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
                    <a class="wp-block-button__link wp-element-button" href="${(window.shopConfig || {}).shopUrl || '/shop/'}">Back to Shop</a>
                </div>
            </div>
        `;
    },
};

// ==========================================================================
// Init
// ==========================================================================

async function initCart() {
    // Country toggle — shipping region selector
    CountryToggle.init();

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
