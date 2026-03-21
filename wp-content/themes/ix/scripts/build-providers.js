#!/usr/bin/env node

/**
 * Build Provider Assets
 *
 * Automatically discovers and builds assets from all provider directories.
 * Uses process.cwd() as the theme root so child themes can run this script directly.
 *
 * Provider Assets:
 *   SCSS: src/Providers/[name]/assets/scss/index.scss => dist/css/[provider-name].css
 *   JS:   src/Providers/[name]/assets/js/*.js => dist/js/[provider-name]/*.js
 *
 * Feature Assets:
 *   SCSS: src/Providers/[name]/assets/scss/features/*.scss => dist/css/features/*.css
 *
 * Block Assets:
 *   Editor: src/Providers/[name]/blocks/[block]/editor/index.js => dist/js/[block-name].js
 *   View:   src/Providers/[name]/blocks/[block]/view.js => dist/js/[block-name]-view.js
 *   Styles: src/Providers/[name]/blocks/[block]/style.scss => dist/css/[block-name].css
 *   Editor Styles: src/Providers/[name]/blocks/[block]/editor/editor.scss => dist/css/[block-name]-editor.css
 *
 * Usage:
 *   node scripts/build-providers.js          # Build once
 *   node scripts/build-providers.js --watch  # Watch mode
 */

const fs = require('fs');
const path = require('path');
const sass = require('sass');
const esbuild = require('esbuild');

// Use process.cwd() so child themes can invoke this script from their own root
const THEME_ROOT = process.cwd();
const PROVIDERS_DIR = path.join(THEME_ROOT, 'src', 'Providers');
const DIST_DIR = path.join(THEME_ROOT, 'dist');
const CSS_OUTPUT_DIR = path.join(DIST_DIR, 'css');
const JS_OUTPUT_DIR = path.join(DIST_DIR, 'js');
const isWatch = process.argv.includes('--watch');

/**
 * Load optional theme-specific config file.
 *
 * If scripts/build-providers.config.js exists in the theme root, it may export:
 *   - sassLoadPaths: string[] — additional load paths for sass compiler
 */
let config = { sassLoadPaths: [] };
const configPath = path.join(THEME_ROOT, 'scripts', 'build-providers.config.js');
if (fs.existsSync(configPath)) {
    config = { ...config, ...require(configPath) };
}

/**
 * WordPress externals for block editor scripts
 */
const wpExternals = {
    '@wordpress/blocks': 'wp.blocks',
    '@wordpress/element': 'wp.element',
    '@wordpress/block-editor': 'wp.blockEditor',
    '@wordpress/components': 'wp.components',
    '@wordpress/compose': 'wp.compose',
    '@wordpress/data': 'wp.data',
    '@wordpress/hooks': 'wp.hooks',
    '@wordpress/api-fetch': 'wp.apiFetch',
    '@wordpress/i18n': 'wp.i18n',
    '@wordpress/rich-text': 'wp.richText',
    '@wordpress/server-side-render': 'wp.serverSideRender',
    'react': 'React',
    'react-dom': 'ReactDOM',
};

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
 * Create all output directories once at build start
 */
function ensureOutputDirs(providers) {
    ensureDir(CSS_OUTPUT_DIR);
    ensureDir(JS_OUTPUT_DIR);

    for (const provider of providers) {
        if (provider.jsFiles.length > 0) {
            ensureDir(path.join(JS_OUTPUT_DIR, provider.kebabName));
        }
        if (provider.featureScss.length > 0) {
            ensureDir(path.join(CSS_OUTPUT_DIR, 'features'));
        }
    }
}

/**
 * Create a buffered logger that accumulates output and flushes atomically.
 * Used during parallel provider compilation to prevent interleaved output.
 */
function createLogger() {
    const lines = [];
    return {
        log(msg) { lines.push(msg); },
        error(msg) { lines.push(msg); },
        flush() {
            if (lines.length > 0) {
                process.stdout.write(lines.join('\n') + '\n');
            }
        },
    };
}

/**
 * Direct logger that writes immediately (used in watch mode)
 */
const directLogger = {
    log(msg) { console.log(msg); },
    error(msg) { console.error(msg); },
    flush() {},
};

/**
 * Find all blocks within a provider directory
 */
