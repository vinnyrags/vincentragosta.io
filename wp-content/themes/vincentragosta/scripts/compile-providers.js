#!/usr/bin/env node

/**
 * Compile Provider Assets
 *
 * Automatically discovers and compiles assets from all provider asset directories.
 *
 * SCSS: src/Providers/[name]/assets/scss/index.scss -> dist/css/[provider-name].css
 * JS:   src/Providers/[name]/assets/js/*.js -> dist/js/[provider-name]/*.js
 *
 * Usage:
 *   node scripts/compile-providers.js          # Build once
 *   node scripts/compile-providers.js --watch  # Watch mode
 */

const fs = require('fs');
const path = require('path');
const sass = require('sass');
const esbuild = require('esbuild');

const PROVIDERS_DIR = path.join(__dirname, '..', 'src', 'Providers');
const DIST_DIR = path.join(__dirname, '..', 'dist');
const CSS_OUTPUT_DIR = path.join(DIST_DIR, 'css');
const JS_OUTPUT_DIR = path.join(DIST_DIR, 'js');
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
 * Ensure directory exists
 */
function ensureDir(dir) {
    if (!fs.existsSync(dir)) {
        fs.mkdirSync(dir, { recursive: true });
    }
}

/**
 * Find all provider directories with assets
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
        const assetsPath = path.join(providerPath, 'assets');

        if (!fs.existsSync(assetsPath)) continue;

        const kebabName = toKebabCase(entry.name);
        const scssIndexPath = path.join(assetsPath, 'scss', 'index.scss');
        const jsDir = path.join(assetsPath, 'js');

        const provider = {
            name: entry.name,
            kebabName,
            assetsPath,
            scss: null,
            jsFiles: [],
        };

        // Check for SCSS
        if (fs.existsSync(scssIndexPath)) {
            provider.scss = {
                inputPath: scssIndexPath,
                outputPath: path.join(CSS_OUTPUT_DIR, `${kebabName}.css`),
            };
        }

        // Check for JS files
        if (fs.existsSync(jsDir)) {
            const jsFiles = fs.readdirSync(jsDir).filter(f => f.endsWith('.js'));
            provider.jsFiles = jsFiles.map(filename => ({
                inputPath: path.join(jsDir, filename),
                outputPath: path.join(JS_OUTPUT_DIR, kebabName, filename),
            }));
        }

        // Only add if provider has assets
        if (provider.scss || provider.jsFiles.length > 0) {
            providers.push(provider);
        }
    }

    return providers;
}

/**
 * Compile a provider's SCSS
 */
function compileScss(provider) {
    if (!provider.scss) return true;

    try {
        ensureDir(CSS_OUTPUT_DIR);

        const result = sass.compile(provider.scss.inputPath, {
            style: 'expanded',
            sourceMap: false,
        });

        fs.writeFileSync(provider.scss.outputPath, result.css);
        console.log(`  SCSS: ${path.relative(process.cwd(), provider.scss.outputPath)}`);
        return true;
    } catch (error) {
        console.error(`  SCSS Error: ${error.message}`);
        return false;
    }
}

/**
 * Compile a provider's JS files
 */
async function compileJs(provider) {
    if (provider.jsFiles.length === 0) return true;

    const outputDir = path.join(JS_OUTPUT_DIR, provider.kebabName);
    ensureDir(outputDir);

    let allSuccess = true;

    for (const jsFile of provider.jsFiles) {
        try {
            await esbuild.build({
                entryPoints: [jsFile.inputPath],
                outfile: jsFile.outputPath,
                bundle: true,
                minify: !isWatch,
                sourcemap: isWatch,
                target: ['es2020'],
                format: 'iife',
            });

            console.log(`  JS: ${path.relative(process.cwd(), jsFile.outputPath)}`);
        } catch (error) {
            console.error(`  JS Error (${path.basename(jsFile.inputPath)}): ${error.message}`);
            allSuccess = false;
        }
    }

    return allSuccess;
}

/**
 * Compile a single provider's assets
 */
async function compileProvider(provider) {
    console.log(`\n${provider.name}:`);

    const scssSuccess = compileScss(provider);
    const jsSuccess = await compileJs(provider);

    return scssSuccess && jsSuccess;
}

/**
 * Compile all providers
 */
async function compileAll() {
    const providers = discoverProviders();

    if (providers.length === 0) {
        console.log('No provider assets found to compile');
        return;
    }

    console.log(`\nCompiling ${providers.length} provider(s)...`);

    let success = 0;
    let failed = 0;

    for (const provider of providers) {
        if (await compileProvider(provider)) {
            success++;
        } else {
            failed++;
        }
    }

    console.log(`\nDone: ${success} succeeded, ${failed} failed\n`);
}

/**
 * Watch mode - recompile on changes
 */
async function watchProviders() {
    const providers = discoverProviders();

    if (providers.length === 0) {
        console.log('No provider assets found to watch');
        return;
    }

    console.log(`\nWatching ${providers.length} provider(s) for changes...`);

    // Initial compile
    for (const provider of providers) {
        await compileProvider(provider);
    }

    // Watch each provider's assets directory
    for (const provider of providers) {
        fs.watch(provider.assetsPath, { recursive: true }, async (eventType, filename) => {
            if (!filename) return;

            if (filename.endsWith('.scss')) {
                console.log(`\nSCSS change in ${provider.name}...`);
                compileScss(provider);
            } else if (filename.endsWith('.js')) {
                console.log(`\nJS change in ${provider.name}...`);
                await compileJs(provider);
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
