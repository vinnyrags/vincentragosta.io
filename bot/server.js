/**
 * Express server for webhook endpoints.
 *
 * Routes:
 *   POST /webhooks/stripe    — Stripe checkout events
 *   POST /webhooks/twitch    — Twitch EventSub events
 *   POST /alerts/products    — New product alerts (from sync scripts)
 *   GET  /health             — Health check
 */

const express = require('express');
const config = require('./config');
const { handleCheckoutCompleted } = require('./webhooks/stripe');
const { handleTwitchWebhook } = require('./webhooks/twitch');
const { alertNewProducts } = require('./alerts/products');

const app = express();

// =========================================================================
// Stripe webhook — needs raw body for signature verification
// =========================================================================

app.post('/webhooks/stripe', express.raw({ type: 'application/json' }), async (req, res) => {
    let event;

    if (config.STRIPE_WEBHOOK_SECRET) {
        const stripe = require('stripe')(config.STRIPE_SECRET_KEY);
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
    const expectedSecret = config.optional?.('PRODUCT_ALERT_SECRET')
        || process.env.PRODUCT_ALERT_SECRET
        || 'itzenzo-sync';

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

module.exports = { app, startServer };
