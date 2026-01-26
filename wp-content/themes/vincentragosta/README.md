# Vincent Ragosta Theme

A modern WordPress theme built with Timber/Twig templating and a hybrid block editor approach. This theme combines the flexibility of classic PHP templating for structural elements (header, footer) with the power of the WordPress block editor for page content.

## Requirements

- PHP 8.0+
- WordPress 6.4+
- Node.js 18+
- Composer

## Quick Start

```bash
# Install PHP dependencies
composer install

# Install Node dependencies
npm install

# Build assets (production)
npm run build

# Development mode (watch for changes)
npm run start
```

## Project Structure

```
vincentragosta/
├── assets/
│   └── src/
│       ├── js/                    # JavaScript source files
│       ├── scss/                  # SCSS source files
│       │   ├── common/            # Shared styles & utilities
│       │   │   ├── _breakpoints.scss  # Breakpoint variables & mixins
│       │   │   ├── _color-mode.scss   # Light/dark mode styles
│       │   │   ├── _function.scss     # SCSS functions
│       │   │   ├── _layout.scss       # Site layout styles
│       │   │   └── _animation.scss    # Animation utilities
│       │   ├── elements/          # Element-level styles
│       │   ├── layout/            # Layout components (header, etc.)
│       │   ├── utilities/         # Utility classes
│       │   └── main.scss          # Main stylesheet entry
├── blocks/                        # Custom Gutenberg blocks
│   ├── hero/                      # Hero block
│   ├── projects/                  # Projects grid block
│   ├── shutter-cards/             # Shutter cards container
│   ├── shutter-card/              # Individual shutter card
│   └── index.js                   # Block registration (editor)
├── dist/                          # Compiled assets (gitignored)
│   ├── blocks/                    # Compiled block JS/CSS
│   ├── css/                       # Compiled provider CSS
│   └── js/                        # Compiled theme JS
├── docs/                          # Documentation
│   ├── ARCHITECTURE.md            # Technical architecture
│   ├── BLOCKS.md                  # Block development guide
│   └── STYLING.md                 # CSS/SCSS conventions
├── src/                           # PHP source (PSR-4)
│   ├── Contracts/                 # Interfaces
│   ├── Providers/                 # Service providers
│   ├── Services/                  # Utility services
│   └── Theme.php                  # Main theme class
├── views/                         # Twig templates
│   ├── base.twig                  # Base layout template
│   ├── head.twig                  # HTML head
│   ├── header.twig                # Site header
│   ├── footer.twig                # Site footer
│   └── ...                        # Page templates
├── functions.php                  # Theme bootstrap
├── theme.json                     # Block editor configuration
├── webpack.config.js              # Custom webpack configuration
└── style.css                      # Compiled main stylesheet
```

## Architecture Overview

This theme uses a **hybrid architecture**:

| Layer | Technology | Purpose |
|-------|------------|---------|
| Templating | Timber/Twig | Header, footer, page structure |
| Page Content | Block Editor | Flexible content via Gutenberg |
| Design Tokens | theme.json | Colors, typography, spacing |
| Custom Blocks | React + PHP | Hero, projects, shutter cards |

### Why Hybrid?

- **Header/Footer in Twig**: Complex interactive elements (dark mode toggle, animated menu) are easier to maintain in traditional templates
- **Content in Blocks**: Editors get visual control over page layouts
- **Best of Both**: Developers maintain structural control while content creators have flexibility

## Build System

The theme uses `@wordpress/scripts` with a custom webpack configuration that auto-injects SCSS breakpoint mixins into all block stylesheets.

### Available Commands

| Command | Description |
|---------|-------------|
| `npm run build` | Production build (all assets) |
| `npm run start` | Development mode with watch |
| `npm run build-blocks` | Build blocks only |
| `npm run build-assets` | Build JS assets only |
| `npm run compile-theme-css` | Compile main stylesheet |

## Documentation

- [Architecture](docs/ARCHITECTURE.md) - Technical decisions and patterns
- [Custom Blocks](docs/BLOCKS.md) - Block development guide
- [Styling](docs/STYLING.md) - CSS methodology and conventions

## Design Tokens

All design tokens are defined in `theme.json` and exposed as CSS custom properties:

```css
/* Colors */
var(--wp--preset--color--base)
var(--wp--preset--color--contrast)
var(--wp--preset--color--accent-1)

/* Spacing */
var(--wp--preset--spacing--20)  /* 0.5rem */
var(--wp--preset--spacing--40)  /* 1rem */
var(--wp--preset--spacing--50)  /* 1.5rem */

/* Typography */
var(--wp--preset--font-size--body-small)
var(--wp--preset--font-size--body-large)
var(--wp--preset--font-family--reckless-neue)
```

## License

MIT
