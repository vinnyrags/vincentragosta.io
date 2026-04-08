/**
 * Card Shop System — Individual card sales via Discord.
 *
 * Commands:
 *   !sell @buyer "Card Name" 25.00 — Reserve a card for a specific buyer (stream sales)
 *   !list "Card Name" 25.00         — List a card for open purchase (passive inventory)
 *   !sold <message_id>              — Manually mark a listing as sold (or reply to listing)
 */

import { EmbedBuilder, ButtonBuilder, ButtonStyle, ActionRowBuilder } from 'discord.js';
import config from '../config.js';
import { cardListings } from '../db.js';
import { client, getMember } from '../discord.js';
import { formatShippingRate, getShippingLabel, hasShippingCoveredByDiscordId } from '../shipping.js';

// In-memory expiry timers: listingId → timeoutId
const expiryTimers = new Map();

/**
 * Format cents as dollars.
 */
function formatPrice(cents) {
    return `$${(cents / 100).toFixed(2)}`;
}

/**
 * Build the checkout URL for a listing.
 */
function checkoutUrl(listingId) {
    return `${config.SHOP_URL.replace(/\/shop$/, '')}/bot/card-shop/checkout/${listingId}`;
}

/**
 * Build an embed for a card listing based on its status.
 */
function buildListingEmbed(listing) {
    const priceLabel = formatPrice(listing.price);
    const shippingNote = `*Shipping: ${formatShippingRate(config.SHIPPING.DOMESTIC)} US / ${formatShippingRate(config.SHIPPING.INTERNATIONAL)} International (waived if already covered this week/month)*`;

    if (listing.status === 'sold') {
        return new EmbedBuilder()
            .setTitle(`🃏 ${listing.card_name}`)
            .setDescription(`~~${priceLabel}~~\n\n**SOLD**`)
            .setColor(0xe74c3c)
            .setFooter({ text: `Listing #${listing.id}` });
    }

    if (listing.status === 'reserved') {
        return new EmbedBuilder()
            .setTitle(`🃏 ${listing.card_name}`)
            .setDescription(
                `**${priceLabel}**\n\n` +
                `🔒 Reserved for <@${listing.buyer_discord_id}> — 15 minutes to checkout\n\n` +
                shippingNote
            )
            .setColor(0xf1c40f)
            .setFooter({ text: `Listing #${listing.id}` });
    }

    if (listing.status === 'expired') {
        return new EmbedBuilder()
            .setTitle(`🃏 ${listing.card_name}`)
            .setDescription(`~~${priceLabel}~~\n\n**EXPIRED** — reservation lapsed`)
            .setColor(0x95a5a6)
            .setFooter({ text: `Listing #${listing.id}` });
    }

    // active / available — uses Discord button instead of raw link
    return new EmbedBuilder()
        .setTitle(`🃏 ${listing.card_name}`)
        .setDescription(
            `**${priceLabel}** — click Buy Now to check out\n\n` +
            shippingNote
        )
        .setColor(0xceff00)
        .setFooter({ text: `Listing #${listing.id}` });
}

/**
 * Fetch and update the listing embed in #card-shop.
 */
async function updateListingEmbed(listing) {
    try {
        const channel = client.channels.cache.get(config.CHANNELS.CARD_SHOP);
        if (!channel || !listing.message_id) return;
        const msg = await channel.messages.fetch(listing.message_id);

        const editPayload = { embeds: [buildListingEmbed(listing)] };

        // Remove button when sold/expired, keep for active/reserved
        if (listing.status === 'sold' || listing.status === 'expired') {
            editPayload.components = [];
        }

        await msg.edit(editPayload);
    } catch (e) {
        console.error('Failed to update card listing embed:', e.message);
    }
}

/**
 * Start a 15-minute expiry timer for a reserved listing.
 * On expiry, relists the card as active and updates the embed.
 */
