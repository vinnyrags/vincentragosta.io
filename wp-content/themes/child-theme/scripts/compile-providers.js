#!/usr/bin/env node

/**
 * Compile Provider Assets
 *
 * Automatically discovers and compiles assets from all provider directories.
 *
 * Provider Assets:
 *   SCSS: src/Providers/[name]/assets/scss/index.scss => dist/css/[provider-name].css
 *   JS:   src/Providers/[name]/assets/js/*.js => dist/js/[provider-name]/*.js
 *
 * Block Assets:
 *   Editor: src/Providers/[name]/blocks/[block]/editor/index.js => dist/js/[block-name].js
 *   Styles: src/Providers/[name]/blocks/[block]/frontend/style.scss => dist/css/[block-name].css
 *   Editor Styles: src/Providers/[name]/blocks/[block]/editor/editor.scss => dist/css/[block-name]-editor.css
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

// Path to parent theme breakpoints for SCSS compilation
const PARENT_THEME_DIR = path.join(__dirname, '..', '..', 'parent-theme');
const BREAKPOINTS_PATH = path.join(PARENT_THEME_DIR, 'assets', 'src', 'scss', 'common', '_breakpoints.scss');
const MIXINS_PATH = path.join(PARENT_THEME_DIR, 'assets', 'src', 'scss', 'common', '_mixins.scss');

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
            // Use JSX loader for .js files (WordPress blocks use JSX)
            loader: { '.js': 'jsx' },
            // Map WordPress packages to globals
            external: Object.keys(wpExternals),
            plugins: [
                // Ignore CSS/SCSS imports (compiled separately)
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
                // Handle WordPress externals
                {
                    name: 'wordpress-externals',
                    setup(build) {
                        // Rewrite imports to use WordPress globals
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

        console.log(`  Block Editor JS: ${path.relative(process.cwd(), block.editorScript.outputPath)}`);
        return true;
    } catch (error) {
        console.error(`  Block Editor JS Error (${block.name}): ${error.message}`);
        return false;
    }
}

/**
 * Build SCSS import statements for parent theme dependencies
 */
function getSassImports() {
    const imports = [];

    // Add breakpoints if available
    if (fs.existsSync(BREAKPOINTS_PATH)) {
        imports.push(`@use "${BREAKPOINTS_PATH.replace(/\\/g, '/')}" as *;`);
    }

    // Add mixins if available
    if (fs.existsSync(MIXINS_PATH)) {
        imports.push(`@use "${MIXINS_PATH.replace(/\\/g, '/')}" as *;`);
    }

    return imports.join('\n') + '\n';
}

/**
 * Compile SCSS with parent theme imports
 */
function compileSassWithImports(inputPath, outputPath) {
    const imports = getSassImports();
    const originalContent = fs.readFileSync(inputPath, 'utf8');
    const contentWithImports = imports + originalContent;

    // Create a temporary file with the imports prepended
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
                path.join(PARENT_THEME_DIR, 'assets', 'src', 'scss'),
            ],
        });

        fs.writeFileSync(outputPath, result.css);
        return true;
    } finally {
        // Clean up temp file
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

    // Compile frontend style
    if (block.frontendStyle) {
        try {
            ensureDir(CSS_OUTPUT_DIR);
            compileSassWithImports(block.frontendStyle.inputPath, block.frontendStyle.outputPath);
            console.log(`  Block Style: ${path.relative(process.cwd(), block.frontendStyle.outputPath)}`);
        } catch (error) {
            console.error(`  Block Style Error (${block.name}): ${error.message}`);
            allSuccess = false;
        }
    }

    // Compile editor style
    if (block.editorStyle) {
        try {
            ensureDir(CSS_OUTPUT_DIR);
            compileSassWithImports(block.editorStyle.inputPath, block.editorStyle.outputPath);
            console.log(`  Block Editor Style: ${path.relative(process.cwd(), block.editorStyle.outputPath)}`);
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

        const scriptSuccess = await compileBlockEditorScript(block);
        const styleSuccess = compileBlockStyles(block);

        if (!scriptSuccess || !styleSuccess) {
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

    // Watch each provider's assets directory
    for (const provider of providers) {
        // Watch assets directory if it exists
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

        // Watch blocks directory if it exists
        const blocksPath = path.join(provider.providerPath, 'blocks');
        if (fs.existsSync(blocksPath)) {
            fs.watch(blocksPath, { recursive: true }, async (eventType, filename) => {
                if (!filename) return;

                // Find which block was changed
                const blockName = filename.split(path.sep)[0];
                const block = provider.blocks.find(b => b.name === blockName);

                if (!block) return;

                if (filename.endsWith('.scss')) {
                    console.log(`\nBlock SCSS change in ${provider.name}/${blockName}...`);
                    compileBlockStyles(block);
                } else if (filename.endsWith('.js')) {
                    console.log(`\nBlock JS change in ${provider.name}/${blockName}...`);
                    await compileBlockEditorScript(block);
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