function discoverProviderBlocks(providerPath, providerName) {
    const blocksPath = path.join(providerPath, 'blocks');
    const blocks = [];

    if (!fs.existsSync(blocksPath)) {
        return blocks;
    }

    const entries = fs.readdirSync(blocksPath, { withFileTypes: true });

    for (const entry of entries) {
        if (!entry.isDirectory()) continue;

        const blockPath = path.join(blocksPath, entry.name);
        const blockJsonPath = path.join(blockPath, 'block.json');

        // Only process directories with block.json
        if (!fs.existsSync(blockJsonPath)) continue;

        const block = {
            name: entry.name,
            path: blockPath,
            provider: providerName,
            editorScript: null,
            viewScript: null,
            editorStyle: null,
            frontendStyle: null,
        };

        // Check for editor script
        const editorIndexPath = path.join(blockPath, 'editor', 'index.js');
        if (fs.existsSync(editorIndexPath)) {
            block.editorScript = {
                inputPath: editorIndexPath,
                outputPath: path.join(JS_OUTPUT_DIR, `${entry.name}.js`),
            };
        }

        // Check for editor style
        const editorStylePath = path.join(blockPath, 'editor', 'editor.scss');
        if (fs.existsSync(editorStylePath)) {
            block.editorStyle = {
                inputPath: editorStylePath,
                outputPath: path.join(CSS_OUTPUT_DIR, `${entry.name}-editor.css`),
            };
        }

        // Check for frontend style
        const frontendStylePath = path.join(blockPath, 'style.scss');
        if (fs.existsSync(frontendStylePath)) {
            block.frontendStyle = {
                inputPath: frontendStylePath,
                outputPath: path.join(CSS_OUTPUT_DIR, `${entry.name}.css`),
            };
        }

        // Check for view script
        const viewScriptPath = path.join(blockPath, 'view.js');
        if (fs.existsSync(viewScriptPath)) {
            block.viewScript = {
                inputPath: viewScriptPath,
                outputPath: path.join(JS_OUTPUT_DIR, `${entry.name}-view.js`),
            };
        }

        blocks.push(block);
    }

    return blocks;
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

        const kebabName = toKebabCase(entry.name);
        const scssIndexPath = path.join(assetsPath, 'scss', 'index.scss');
        const jsDir = path.join(assetsPath, 'js');

        const provider = {
            name: entry.name,
            kebabName,
            providerPath,
            assetsPath,
            scss: null,
            featureScss: [],
            jsFiles: [],
            blocks: [],
        };

        const assetsExist = fs.existsSync(assetsPath);

        // Check for SCSS (only if assets directory exists)
        if (assetsExist && fs.existsSync(scssIndexPath)) {
            provider.scss = {
                inputPath: scssIndexPath,
                outputPath: path.join(CSS_OUTPUT_DIR, `${kebabName}.css`),
            };
        }

        // Check for feature SCSS files (only if assets directory exists)
        const featuresDir = path.join(assetsPath, 'scss', 'features');
        if (assetsExist && fs.existsSync(featuresDir)) {
            const featureFiles = fs.readdirSync(featuresDir).filter(f => f.endsWith('.scss'));
            const featureOutputDir = path.join(CSS_OUTPUT_DIR, 'features');
            provider.featureScss = featureFiles.map(filename => ({
                inputPath: path.join(featuresDir, filename),
                outputPath: path.join(featureOutputDir, filename.replace('.scss', '.css')),
            }));
        }

        // Check for JS files (only if assets directory exists)
        if (assetsExist && fs.existsSync(jsDir)) {
            const jsFiles = fs.readdirSync(jsDir).filter(f => f.endsWith('.js'));
            provider.jsFiles = jsFiles.map(filename => ({
                inputPath: path.join(jsDir, filename),
                outputPath: path.join(JS_OUTPUT_DIR, kebabName, filename),
            }));
        }

        // Discover blocks within this provider
        provider.blocks = discoverProviderBlocks(providerPath, entry.name);

        // Only add if provider has assets, features, or blocks
        if (provider.scss || provider.featureScss.length > 0 || provider.jsFiles.length > 0 || provider.blocks.length > 0) {
            providers.push(provider);
        }
    }

    return providers;
}

/**
 * Compile a single SCSS file
 */
async function compileSingleScss(inputPath, outputPath) {
    const result = await sass.compileAsync(inputPath, {
        style: 'expanded',
        sourceMap: false,
        loadPaths: config.sassLoadPaths,
    });
    fs.writeFileSync(outputPath, result.css);
}

/**
 * Compile a provider's SCSS
 */
