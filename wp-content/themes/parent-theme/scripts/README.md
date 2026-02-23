# build-providers.js

Builds assets (SCSS, JS, blocks) from provider directories.

## Purpose

Each provider can have its own assets and blocks. This script auto-discovers and builds them all, keeping provider functionality self-contained. Both parent and child themes use this single script.

## Usage

```bash
# Build once
node scripts/build-providers.js

# Watch mode (rebuilds on changes)
node scripts/build-providers.js --watch

# Via npm
npm run build-providers
npm run watch-providers
```

Child themes run the parent's script directly. The script uses `process.cwd()` as the theme root, so it discovers assets relative to whichever theme invokes it.

## Directory Structure

```
src/Providers/{ProviderName}/
├── assets/
│   ├── scss/
│   │   └── index.scss      → dist/css/{provider-name}.css
│   └── js/
│       └── *.js            → dist/js/{provider-name}/*.js
└── blocks/
    └── {block-name}/
        ├── block.json
        ├── editor/
        │   ├── index.js    → dist/js/{block-name}.js
        │   └── editor.scss → dist/css/{block-name}-editor.css
        └── frontend/
            ├── style.scss  → dist/css/{block-name}.css
            └── view.js     → dist/js/{block-name}-view.js
```

## Output Mapping

| Source | Output |
|--------|--------|
| Provider SCSS | `dist/css/{provider-name}.css` |
| Provider JS | `dist/js/{provider-name}/{filename}.js` |
| Block Editor JS | `dist/js/{block-name}.js` |
| Block View JS | `dist/js/{block-name}-view.js` |
| Block Frontend Style | `dist/css/{block-name}.css` |
| Block Editor Style | `dist/css/{block-name}-editor.css` |

## Config File

Child themes can create `scripts/build-providers.config.js` to customize SCSS compilation:

```js
const path = require('path');
const parentScss = path.resolve(__dirname, '..', '..', 'parent-theme', 'src', 'Providers', 'Theme', 'assets', 'scss');

module.exports = {
    // Additional load paths for the sass compiler
    sassLoadPaths: [parentScss],
};
```

This allows SCSS files to resolve cross-theme imports like `@use 'common/breakpoints' as *;`. Every SCSS file that uses shared mixins must include the explicit `@use` statement.

## Features

### WordPress Externals

Block editor scripts automatically map WordPress imports to globals:

```js
import { registerBlockType } from '@wordpress/blocks';
// Built to use: wp.blocks
```

### JSX Support

Block editor scripts support JSX syntax, built to use `wp.element.createElement`.

### Watch Mode

In watch mode, the script monitors both `assets/` and `blocks/` directories for each provider and rebuilds only the affected files.

| Mode | Minified | Source Maps |
|------|----------|-------------|
| Build | Yes | No |
| Watch | No | Yes |
