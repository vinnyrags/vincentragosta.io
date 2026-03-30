/**
 * Discord Server Audit — read-only snapshot of current state.
 *
 * Usage: node scripts/shop/discord-audit.js
 */

const { Client, GatewayIntentBits } = require('discord.js');

const TOKEN = process.env.DISCORD_BOT_TOKEN;
if (!TOKEN) { console.error('Set DISCORD_BOT_TOKEN env var.'); process.exit(1); }

const client = new Client({
    intents: [
        GatewayIntentBits.Guilds,
        GatewayIntentBits.GuildMembers,
    ],
});

client.once('ready', async () => {
    const guild = client.guilds.cache.first();
    if (!guild) {
        console.log('No guild found.');
        process.exit(1);
    }

    console.log(`\nServer: ${guild.name}`);
    console.log(`Members: ${guild.memberCount}`);
    console.log(`ID: ${guild.id}\n`);

    // Categories and channels
    console.log('=== CHANNELS ===\n');
    const categories = guild.channels.cache
        .filter((c) => c.type === 4) // CategoryChannel
        .sort((a, b) => a.position - b.position);

    const uncategorized = guild.channels.cache
        .filter((c) => !c.parentId && c.type !== 4)
        .sort((a, b) => a.position - b.position);

    if (uncategorized.size) {
        console.log('(No Category)');
        uncategorized.forEach((c) => {
            const type = c.type === 0 ? '#' : c.type === 2 ? '🔊' : c.type === 5 ? '📢' : '?';
            console.log(`  ${type} ${c.name} (ID: ${c.id})`);
        });
        console.log();
    }

    categories.forEach((cat) => {
        console.log(`${cat.name} (ID: ${cat.id})`);
        const children = guild.channels.cache
            .filter((c) => c.parentId === cat.id)
            .sort((a, b) => a.position - b.position);
        children.forEach((c) => {
            const type = c.type === 0 ? '#' : c.type === 2 ? '🔊' : c.type === 5 ? '📢' : '?';
            const nsfw = c.nsfw ? ' [NSFW]' : '';
            console.log(`  ${type} ${c.name} (ID: ${c.id})${nsfw}`);
        });
        console.log();
    });

    // Roles
    console.log('=== ROLES ===\n');
    const roles = guild.roles.cache
        .sort((a, b) => b.position - a.position);
    roles.forEach((r) => {
        if (r.name === '@everyone') return;
        const color = r.hexColor !== '#000000' ? ` [${r.hexColor}]` : '';
        const managed = r.managed ? ' (bot)' : '';
        console.log(`  ${r.name} — ${r.members.size} members${color}${managed} (ID: ${r.id})`);
    });

    console.log(`\nTotal: ${roles.size} roles, ${guild.channels.cache.size} channels`);

    client.destroy();
});

client.login(TOKEN);