async function compileScss(provider, logger) {
    if (!provider.scss) return true;

    try {
        await compileSingleScss(provider.scss.inputPath, provider.scss.outputPath);
        logger.log(`  SCSS: ${path.relative(THEME_ROOT, provider.scss.outputPath)}`);
        return true;
    } catch (error) {
        logger.error(`  SCSS Error: ${error.message}`);
        return false;
    }
}

/**
 * Compile a provider's feature SCSS files
 */
async function compileFeatureScss(provider, logger) {
    if (provider.featureScss.length === 0) return true;

    let allSuccess = true;

    for (const feature of provider.featureScss) {
        try {
            await compileSingleScss(feature.inputPath, feature.outputPath);
            logger.log(`  Feature SCSS: ${path.relative(THEME_ROOT, feature.outputPath)}`);
        } catch (error) {
            logger.error(`  Feature SCSS Error (${path.basename(feature.inputPath)}): ${error.message}`);
            allSuccess = false;
        }
    }

    return allSuccess;
}

/**
 * Compile a provider's JS files
 */
async function compileJs(provider, logger) {
    if (provider.jsFiles.length === 0) return true;

    const results = await Promise.all(provider.jsFiles.map(jsFile =>
        esbuild.build({
            entryPoints: [jsFile.inputPath],
            outfile: jsFile.outputPath,
            bundle: true,
            minify: !isWatch,
            sourcemap: isWatch,
            target: ['es2020'],
            format: 'iife',
        })
        .then(() => {
            logger.log(`  JS: ${path.relative(THEME_ROOT, jsFile.outputPath)}`);
        })
        .catch(error => {
            logger.error(`  JS Error (${path.basename(jsFile.inputPath)}): ${error.message}`);
            return 'failed';
        })
    ));

    return !results.includes('failed');
}

/**
 * Compile a block's editor script with WordPress externals
 */
async function compileBlockEditorScript(block, logger) {
    if (!block.editorScript) return true;

    try {
        await esbuild.build({
            entryPoints: [block.editorScript.inputPath],
            outfile: block.editorScript.outputPath,
            bundle: true,
            minify: !isWatch,
            sourcemap: isWatch,
            target: ['es2020'],
            format: 'iife',
            loader: { '.js': 'jsx' },
            jsxFactory: 'wp.element.createElement',
            jsxFragment: 'wp.element.Fragment',
            plugins: [
                {
                    name: 'ignore-styles',
                    setup(build) {
                        build.onResolve({ filter: /\.(css|scss|sass)$/ }, args => {
                            return { path: args.path, namespace: 'ignore-styles' };
                        });
                        build.onLoad({ filter: /.*/, namespace: 'ignore-styles' }, () => {
                            return { contents: '', loader: 'js' };
                        });
                    },
                },
                {
                    name: 'wordpress-externals',
                    setup(build) {
                        build.onResolve({ filter: /^@wordpress\// }, args => {
                            return { path: args.path, namespace: 'wp-external' };
                        });
                        build.onResolve({ filter: /^(react|react-dom)$/ }, args => {
                            return { path: args.path, namespace: 'wp-external' };
                        });
                        build.onLoad({ filter: /.*/, namespace: 'wp-external' }, args => {
                            const global = wpExternals[args.path];
                            return {
                                contents: `module.exports = window.${global};`,
                                loader: 'js',
                            };
                        });
                    },
                },
            ],
        });

        logger.log(`  Block Editor JS: ${path.relative(THEME_ROOT, block.editorScript.outputPath)}`);
        return true;
    } catch (error) {
        logger.error(`  Block Editor JS Error (${block.name}): ${error.message}`);
        return false;
    }
}

/**
 * Compile a block's frontend view script
 */
async function compileBlockViewScript(block, logger) {
    if (!block.viewScript) return true;

    try {
        await esbuild.build({
            entryPoints: [block.viewScript.inputPath],
            outfile: block.viewScript.outputPath,
            bundle: true,
            minify: !isWatch,
            sourcemap: isWatch,
            target: ['es2020'],
            format: 'iife',
        });

        logger.log(`  Block View JS: ${path.relative(THEME_ROOT, block.viewScript.outputPath)}`);
        return true;
    } catch (error) {
        logger.error(`  Block View JS Error (${block.name}): ${error.message}`);
        return false;
    }
}

/**
 * Compile a block's styles
 */
