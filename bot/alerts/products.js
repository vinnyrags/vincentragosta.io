/**
 * Product Alerts
 *
 * Posts new product notifications to category-specific channels:
 * - Pokemon products → #pokemon
 * - Anime products → #anime
 * - Mature products → #mature-drops
 *
 * Triggered via HTTP endpoint after `make sync-products`.
 */

import config from '../config.js';
import { sendEmbed } from '../discord.js';

/**
 * Map a product category to its Discord channel key.
 */
function getCategoryChannel(category) {
    const map = {
        pokemon: 'POKEMON',
        anime: 'ANIME',
        mature: 'MATURE_DROPS',
    };
    return map[category?.toLowerCase()] || null;
}

/**
 * Post a new product alert.
 */
async function alertNewProduct(product) {
    const { name, category, price, image_url, shop_url } = product;
    const channelKey = getCategoryChannel(category);

    if (!channelKey) {
        console.log(`No channel mapping for category: ${category}`);
        return;
    }

    const description = [
        `**${name}**`,
        price ? `💰 $${(price / 100).toFixed(2)}` : null,
        shop_url ? `\n👉 [Shop Now](${shop_url})` : null,
    ].filter(Boolean).join('\n');

    await sendEmbed(channelKey, {
        title: '🆕 New Drop!',
        description,
        color: channelKey === 'MATURE_DROPS' ? 0xc0392b : 0x2ecc71,
    });

    // Also post to #deals for visibility
    await sendEmbed('DEALS', {
        title: '🆕 New Product Added',
        description: `**${name}** just dropped${category ? ` in ${category}` : ''}!\n\nCheck <#${config.CHANNELS[channelKey]}> for details.`,
        color: 0x2ecc71,
    });

    console.log(`Product alert sent: ${name} → ${channelKey}`);
}

/**
 * Handle bulk product alerts (after sync).
 */
async function alertNewProducts(products) {
    if (!products.length) return;

    // Group by category
    const grouped = {};
    for (const product of products) {
        const cat = product.category || 'other';
        if (!grouped[cat]) grouped[cat] = [];
        grouped[cat].push(product);
    }

    for (const [category, items] of Object.entries(grouped)) {
        const channelKey = getCategoryChannel(category);
        if (!channelKey) continue;

        if (items.length === 1) {
            await alertNewProduct(items[0]);
        } else {
            // Bulk alert
            const list = items.map((p) => `• **${p.name}**${p.price ? ` — $${(p.price / 100).toFixed(2)}` : ''}`).join('\n');

            await sendEmbed(channelKey, {
                title: `🆕 ${items.length} New Drops!`,
                description: list,
                color: channelKey === 'MATURE_DROPS' ? 0xc0392b : 0x2ecc71,
            });
        }
    }
}

export { alertNewProduct, alertNewProducts };