function startExpiryTimer(listingId) {
    clearExpiryTimer(listingId);

    const timer = setTimeout(async () => {
        expiryTimers.delete(listingId);

        const listing = cardListings.getById.get(listingId);
        if (!listing || listing.status !== 'reserved') return;

        cardListings.markExpired.run(listingId);

        const expired = cardListings.getById.get(listingId);
        await updateListingEmbed(expired);

        // Update the buyer's DM to show expiry
        if (expired.buyer_discord_id && expired.buyer_dm_message_id) {
            try {
                const member = await getMember(expired.buyer_discord_id);
                if (member) {
                    const dm = await member.createDM();
                    const dmMsg = await dm.messages.fetch(expired.buyer_dm_message_id);
                    const embed = new EmbedBuilder()
                        .setTitle('⏰ Reservation Expired')
                        .setDescription(`Your reservation for **${expired.card_name}** has expired.`)
                        .setColor(0x95a5a6);
                    await dmMsg.edit({ embeds: [embed], components: [] });
                }
            } catch (e) {
                console.error(`Failed to update expired DM for listing #${listingId}:`, e.message);
            }
        }

        console.log(`Card listing #${listingId} expired — reservation lapsed`);
    }, config.CARD_RESERVATION_MS);

    expiryTimers.set(listingId, timer);
}

/**
 * Clear an active expiry timer.
 */
function clearExpiryTimer(listingId) {
    const timer = expiryTimers.get(listingId);
    if (timer) {
        clearTimeout(timer);
        expiryTimers.delete(listingId);
    }
}

/**
 * !sell @buyer "Card Name" 25.00
 */
async function handleSell(message, args) {
    if (!message.member.roles.cache.has(config.ROLES.AKIVILI)) {
        return message.reply('Only the server owner can list cards for sale.');
    }

    const buyer = message.mentions.users.first();
    if (!buyer) {
        return message.reply('Usage: `!sell @buyer "Card Name" 25.00`');
    }

    // Parse quoted card name
    const fullText = message.content;
    const nameMatch = fullText.match(/"([^"]+)"/);
    if (!nameMatch) {
        return message.reply('Card name must be in quotes: `!sell @buyer "Card Name" 25.00`');
    }
    const cardName = nameMatch[1];

    // Parse price — last number in the message after the closing quote
    const afterQuote = fullText.slice(fullText.lastIndexOf('"') + 1).trim();
    const priceMatch = afterQuote.match(/([\d]+(?:\.[\d]{1,2})?)/);
    if (!priceMatch) {
        return message.reply('Include a price: `!sell @buyer "Card Name" 25.00`');
    }
    const priceCents = Math.round(parseFloat(priceMatch[1]) * 100);

    if (priceCents <= 0) {
        return message.reply('Price must be greater than zero.');
    }

    // Create reserved listing
    const result = cardListings.create.run(cardName, priceCents, buyer.id, 'reserved');
    const listingId = Number(result.lastInsertRowid);

    // Post embed in #card-shop
    const channel = client.channels.cache.get(config.CHANNELS.CARD_SHOP);
    if (!channel) {
        return message.reply('Card shop channel not found. Check config.');
    }

    const listing = cardListings.getById.get(listingId);
    const embed = buildListingEmbed(listing);
    const msg = await channel.send({ embeds: [embed] });
    cardListings.setMessageId.run(msg.id, listingId);

    // DM the buyer with a Buy Now button (identity captured via interaction handler)
    const buyButton = new ButtonBuilder()
        .setCustomId(`sell-buy-${listingId}`)
        .setLabel('Complete Purchase')
        .setStyle(ButtonStyle.Primary)
        .setEmoji('🛒');
    const buyRow = new ActionRowBuilder().addComponents(buyButton);

    const covered = hasShippingCoveredByDiscordId(buyer.id);
    const shippingNote = covered
        ? '✅ Shipping already covered this period'
        : (() => {
            const { rate, label } = getShippingLabel(buyer.id);
            return `📦 ${label}: ${formatShippingRate(rate)}`;
        })();
    try {
        const member = await getMember(buyer.id);
        if (member) {
            const dm = await member.createDM();
            const dmEmbed = new EmbedBuilder()
                .setTitle(`🃏 Card Reserved for You!`)
                .setDescription(
                    `**${cardName}** — ${formatPrice(priceCents)}\n` +
                    `${shippingNote}\n\n` +
                    `⏰ This reservation expires in 15 minutes.`
                )
                .setColor(0xf1c40f);
            const dmMsg = await dm.send({ embeds: [dmEmbed], components: [buyRow] });
            cardListings.setBuyerDmMessageId.run(dmMsg.id, listingId);
        }
    } catch {
        // DMs disabled — post fallback in channel with button
        await channel.send({ content: `<@${buyer.id}> Your card is reserved!`, components: [buyRow] });
    }

    // Start 15-minute expiry
    startExpiryTimer(listingId);

    if (message.channel.id !== channel.id) {
        await message.channel.send(`✅ Reserved **${cardName}** for <@${buyer.id}> in <#${config.CHANNELS.CARD_SHOP}>.`);
    }
}

