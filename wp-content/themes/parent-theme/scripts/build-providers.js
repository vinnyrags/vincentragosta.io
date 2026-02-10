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
 * Block Assets:
 *   Editor: src/Providers/[name]/blocks/[block]/editor/index.js => dist/js/[block-name].js
 *   View:   src/Providers/[name]/blocks/[block]/frontend/view.js => dist/js/[block-name]-view.js
 *   Styles: src/Providers/[name]/blocks/[block]/frontend/style.scss => dist/css/[block-name].css
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
 *   - sassImports: string[] — absolute paths to SCSS files prepended to block SCSS
 *   - sassLoadPaths: string[] — additional load paths for sass compiler
 */
let config = { sassImports: [], sassLoadPaths: [] };
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
        const frontendStylePath = path.join(blockPath, 'frontend', 'style.scss');
        if (fs.existsSync(frontendStylePath)) {
            block.frontendStyle = {
                inputPath: frontendStylePath,
                outputPath: path.join(CSS_OUTPUT_DIR, `${entry.name}.css`),
            };
        }

        // Check for frontend view script
        const viewScriptPath = path.join(blockPath, 'frontend', 'view.js');
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
            jsFiles: [],
            blocks: [],
        };

        // Check for SCSS (only if assets directory exists)
        if (fs.existsSync(assetsPath) && fs.existsSync(scssIndexPath)) {
            provider.scss = {
                inputPath: scssIndexPath,
                outputPath: path.join(CSS_OUTPUT_DIR, `${kebabName}.css`),
            };
        }

        // Check for JS files (only if assets directory exists)
        if (fs.existsSync(assetsPath) && fs.existsSync(jsDir)) {
            const jsFiles = fs.readdirSync(jsDir).filter(f => f.endsWith('.js'));
            provider.jsFiles = jsFiles.map(filename => ({
                inputPath: path.join(jsDir, filename),
                outputPath: path.join(JS_OUTPUT_DIR, kebabName, filename),
            }));
        }

        // Discover blocks within this provider
        provider.blocks = discoverProviderBlocks(providerPath, entry.name);

        // Only add if provider has assets or blocks
        if (provider.scss || provider.jsFiles.length > 0 || provider.blocks.length > 0) {
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
            loadPaths: config.sassLoadPaths,
        });

        fs.writeFileSync(provider.scss.outputPath, result.css);
        console.log(`  SCSS: ${path.relative(THEME_ROOT, provider.scss.outputPath)}`);
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

            console.log(`  JS: ${path.relative(THEME_ROOT, jsFile.outputPath)}`);
        } catch (error) {
            console.error(`  JS Error (${path.basename(jsFile.inputPath)}): ${error.message}`);
            allSuccess = false;
        }
    }

    return allSuccess;
}

/**
 * Compile a block's editor script with WordPress externals
 */
async function compileBlockEditorScript(block) {
    if (!block.editorScript) return true;

    try {
        ensureDir(JS_OUTPUT_DIR);

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
            external: Object.keys(wpExternals),
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

        console.log(`  Block Editor JS: ${path.relative(THEME_ROOT, block.editorScript.outputPath)}`);
        return true;
    } catch (error) {
        console.error(`  Block Editor JS Error (${block.name}): ${error.message}`);
        return false;
    }
}

/**
 * Compile a block's frontend view script
 */
async function compileBlockViewScript(block) {
    if (!block.viewScript) return true;

    try {
        ensureDir(JS_OUTPUT_DIR);

        await esbuild.build({
            entryPoints: [block.viewScript.inputPath],
            outfile: block.viewScript.outputPath,
            bundle: true,
            minify: !isWatch,
            sourcemap: isWatch,
            target: ['es2020'],
            format: 'iife',
        });

        console.log(`  Block View JS: ${path.relative(THEME_ROOT, block.viewScript.outputPath)}`);
        return true;
    } catch (error) {
        console.error(`  Block View JS Error (${block.name}): ${error.message}`);
        return false;
    }
}

/**
 * Build SCSS import statements from config
 */
function getSassImports() {
    if (config.sassImports.length === 0) return '';

    const imports = config.sassImports
        .filter(p => fs.existsSync(p))
        .map(p => `@use "${p.replace(/\\/g, '/')}" as *;`);

    return imports.length > 0 ? imports.join('\n') + '\n' : '';
}