async function compileBlockStyles(block, logger) {
    const tasks = [];

    if (block.frontendStyle) {
        tasks.push(
            compileSingleScss(block.frontendStyle.inputPath, block.frontendStyle.outputPath)
                .then(() => logger.log(`  Block Style: ${path.relative(THEME_ROOT, block.frontendStyle.outputPath)}`))
                .catch(error => {
                    logger.error(`  Block Style Error (${block.name}): ${error.message}`);
                    return 'failed';
                })
        );
    }

    if (block.editorStyle) {
        tasks.push(
            compileSingleScss(block.editorStyle.inputPath, block.editorStyle.outputPath)
                .then(() => logger.log(`  Block Editor Style: ${path.relative(THEME_ROOT, block.editorStyle.outputPath)}`))
                .catch(error => {
                    logger.error(`  Block Editor Style Error (${block.name}): ${error.message}`);
                    return 'failed';
                })
        );
    }

    const results = await Promise.all(tasks);
    return !results.includes('failed');
}

/**
 * Compile a provider's blocks
 */
async function compileBlock(block, logger) {
    logger.log(`  Block: ${block.name}`);

    const results = await Promise.all([
        compileBlockEditorScript(block, logger),
        compileBlockViewScript(block, logger),
        compileBlockStyles(block, logger),
    ]);

    return results.every(Boolean);
}

async function compileBlocks(provider, logger) {
    if (provider.blocks.length === 0) return true;

    const results = await Promise.all(provider.blocks.map(block => compileBlock(block, logger)));
    return results.every(Boolean);
}

/**
 * Compile a single provider's assets
 */
async function compileProvider(provider, logger) {
    logger.log(`\n${provider.name}:`);

    const results = await Promise.all([
        compileScss(provider, logger),
        compileFeatureScss(provider, logger),
        compileJs(provider, logger),
        compileBlocks(provider, logger),
    ]);

    return results.every(Boolean);
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
    ensureOutputDirs(providers);

    const loggers = providers.map(() => createLogger());
    const results = await Promise.all(
        providers.map((provider, i) => compileProvider(provider, loggers[i]))
    );

    // Flush loggers in discovery order for deterministic output grouping
    for (const logger of loggers) {
        logger.flush();
    }

    const success = results.filter(Boolean).length;
    const failed = results.length - success;
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
    ensureOutputDirs(providers);

    // Initial compile (parallel with buffered output)
    const loggers = providers.map(() => createLogger());
    await Promise.all(
        providers.map((provider, i) => compileProvider(provider, loggers[i]))
    );
    for (const logger of loggers) {
        logger.flush();
    }

    // Watch each provider's assets and blocks directories
    for (const provider of providers) {
        if (fs.existsSync(provider.assetsPath)) {
            fs.watch(provider.assetsPath, { recursive: true }, async (eventType, filename) => {
                if (!filename) return;

                if (filename.endsWith('.scss') && filename.includes(path.join('scss', 'features'))) {
                    console.log(`\nFeature SCSS change in ${provider.name}...`);
                    await compileFeatureScss(provider, directLogger);
                } else if (filename.endsWith('.scss')) {
                    console.log(`\nSCSS change in ${provider.name}...`);
                    await compileScss(provider, directLogger);
                } else if (filename.endsWith('.js')) {
                    console.log(`\nJS change in ${provider.name}...`);
                    await compileJs(provider, directLogger);
                }
            });
        }

        const blocksPath = path.join(provider.providerPath, 'blocks');
        if (fs.existsSync(blocksPath)) {
            fs.watch(blocksPath, { recursive: true }, async (eventType, filename) => {
                if (!filename) return;

                const blockName = filename.split(path.sep)[0];
                const block = provider.blocks.find(b => b.name === blockName);

                if (!block) return;

                if (filename.endsWith('.scss')) {
                    console.log(`\nBlock SCSS change in ${provider.name}/${blockName}...`);
                    await compileBlockStyles(block, directLogger);
                } else if (filename.endsWith('.js')) {
                    console.log(`\nBlock JS change in ${provider.name}/${blockName}...`);
                    if (path.basename(filename) === 'view.js') {
                        await compileBlockViewScript(block, directLogger);
                    } else {
                        await compileBlockEditorScript(block, directLogger);
                    }
                }
            });
        }
    }

    console.log('\nPress Ctrl+C to stop watching\n');
}

// Run
if (isWatch) {
    watchProviders();
} else {
    compileAll();
}
