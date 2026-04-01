/**
 * Express server for webhook endpoints.
 *
 * Routes:
 *   POST /webhooks/stripe    — Stripe checkout events
 *   POST /webhooks/twitch    — Twitch EventSub events
 *   POST /alerts/products    — New product alerts (from sync scripts)
 *   GET  /battle/checkout/:id     — Direct checkout for pack battle buy-in (no shipping)
 *   GET  /livestream/shipping/:id — $10 shipping for all livestream buyers (including battle winners)
 *   GET  /health                  — Health check
 */

import express from 'express';
import Stripe from 'stripe';
import config from './config.js';
import { battles, cardListings } from './db.js';
import { handleCheckoutCompleted } from './webhooks/stripe.js';
import { handleTwitchWebhook } from './webhooks/twitch.js';
import { alertNewProducts } from './alerts/products.js';

const app = express();

// =========================================================================
// Stripe webhook — needs raw body for signature verification
// =========================================================================

app.post('/webhooks/stripe', express.raw({ type: 'application/json' }), async (req, res) => {
    let event;

    if (config.STRIPE_WEBHOOK_SECRET) {
        const stripe = new Stripe(config.STRIPE_SECRET_KEY);
        try {
            event = stripe.webhooks.constructEvent(
                req.body,
                req.headers['stripe-signature'],
                config.STRIPE_WEBHOOK_SECRET
            );
        } catch (e) {
            console.error('Stripe signature verification failed:', e.message);
            return res.status(400).send('Invalid signature');
        }
    } else {
        event = JSON.parse(req.body);
    }

    try {
        switch (event.type) {
            case 'checkout.session.completed':
                await handleCheckoutCompleted(event.data.object);
                break;
            default:
                console.log('Unhandled Stripe event:', event.type);
        }
    } catch (e) {
        console.error('Error handling Stripe event:', e.message);
    }

    res.sendStatus(200);
});

// =========================================================================
// Twitch webhook — needs raw body for signature verification
// =========================================================================

app.post('/webhooks/twitch', express.json({
    verify: (req, res, buf) => { req.rawBody = buf.toString(); },
}), handleTwitchWebhook);

// =========================================================================
// Product alerts — triggered after make sync-products
// =========================================================================

app.post('/alerts/products', express.json(), async (req, res) => {
    const { products, secret } = req.body;

    // Simple shared secret for internal calls
    const expectedSecret = process.env.PRODUCT_ALERT_SECRET || 'itzenzo-sync';

    if (secret !== expectedSecret) {
        return res.status(403).send('Invalid secret');
    }

    if (!Array.isArray(products) || !products.length) {
        return res.status(400).send('No products provided');
    }

    try {
        await alertNewProducts(products);
        res.json({ ok: true, alerted: products.length });
    } catch (e) {
        console.error('Product alert error:', e.message);
        res.status(500).json({ error: e.message });
    }
});

// =========================================================================
// Pack battle direct checkout — creates a Stripe session and redirects
// =========================================================================

app.get('/battle/checkout/:id', async (req, res) => {
    const battle = battles.getActiveBattle.get();

    if (!battle || !battle.stripe_price_id) {
        return res.status(404).send('No active battle or no product linked.');
    }

    try {
        const stripe = new Stripe(config.STRIPE_SECRET_KEY);
        const session = await stripe.checkout.sessions.create({
            mode: 'payment',
            line_items: [{ price: battle.stripe_price_id, quantity: 1 }],
            success_url: `${config.SHOP_URL}?thanks=1`,
            cancel_url: config.SHOP_URL,
            metadata: {
                battle_id: String(battle.id),
                source: 'pack-battle',
            },
            custom_fields: [
                {
                    key: 'discord_username',
                    label: { type: 'custom', custom: 'Discord username for role upgrades (optional)' },
                    type: 'text',
                    optional: true,
                },
            ],
            // No shipping — only the winner receives cards
        });

        res.redirect(303, session.url);
    } catch (e) {
        console.error('Battle checkout error:', e.message);
        res.status(500).send('Checkout failed. Try again or purchase from the shop directly.');
    }
});

// =========================================================================
// Battle winner shipping — $0 checkout to collect winner's address
// =========================================================================
// Livestream shipping — $10 flat rate for all items from tonight's stream
// =========================================================================