/**
 * Compile SCSS with config-driven imports and load paths
 */
function compileSassWithImports(inputPath, outputPath) {
    const imports = getSassImports();
    const originalContent = fs.readFileSync(inputPath, 'utf8');
    const contentWithImports = imports + originalContent;

    const tempDir = path.join(DIST_DIR, '.temp');
    ensureDir(tempDir);
    const tempFile = path.join(tempDir, path.basename(inputPath));
    fs.writeFileSync(tempFile, contentWithImports);

    try {
        const result = sass.compile(tempFile, {
            style: 'expanded',
            sourceMap: false,
            loadPaths: [
                path.dirname(inputPath),
                ...config.sassLoadPaths,
            ],
        });

        fs.writeFileSync(outputPath, result.css);
        return true;
    } finally {
        if (fs.existsSync(tempFile)) {
            fs.unlinkSync(tempFile);
        }
    }
}

/**
 * Compile a block's styles
 */
function compileBlockStyles(block) {
    let allSuccess = true;
    const hasSassConfig = config.sassImports.length > 0;

    // Compile frontend style
    if (block.frontendStyle) {
        try {
            ensureDir(CSS_OUTPUT_DIR);

            if (hasSassConfig) {
                compileSassWithImports(block.frontendStyle.inputPath, block.frontendStyle.outputPath);
            } else {
                const result = sass.compile(block.frontendStyle.inputPath, {
                    style: 'expanded',
                    sourceMap: false,
                });
                fs.writeFileSync(block.frontendStyle.outputPath, result.css);
            }

            console.log(`  Block Style: ${path.relative(THEME_ROOT, block.frontendStyle.outputPath)}`);
        } catch (error) {
            console.error(`  Block Style Error (${block.name}): ${error.message}`);
            allSuccess = false;
        }
    }

    // Compile editor style
    if (block.editorStyle) {
        try {
            ensureDir(CSS_OUTPUT_DIR);

            if (hasSassConfig) {
                compileSassWithImports(block.editorStyle.inputPath, block.editorStyle.outputPath);
            } else {
                const result = sass.compile(block.editorStyle.inputPath, {
                    style: 'expanded',
                    sourceMap: false,
                });
                fs.writeFileSync(block.editorStyle.outputPath, result.css);
            }

            console.log(`  Block Editor Style: ${path.relative(THEME_ROOT, block.editorStyle.outputPath)}`);
        } catch (error) {
            console.error(`  Block Editor Style Error (${block.name}): ${error.message}`);
            allSuccess = false;
        }
    }

    return allSuccess;
}

/**
 * Compile a provider's blocks
 */
async function compileBlocks(provider) {
    if (provider.blocks.length === 0) return true;

    let allSuccess = true;

    for (const block of provider.blocks) {
        console.log(`  Block: ${block.name}`);

        const editorScriptSuccess = await compileBlockEditorScript(block);
        const viewScriptSuccess = await compileBlockViewScript(block);
        const styleSuccess = compileBlockStyles(block);

        if (!editorScriptSuccess || !viewScriptSuccess || !styleSuccess) {
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
    const blocksSuccess = await compileBlocks(provider);

    return scssSuccess && jsSuccess && blocksSuccess;
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

    // Watch each provider's assets and blocks directories
    for (const provider of providers) {
        if (fs.existsSync(provider.assetsPath)) {
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

        const blocksPath = path.join(provider.providerPath, 'blocks');
        if (fs.existsSync(blocksPath)) {
            fs.watch(blocksPath, { recursive: true }, async (eventType, filename) => {
                if (!filename) return;

                const blockName = filename.split(path.sep)[0];
                const block = provider.blocks.find(b => b.name === blockName);

                if (!block) return;

                if (filename.endsWith('.scss')) {
                    console.log(`\nBlock SCSS change in ${provider.name}/${blockName}...`);
                    compileBlockStyles(block);
                } else if (filename.endsWith('.js')) {
                    console.log(`\nBlock JS change in ${provider.name}/${blockName}...`);
                    if (filename.includes('frontend') && filename.includes('view.js')) {
                        await compileBlockViewScript(block);
                    } else {
                        await compileBlockEditorScript(block);
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
