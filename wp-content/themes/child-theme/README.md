# Vincent Ragosta Theme

A WordPress child theme extending `parent-theme` with custom blocks, Timber/Twig templating, and a modern build system.

## Requirements

- PHP 8.1+
- WordPress 6.0+
- Node.js 18+
- Composer
- Docker/DDEV (for local development)

## Quick Start

```bash
# Install all dependencies (both parent and child themes)
make install

# Build assets
make build

# Start development mode (watch for changes)
make watch
```

## Available Commands

| Command | Description |
|---------|-------------|
| `make install` | Install Composer & npm dependencies for both themes |
| `make build` | Build all child theme assets |
| `make watch` | Start watch mode for development |
| `make clean` | Remove all generated files |
| `make autoload` | Regenerate Composer autoloaders |

## Project Structure

```
child-theme/
├── assets/
│   └── src/
│       ├── js/                    # JavaScript source files
│       └── scss/                  # SCSS source files
│           ├── common/            # Shared styles & utilities
│           ├── elements/          # Element-level styles
│           ├── layout/            # Layout components
│           ├── utilities/         # Utility classes
│           └── main.scss          # Main stylesheet (→ style.css)
├── blocks/                        # Custom Gutenberg blocks
│   ├── hero/
│   ├── projects/
│   ├── shutter-card/
│   └── shutter-cards/
├── config/                        # JSON configuration files
│   └── *.json                     # Post type definitions
├── dist/                          # Compiled assets (gitignored)
│   ├── blocks/                    # Compiled block assets
│   ├── css/                       # Provider CSS
│   └── js/                        # Provider JS
├── scripts/                       # Build scripts
│   ├── build-block-views.js       # Compiles block view scripts
│   └── compile-providers.js       # Compiles provider SCSS/JS
├── src/
│   ├── Providers/
│   │   ├── ServiceProvider.php           # Base (extends parent)
│   │   ├── AssetServiceProvider.php      # Frontend/editor assets
│   │   ├── PostTypeServiceProvider.php   # CPT registration
│   │   ├── TwigServiceProvider.php       # Twig functions
│   │   ├── BlockService/
│   │   │   ├── BlockServiceProvider.php  # Block registration
│   │   │   ├── Features/
│   │   │   └── assets/                   # Provider-specific assets
│   │   └── ThemeService/
│   │       └── ThemeServiceProvider.php  # Theme setup
│   ├── Services/
│   │   └── IconService.php               # SVG icon handling
│   └── Theme.php                         # Main theme class
├── templates/                     # Twig templates
├── views/                         # Twig views
│   ├── base.twig
│   ├── header.twig
│   └── footer.twig
├── composer.json
├── functions.php
├── Makefile
├── package.json
├── style.css
└── theme.json                     # Block editor configuration
```

## Architecture

This theme uses a **hybrid architecture** combining:

| Layer | Technology | Purpose |
|-------|------------|---------|
| Infrastructure | parent-theme | Base classes, contracts, traits |
| Templating | Timber/Twig | Header, footer, page structure |
| Page Content | Block Editor | Flexible content via Gutenberg |
| Design Tokens | theme.json | Colors, typography, spacing |
| Custom Blocks | React + PHP | Hero, projects, shutter cards |

### Parent Theme Relationship

This theme extends `parent-theme` which provides:

- **Contracts:** `Registrable`, `HasAssets`
- **Traits:** `HasAssets` (asset enqueueing)
- **Base Classes:** `ServiceProvider`, `Theme`
- **Common Providers:** Can be extended or overridden

The parent theme's autoloader is loaded in `functions.php` before the child's.

## Service Providers

| Provider | Purpose |
|----------|---------|
| `ThemeServiceProvider` | Theme setup, admin bar, theme CSS |
| `AssetServiceProvider` | Frontend/editor assets, Google Fonts |
| `BlockServiceProvider` | Block registration, editor data |
| `PostTypeServiceProvider` | CPTs from JSON config |
| `TwigServiceProvider` | Custom Twig functions (`icon()`) |

## Custom Blocks

| Block | Description |
|-------|-------------|
| `child-theme/hero` | Hero with video background or SVG |
| `child-theme/projects` | Latest or curated projects grid |
| `child-theme/shutter-cards` | Interactive expanding cards container |
| `child-theme/shutter-card` | Individual card item |

## Build System

### Asset Compilation Flow

| Source | Output |
|--------|--------|
| `assets/src/scss/main.scss` | `style.css` |
| `src/Providers/*/assets/scss/*.scss` | `dist/css/{provider}.css` |
| `src/Providers/*/assets/js/*.js` | `dist/js/{provider}/*.js` |
| `blocks/*/index.js` | `dist/blocks/` |
| `blocks/*/view.js` | `dist/blocks/*-view.js` |

### Adding a Provider with Assets

1. Create `src/Providers/YourService/YourServiceProvider.php`
2. Add `assets/scss/main.scss` and/or `assets/js/*.js`
3. Run `make build` — assets are auto-discovered

## Development

### Adding a New Block

1. Create `blocks/{block-name}/` with `block.json`, `edit.js`, `index.js`
2. Add `style.scss` and/or `editor.scss` for styles
3. Add `view.js` for frontend interactivity (optional)
4. Register in `BlockServiceProvider::$blocks`
5. Run `make build`

### Adding Icons

- **Sprite icons:** Add SVG to `assets/images/svg-sprite/`
- **Standalone SVGs:** Add to `assets/images/svg/`
- **Twig:** `{{ icon('icon-name') }}`
- **PHP:** `new IconService('icon-name')`

## Design Tokens

Defined in `theme.json` and available as CSS custom properties:

```css
/* Colors */
var(--wp--preset--color--base)
var(--wp--preset--color--contrast)
var(--wp--preset--color--accent-1)

/* Spacing */
var(--wp--preset--spacing--40)  /* 1rem */
var(--wp--preset--spacing--50)  /* 1.5rem */

/* Typography */
var(--wp--preset--font-size--body-large)
var(--wp--preset--font-family--reckless-neue)
```

## License

MIT
