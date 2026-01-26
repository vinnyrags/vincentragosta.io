#!/usr/bin/env node

/**
 * Build Block View Scripts
 *
 * Auto-discovers and compiles view.js files from block directories.
 * Each block's view.js is compiled to dist/blocks/{block-name}-view.js
 *
 * Usage:
 *   node scripts/build-block-views.js          # Build once
 *   node scripts/build-block-views.js --watch  # Watch mode
 */

const fs = require('fs');
const path = require('path');
const esbuild = require('esbuild');

const BLOCKS_DIR = path.join(__dirname, '..', 'blocks');
const OUTPUT_DIR = path.join(__dirname, '..', 'dist', 'blocks');
const isWatch = process.argv.includes('--watch');

/**
 * Find all block directories with view.js files
 */
function discoverViewScripts() {
    const viewScripts = [];

    if (!fs.existsSync(BLOCKS_DIR)) {
        console.log('No blocks directory found');
        return viewScripts;
    }

    const entries = fs.readdirSync(BLOCKS_DIR, { withFileTypes: true });

    for (const entry of entries) {
        if (!entry.isDirectory()) continue;

        const viewPath = path.join(BLOCKS_DIR, entry.name, 'view.js');

        if (fs.existsSync(viewPath)) {
            viewScripts.push({
                name: entry.name,
                inputPath: viewPath,
                outputPath: path.join(OUTPUT_DIR, `${entry.name}-view.js`),
            });
        }
    }

    return viewScripts;
}

/**
 * Ensure output directory exists
 */
function ensureOutputDir() {
    if (!fs.existsSync(OUTPUT_DIR)) {
        fs.mkdirSync(OUTPUT_DIR, { recursive: true });
    }
}

/**
 * Build a single view script
 */
async function buildViewScript(script) {
    try {
        await esbuild.build({
            entryPoints: [script.inputPath],
            outfile: script.outputPath,
            bundle: true,
            minify: !isWatch,
            sourcemap: isWatch,
            target: ['es2020'],
            format: 'iife',
        });

        console.log(`Compiled: ${script.name}/view.js -> ${path.relative(process.cwd(), script.outputPath)}`);
        return true;
    } catch (error) {
        console.error(`Error compiling ${script.name}/view.js:`, error.message);
        return false;
    }
}

/**
 * Build all view scripts
 */
async function buildAll() {
    const scripts = discoverViewScripts();

    if (scripts.length === 0) {
        console.log('No block view scripts found');
        return;
    }

    ensureOutputDir();
    console.log(`\nCompiling ${scripts.length} view script(s)...\n`);

    let success = 0;
    let failed = 0;

    for (const script of scripts) {
        if (await buildViewScript(script)) {
            success++;
        } else {
            failed++;
        }
    }

    console.log(`\nDone: ${success} compiled, ${failed} failed\n`);
}

/**
 * Watch mode - rebuild on changes
 */
async function watchScripts() {
    const scripts = discoverViewScripts();

    if (scripts.length === 0) {
        console.log('No block view scripts found to watch');
        return;
    }

    ensureOutputDir();
    console.log(`\nWatching ${scripts.length} view script(s) for changes...\n`);

    // Initial build
    for (const script of scripts) {
        await buildViewScript(script);
    }

    // Watch each view.js file
    for (const script of scripts) {
        fs.watch(script.inputPath, async (eventType) => {
            if (eventType === 'change') {
                console.log(`\nChange detected in ${script.name}/view.js...`);
                await buildViewScript(script);
            }
        });
    }

    console.log('\nPress Ctrl+C to stop watching\n');
}

// Run
if (isWatch) {
    watchScripts();
} else {
    buildAll();
}
