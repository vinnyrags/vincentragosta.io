/**
 * Product Sync Commands
 *
 * !sync          — Full pipeline: Google Sheets → Stripe → WordPress
 * !sync stripe   — Stripe → WordPress only (skip Sheets)
 */

import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import config from '../config.js';
import { sendToChannel } from '../discord.js';
import { alertNewProducts } from '../alerts/products.js';

const exec = promisify(execFile);
const __dirname = path.dirname(fileURLToPath(import.meta.url));
const PROJECT_ROOT = path.resolve(__dirname, '../..');
const PUSH_SCRIPT = path.join(PROJECT_ROOT, 'scripts/shop/push-products.js');
const PULL_SCRIPT = path.join(PROJECT_ROOT, 'scripts/pull-products.php');
const WP_PATH = path.resolve(PROJECT_ROOT, 'wp');

/**
 * Resolve the Node.js binary — handles NVM environments.
 */
function getNodePath() {
    return process.execPath;
}

/**
 * Run push-products.js (Sheets → Stripe).
 */
async function pushToStripe(clean) {
    const args = [PUSH_SCRIPT];
    if (clean) args.push('--clean');
    const { stdout } = await exec(getNodePath(), args, {
        cwd: PROJECT_ROOT,
        timeout: 120000,
        env: { ...process.env, HOME: process.env.HOME || '/root' },
    });
    return stdout;
}

/**
 * Run pull-products.php (Stripe → WordPress).
 */
async function pullToWordPress() {
    const { stdout } = await exec('wp', [
        'eval-file', PULL_SCRIPT,
        `--path=${WP_PATH}`,
        '--allow-root',
    ], {
        cwd: PROJECT_ROOT,
        timeout: 120000,
        env: {
            ...process.env,
            // Set marker flags for clean + publish
            PUBLISH: '1',
            CLEAN: '1',
        },
    });
    return stdout;
}

/**
 * Parse sync output for a summary line.
 */
function parseSummary(output) {
    const doneMatch = output.match(/Done:\s*(.+)/);
    return doneMatch ? doneMatch[1].trim() : null;
}

/**
 * Parse new products for alerts.
 */
function parseNewProducts(output) {
    const products = [];
    const regex = /Created(?: \(published\))?: (.+?) (?:\(ID \d+\) )?\[(\w+)/g;
    let match;
    while ((match = regex.exec(output)) !== null) {
        products.push({ name: match[1], category: match[2] });
    }
    return products;
}

async function handleSync(message, args) {
    // Akivili only
    if (!message.member.roles.cache.has(config.ROLES.AKIVILI)) {
        return message.reply('Only the owner can sync products.');
    }

    const subcommand = args[0]?.toLowerCase();

    if (subcommand === 'stripe') {
        return syncStripeToWordPress(message);
    }

    return syncFull(message);
}

/**
 * Full sync: Sheets → Stripe → WordPress
 */
async function syncFull(message) {
    await message.channel.send('🔄 **Syncing products:** Sheets → Stripe → WordPress...');

    try {
        // Step 1: Sheets → Stripe
        const pushOutput = await pushToStripe(true);
        const pushSummary = parseSummary(pushOutput);

        await message.channel.send(
            `✅ **Sheets → Stripe** complete${pushSummary ? `: ${pushSummary}` : ''}`
        );

        // Step 2: Stripe → WordPress
        const pullOutput = await pullToWordPress();
        const pullSummary = parseSummary(pullOutput);
        const newProducts = parseNewProducts(pullOutput);

        await message.channel.send(
            `✅ **Stripe → WordPress** complete${pullSummary ? `: ${pullSummary}` : ''}`
        );

        // Post summary to #ops
        const lines = ['📦 **Product Sync Complete**', ''];
        if (pushSummary) lines.push(`**Sheets → Stripe:** ${pushSummary}`);
        if (pullSummary) lines.push(`**Stripe → WordPress:** ${pullSummary}`);
        await sendToChannel('OPS', lines.join('\n'));

        // Send new product alerts
        if (newProducts.length > 0) {
            for (const product of newProducts) {
                await alertNewProducts([{
                    name: product.name,
                    category: product.category,
                    shop_url: config.SHOP_URL,
                }]);
            }
        }
    } catch (e) {
        console.error('Product sync failed:', e.message);
        await message.channel.send(`❌ **Sync failed:** ${e.message}`);
    }
}

/**
 * Stripe → WordPress only
 */
async function syncStripeToWordPress(message) {
    await message.channel.send('🔄 **Syncing products:** Stripe → WordPress...');

    try {
        const pullOutput = await pullToWordPress();
        const pullSummary = parseSummary(pullOutput);
        const newProducts = parseNewProducts(pullOutput);

        await message.channel.send(
            `✅ **Stripe → WordPress** complete${pullSummary ? `: ${pullSummary}` : ''}`
        );

        await sendToChannel('OPS', `📦 **Product Sync Complete**\n\n**Stripe → WordPress:** ${pullSummary || 'done'}`);

        if (newProducts.length > 0) {
            for (const product of newProducts) {
                await alertNewProducts([{
                    name: product.name,
                    category: product.category,
                    shop_url: config.SHOP_URL,
                }]);
            }
        }
    } catch (e) {
        console.error('Stripe → WordPress sync failed:', e.message);
        await message.channel.send(`❌ **Sync failed:** ${e.message}`);
    }
}

export { handleSync };