app.get('/livestream/shipping/:sessionId', async (req, res) => {
    const email = req.query.email;

    try {
        const stripe = new Stripe(config.STRIPE_SECRET_KEY);
        const params = {
            mode: 'payment',
            line_items: [
                {
                    price_data: {
                        currency: 'usd',
                        product_data: {
                            name: 'Livestream Shipping',
                            description: 'Flat rate shipping for all items and winnings from tonight\'s stream.',
                        },
                        unit_amount: 1000, // $10
                    },
                    quantity: 1,
                },
            ],
            success_url: `${config.SHOP_URL}?shipping_paid=1`,
            cancel_url: config.SHOP_URL,
            metadata: {
                livestream_session_id: req.params.sessionId,
                source: 'livestream-shipping',
            },
            shipping_address_collection: { allowed_countries: ['US'] },
        };

        // Prefill email if available
        if (email) {
            params.customer_email = email;
            params.metadata.customer_email = email;
        }

        const session = await stripe.checkout.sessions.create(params);
        res.redirect(303, session.url);
    } catch (e) {
        console.error('Livestream shipping checkout error:', e.message);
        res.status(500).send('Could not create shipping form. Contact a mod.');
    }
});

// =========================================================================
// Card shop checkout — creates a Stripe session for individual card sales
// =========================================================================

app.get('/card-shop/checkout/:listingId', async (req, res) => {
    const listing = cardListings.getById.get(Number(req.params.listingId));

    if (!listing || (listing.status !== 'active' && listing.status !== 'reserved')) {
        return res.status(404).send('This card is no longer available.');
    }

    try {
        const stripe = new Stripe(config.STRIPE_SECRET_KEY);
        const session = await stripe.checkout.sessions.create({
            mode: 'payment',
            line_items: [
                {
                    price_data: {
                        currency: 'usd',
                        product_data: { name: listing.card_name },
                        unit_amount: listing.price,
                    },
                    quantity: 1,
                },
                {
                    price_data: {
                        currency: 'usd',
                        product_data: {
                            name: 'Shipping',
                            description: 'Card shipping (USPS)',
                        },
                        unit_amount: config.CARD_SHIPPING_AMOUNT,
                    },
                    quantity: 1,
                },
            ],
            success_url: `${config.SHOP_URL}?thanks=1`,
            cancel_url: config.SHOP_URL,
            metadata: {
                card_listing_id: String(listing.id),
                card_name: listing.card_name,
                source: 'card-sale',
                reserved_for: listing.buyer_discord_id || '',
            },
            shipping_address_collection: { allowed_countries: ['US'] },
            custom_fields: [
                {
                    key: 'discord_username',
                    label: { type: 'custom', custom: 'Discord username for role upgrades (optional)' },
                    type: 'text',
                    optional: true,
                },
            ],
        });

        cardListings.setStripeSessionId.run(session.id, listing.id);
        res.redirect(303, session.url);
    } catch (e) {
        console.error('Card shop checkout error:', e.message);
        res.status(500).send('Checkout failed. Try again or contact a mod.');
    }
});

// =========================================================================
// Ad-hoc shipping checkout — creates a Stripe session for any amount
// =========================================================================

app.get('/shipping/checkout', async (req, res) => {
    const amountCents = parseInt(req.query.amount, 10);
    const reason = req.query.reason || 'Shipping';

    if (!amountCents || amountCents <= 0) {
        return res.status(400).send('Invalid shipping amount.');
    }

    try {
        const stripe = new Stripe(config.STRIPE_SECRET_KEY);
        const session = await stripe.checkout.sessions.create({
            mode: 'payment',
            line_items: [
                {
                    price_data: {
                        currency: 'usd',
                        product_data: {
                            name: reason,
                            description: `Shipping — $${(amountCents / 100).toFixed(2)}`,
                        },
                        unit_amount: amountCents,
                    },
                    quantity: 1,
                },
            ],
            success_url: `${config.SHOP_URL}?shipping_paid=1`,
            cancel_url: config.SHOP_URL,
            metadata: {
                source: 'ad-hoc-shipping',
                discord_user_id: req.query.user || '',
                reason,
            },
            shipping_address_collection: { allowed_countries: ['US'] },
            custom_fields: [
                {
                    key: 'discord_username',
                    label: { type: 'custom', custom: 'Discord username (optional)' },
                    type: 'text',
                    optional: true,
                },
            ],
        });

        res.redirect(303, session.url);
    } catch (e) {
        console.error('Shipping checkout error:', e.message);
        res.status(500).send('Could not create shipping form. Contact a mod.');
    }
});

// =========================================================================
// Health check
// =========================================================================

app.get('/health', (req, res) => {
    res.json({ status: 'ok', uptime: process.uptime() });
});

/**
 * Start the Express server.
 */
function startServer() {
    app.listen(config.PORT, () => {
        console.log(`Webhook server listening on port ${config.PORT}`);
    });
}

export { app, startServer };
