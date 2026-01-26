#!/usr/bin/env node

/**
 * Compile Provider Assets
 *
 * Automatically discovers and compiles SCSS files from all provider asset directories.
 * Looks for: src/Providers/[name]/assets/scss/index.scss
 * Outputs to: src/Providers/[name]/assets/css/[provider-name].css
 *
 * Usage:
 *   node scripts/compile-providers.js          # Build once
 *   node scripts/compile-providers.js --watch  # Watch mode
 */

const fs = require('fs');
const path = require('path');
const sass = require('sass');

const PROVIDERS_DIR = path.join(__dirname, '..', 'src', 'Providers');
const OUTPUT_DIR = path.join(__dirname, '..', 'dist', 'css');
const isWatch = process.argv.includes('--watch');

/**
 * Convert PascalCase to kebab-case
 */
function toKebabCase(str) {
    return str
        .replace(/([a-z])([A-Z])/g, '$1-$2')
        .replace(/([A-Z])([A-Z][a-z])/g, '$1-$2')
        .toLowerCase();
}

/**
 * Find all provider directories with assets/scss/index.scss
 */
function discoverProviders() {
    const providers = [];

    if (!fs.existsSync(PROVIDERS_DIR)) {
        console.log('No Providers directory found');
        return providers;
    }

    const entries = fs.readdirSync(PROVIDERS_DIR, { withFileTypes: true });

    for (const entry of entries) {
        if (!entry.isDirectory()) continue;

        const providerPath = path.join(PROVIDERS_DIR, entry.name);
        const scssIndexPath = path.join(providerPath, 'assets', 'scss', 'index.scss');

        if (fs.existsSync(scssIndexPath)) {
            providers.push({
                name: entry.name,
                scssPath: scssIndexPath,
                cssDir: OUTPUT_DIR,
                cssPath: path.join(OUTPUT_DIR, `${toKebabCase(entry.name)}.css`),
            });
        }
    }

    return providers;
}

/**
 * Compile a single provider's SCSS
 */
function compileProvider(provider) {
    try {
        // Ensure CSS directory exists
        if (!fs.existsSync(provider.cssDir)) {
            fs.mkdirSync(provider.cssDir, { recursive: true });
        }

        const result = sass.compile(provider.scssPath, {
            style: 'expanded',
            sourceMap: false,
        });

        fs.writeFileSync(provider.cssPath, result.css);
        console.log(`Compiled: ${provider.name} -> ${path.relative(process.cwd(), provider.cssPath)}`);
        return true;
    } catch (error) {
        console.error(`Error compiling ${provider.name}:`, error.message);
        return false;
    }
}

/**
 * Compile all providers
 */
function compileAll() {
    const providers = discoverProviders();

    if (providers.length === 0) {
        console.log('No provider assets found to compile');
        return;
    }

    console.log(`\nCompiling ${providers.length} provider(s)...\n`);

    let success = 0;
    let failed = 0;

    for (const provider of providers) {
        if (compileProvider(provider)) {
            success++;
        } else {
            failed++;
        }
    }

    console.log(`\nDone: ${success} compiled, ${failed} failed\n`);
}

/**
 * Watch mode - recompile on changes
 */
function watchProviders() {
    const providers = discoverProviders();

    if (providers.length === 0) {
        console.log('No provider assets found to watch');
        return;
    }

    console.log(`\nWatching ${providers.length} provider(s) for changes...\n`);

    // Initial compile
    for (const provider of providers) {
        compileProvider(provider);
    }

    // Watch each provider's scss directory
    for (const provider of providers) {
        const scssDir = path.dirname(provider.scssPath);

        fs.watch(scssDir, { recursive: true }, (eventType, filename) => {
            if (filename && filename.endsWith('.scss')) {
                console.log(`\nChange detected in ${provider.name}...`);
                compileProvider(provider);
            }
        });
    }

    console.log('\nPress Ctrl+C to stop watching\n');
}

// Run
if (isWatch) {
    watchProviders();
} else {
    compileAll();
}
