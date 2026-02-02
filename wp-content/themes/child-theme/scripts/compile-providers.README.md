# compile-providers.js

Compiles assets (SCSS, JS, blocks) from service provider directories.

## Purpose

Each service provider can have its own assets and blocks. This script auto-discovers and compiles them all, keeping provider functionality self-contained.

## Usage

```bash
# Build once
node scripts/compile-providers.js

# Watch mode (rebuilds on changes)
node scripts/compile-providers.js --watch

# Via npm
npm run compile-providers
npm run watch-providers
```

## How It Works

1. Scans `src/Providers/` for provider directories
2. For each provider, discovers:
   - SCSS entry point (`assets/scss/index.scss`)
   - JS files (`assets/js/*.js`)
   - Blocks (`blocks/*/`)
3. Compiles all discovered assets to `dist/`

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

## Features

### WordPress Externals

Block editor scripts automatically map WordPress imports to globals:

```js
import { registerBlockType } from '@wordpress/blocks';
// Compiled to use: wp.blocks
```

### JSX Support

Block editor scripts support JSX syntax. JSX is compiled to use WordPress's element system (`wp.element.createElement`) rather than React directly, ensuring compatibility with the WordPress block editor.

### Parent Theme Imports

SCSS files automatically have access to parent theme variables:
- `_breakpoints.scss`
- `_mixins.scss`

## Build Options

| Mode | Minified | Source Maps |
|------|----------|-------------|
| Build | Yes | No |
| Watch | No | Yes |

## Example Output

```
Compiling 2 provider(s)...

ProjectService:
  Block: projects
  Block Editor JS: dist/js/projects.js
  Block Style: dist/css/projects.css
  Block Editor Style: dist/css/projects-editor.css

ThemeService:
  SCSS: dist/css/theme-service.css
  JS: dist/js/theme-service/button.js
  Block: shutter-card
  Block Editor JS: dist/js/shutter-card.js
  Block Style: dist/css/shutter-card.css
  Block: shutter-cards
  Block Editor JS: dist/js/shutter-cards.js
  Block View JS: dist/js/shutter-cards-view.js
  Block Style: dist/css/shutter-cards.css
  Block Editor Style: dist/css/shutter-cards-editor.css

Done: 2 succeeded, 0 failed
```
