#!/usr/bin/env node
/**
 * Deploy notification — posts to #dev-log via Discord webhook or bot token.
 *
 * Called by the post-receive hook during deployments. Sends messages to
 * #dev-log without depending on the bot process (which may be restarting).
 *
 * Usage:
 *   node bot/notify-deploy.js --status=started --branch=main --env=production
 *   node bot/notify-deploy.js --status=success --branch=main --env=production --summary="3 files changed"
 *   node bot/notify-deploy.js --status=tests-failed --branch=main --env=production --output="test failure details"
 *   node bot/notify-deploy.js --status=failed --branch=main --env=production --error="build failed"
 */

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

// Parse args
const args = Object.fromEntries(
    process.argv.slice(2)
        .filter((a) => a.startsWith('--'))
        .map((a) => {
            const [key, ...rest] = a.slice(2).split('=');
            return [key, rest.join('=') || 'true'];
        })
);

const { status, branch, env, summary, output, error } = args;

// Read bot token from wp-config-env.php
const configPath = path.resolve(__dirname, '../wp-config-env.php');
let botToken;
try {
    const content = fs.readFileSync(configPath, 'utf8');
    const match = content.match(/define\('DISCORD_BOT_TOKEN',\s*'([^']+)'\)/);
    botToken = match?.[1];
} catch { /* ignore */ }

if (!botToken) {
    console.error('No bot token found — skipping deploy notification');
    process.exit(0);
}

const CHANNEL_ID = '1489513907025346630'; // #dev-log

// Build embed
let title, description, color;

switch (status) {
    case 'started':
        title = `🔄 Deploying ${branch} to ${env}`;
        description = 'Build started...';
        color = 0x95a5a6; // grey
        break;

    case 'tests-passed':
        title = `✅ Tests passed`;
        description = summary || 'All tests passed.';
        color = 0x2ecc71; // green
        break;

    case 'tests-failed':
        title = `❌ Tests failed — bot NOT restarted`;
        description = output
            ? `\`\`\`\n${output.slice(0, 1800)}\n\`\`\``
            : 'Bot tests failed. Previous version still running.';
        color = 0xe74c3c; // red
        break;

    case 'success':
        title = `✅ Deployed ${branch} to ${env}`;
        description = summary || 'Deployment complete.';
        color = 0x2ecc71; // green
        break;

    case 'failed':
        title = `❌ Deploy failed — ${branch} to ${env}`;
        description = error || 'Unknown error.';
        color = 0xe74c3c; // red
        break;

    default:
        title = `📋 Deploy: ${status}`;
        description = summary || output || error || '';
        color = 0x95a5a6;
}

const embed = {
    title,
    description,
    color,
    timestamp: new Date().toISOString(),
};

// Post via Discord REST API (not the bot process — it may be restarting)
try {
    const res = await fetch(`https://discord.com/api/v10/channels/${CHANNEL_ID}/messages`, {
        method: 'POST',
        headers: {
            Authorization: `Bot ${botToken}`,
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ embeds: [embed] }),
    });

    if (!res.ok) {
        const text = await res.text();
        console.error(`Discord API error (${res.status}): ${text}`);
    }
} catch (e) {
    console.error('Failed to send deploy notification:', e.message);
}