/**
 * !list "Card Name" 25.00
 */
async function handleList(message, args) {
    if (!message.member.roles.cache.has(config.ROLES.AKIVILI)) {
        return message.reply('Only the server owner can list cards for sale.');
    }

    // Parse quoted card name
    const fullText = message.content;
    const nameMatch = fullText.match(/"([^"]+)"/);
    if (!nameMatch) {
        return message.reply('Usage: `!list "Card Name" 25.00`');
    }
    const cardName = nameMatch[1];

    // Parse price
    const afterQuote = fullText.slice(fullText.lastIndexOf('"') + 1).trim();
    const priceMatch = afterQuote.match(/([\d]+(?:\.[\d]{1,2})?)/);
    if (!priceMatch) {
        return message.reply('Include a price: `!list "Card Name" 25.00`');
    }
    const priceCents = Math.round(parseFloat(priceMatch[1]) * 100);

    if (priceCents <= 0) {
        return message.reply('Price must be greater than zero.');
    }

    // Create active listing
    const result = cardListings.create.run(cardName, priceCents, null, 'active');
    const listingId = Number(result.lastInsertRowid);

    // Post embed in #card-shop
    const channel = client.channels.cache.get(config.CHANNELS.CARD_SHOP);
    if (!channel) {
        return message.reply('Card shop channel not found. Check config.');
    }

    const listing = cardListings.getById.get(listingId);
    const embed = buildListingEmbed(listing);

    // Add "Buy Now" button for identity-aware checkout
    const buyButton = new ButtonBuilder()
        .setCustomId(`card-buy-${listingId}`)
        .setLabel('Buy Now')
        .setStyle(ButtonStyle.Primary)
        .setEmoji('🛒');

    const row = new ActionRowBuilder().addComponents(buyButton);
    const msg = await channel.send({ embeds: [embed], components: [row] });
    cardListings.setMessageId.run(msg.id, listingId);

    if (message.channel.id !== channel.id) {
        await message.channel.send(`✅ Listed **${cardName}** for ${formatPrice(priceCents)} in <#${config.CHANNELS.CARD_SHOP}>.`);
    }
}

/**
 * !sold <message_id> or reply to a listing message
 */
async function handleSold(message, args) {
    if (!message.member.roles.cache.has(config.ROLES.AKIVILI)) {
        return message.reply('Only the server owner can mark cards as sold.');
    }

    // Determine message ID from args or reply reference
    let targetMessageId = args[0];
    if (!targetMessageId && message.reference?.messageId) {
        targetMessageId = message.reference.messageId;
    }

    if (!targetMessageId) {
        return message.reply('Usage: `!sold <message_id>` or reply to the listing message.');
    }

    const listing = cardListings.getByMessageId.get(targetMessageId);
    if (!listing) {
        return message.reply('No card listing found for that message.');
    }

    if (listing.status === 'sold') {
        return message.reply('That listing is already marked as sold.');
    }

    // Mark sold and update
    cardListings.markSold.run(listing.id);
    clearExpiryTimer(listing.id);

    const updated = cardListings.getById.get(listing.id);
    await updateListingEmbed(updated);

    await message.channel.send(`✅ **${listing.card_name}** marked as sold.`);
}

export {
    handleSell,
    handleList,
    handleSold,
    startExpiryTimer,
    clearExpiryTimer,
    updateListingEmbed,
    expiryTimers as _expiryTimers,
};
