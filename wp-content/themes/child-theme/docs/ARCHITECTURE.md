# Architecture

This document explains the technical architecture and design decisions behind the Vincent Ragosta theme (child-theme).

For documentation on the parent theme's base infrastructure (contracts, traits, service providers), see the [parent-theme docs](../../parent-theme/docs/).

## Table of Contents

- [Overview](#overview)
- [Hybrid Theme Approach](#hybrid-theme-approach)
- [PHP Architecture](#php-architecture)
- [Template Hierarchy](#template-hierarchy)
- [Block Editor Integration](#block-editor-integration)
- [Build System](#build-system)

## Overview

This child theme extends `parent-theme` to implement the Vincent Ragosta portfolio site:

```
child-theme/
├── src/
│   ├── Providers/          # Extended service providers
│   │   ├── AssetServiceProvider.php
│   │   ├── BlockService/
│   │   ├── PostTypeServiceProvider.php
│   │   ├── ThemeService/
│   │   └── TwigServiceProvider.php
│   ├── Services/
│   │   └── IconService.php
│   └── Theme.php           # Extends ParentTheme\Theme
├── blocks/                 # Custom Gutenberg blocks
├── views/                  # Twig templates
└── vendor/                 # Composer dependencies
```

It inherits infrastructure from `parent-theme`:
- **Contracts**: `Registrable`, `HasAssets` interfaces
- **Traits**: `HasAssets` for asset enqueueing
- **Base Classes**: `Theme`, `ServiceProvider`

## Hybrid Theme Approach

### The Problem

WordPress Full Site Editing (FSE) offers powerful visual editing capabilities, but presents challenges for complex themes:

1. **Interactive Components**: Elements like animated menus, dark mode toggles, and complex headers are difficult to maintain in block patterns
2. **Developer Control**: FSE templates can be overwritten by users, making maintenance unpredictable
3. **Performance**: Some interactive features require specific JavaScript initialization that's easier to control in traditional templates

### The Solution

This theme uses a **hybrid approach**:

```
┌─────────────────────────────────────────────────────────┐
│                     WordPress                            │
├─────────────────────────────────────────────────────────┤
│  Timber/Twig Templates          │  Block Editor Content  │
│  ─────────────────────          │  ───────────────────── │
│  • base.twig (layout)           │  • Page content        │
│  • header.twig                  │  • Custom blocks       │
│  • footer.twig                  │  • Core blocks         │
│  • head.twig                    │                        │
├─────────────────────────────────────────────────────────┤
│                    theme.json                            │
│            (Shared design tokens & settings)             │
└─────────────────────────────────────────────────────────┘
```

## PHP Architecture

### Directory Structure

```
src/
├── Providers/
│   ├── ServiceProvider.php              # Base (extends parent)
│   ├── AssetServiceProvider.php         # Enqueues styles/scripts
│   ├── PostTypeServiceProvider.php      # Custom post types
│   ├── TwigServiceProvider.php          # Twig extensions
│   ├── BlockService/
│   │   ├── BlockServiceProvider.php     # Registers custom blocks
│   │   ├── assets/                      # Provider-specific assets
│   │   └── Features/
│   │       └── ButtonIconEnhancer.php   # Block modifications
│   └── ThemeService/
│       └── ThemeServiceProvider.php     # Theme setup
├── Services/
│   └── IconService.php                  # SVG icon rendering
└── Theme.php                            # Main bootstrap class
```

### Bootstrap Flow

```php
// functions.php

// 1. Load parent theme's autoloader (provides ParentTheme namespace)
$parent_autoloader = get_template_directory() . '/vendor/autoload.php';
if (file_exists($parent_autoloader)) {
    require_once $parent_autoloader;
}

// 2. Load child theme's autoloader (provides ChildTheme namespace)
require_once __DIR__ . '/vendor/autoload.php';

// 3. Initialize Timber
Timber\Timber::init();

// 4. Bootstrap the theme
new \ChildTheme\Theme();
```

```php
// Theme.php
namespace ChildTheme;

use ParentTheme\Theme as BaseTheme;

class Theme extends BaseTheme
{
    protected array $providers = [
        Providers\ThemeService\ThemeServiceProvider::class,
        Providers\AssetServiceProvider::class,
        Providers\BlockService\BlockServiceProvider::class,
        Providers\PostTypeServiceProvider::class,
        Providers\TwigServiceProvider::class,
    ];
}
```

## Template Hierarchy

### Twig Templates

```
views/
├── base.twig              # Base layout (extends nothing)
├── head.twig              # <head> content (included)
├── header.twig            # Site header (included)
├── footer.twig            # Site footer (included)
├── page.twig              # Single page (extends base)
├── single.twig            # Single post (extends base)
├── archive.twig           # Archive pages (extends base)
├── index.twig             # Blog index (extends base)
├── 404.twig               # Not found (extends base)
└── partial/
    └── pagination.twig    # Reusable partials
```

### Template Inheritance

```twig
{# base.twig - The foundation #}
{% block html_head_container %}
    {% include 'head.twig' %}
    {% block head %}{% endblock %}
    </head>
{% endblock %}

<body {{ function('body_class') }}>
    {{ function('wp_body_open') }}

    {% include 'header.twig' %}

    <main id="wp--skip-link--target" class="site-main">
        {% block content %}
            {{ post.content }}
        {% endblock %}
    </main>

    {% block footer %}
        <footer id="footer">
            {% include 'footer.twig' %}
        </footer>
        {{ function('wp_footer') }}
    {% endblock %}
</body>
```

## Service Provider Pattern

All theme functionality is organized into service providers that extend base classes from `parent-theme`.

For detailed documentation on contracts, traits, and base classes, see [parent-theme Service Providers](../../parent-theme/docs/SERVICE-PROVIDERS.md).

### Child Theme Providers

| Provider | Responsibility |
|----------|----------------|
| `AssetServiceProvider` | Enqueues styles, scripts, fonts |
| `BlockServiceProvider` | Registers custom Gutenberg blocks |
| `PostTypeServiceProvider` | Registers custom post types |
| `TwigServiceProvider` | Adds Twig filters and functions |
| `ThemeServiceProvider` | Theme setup and features |

## Block Editor Integration

### How Blocks Work With Timber

WordPress renders block content as HTML, which Timber receives via `{{ post.content }}`. Custom blocks use server-side rendering (PHP) which integrates seamlessly:

```
Editor (React) → Save to DB → Render (PHP) → Timber → Twig
```

### Block Registration

Blocks are registered in `BlockServiceProvider`:

```php
protected array $blocks = [
    'hero',
    'projects',
    'shutter-cards',
    'shutter-card',
];

public function registerBlocks(): void
{
    foreach ($this->blocks as $block) {
        $block_dir = get_stylesheet_directory() . '/blocks/' . $block;
        if (file_exists($block_dir . '/block.json')) {
            register_block_type($block_dir);
        }
    }
}
```

### Custom Block Structure

```
blocks/hero/
├── block.json      # Block metadata & registration
├── index.js        # Editor component entry
├── edit.js         # Editor UI (React)
├── save.js         # Save function (or null for dynamic)
├── render.php      # Server-side rendering
├── style.scss      # Frontend styles
└── editor.scss     # Editor-only styles
```

## Build System

### Overview

The build system uses a combination of tools:

| Tool | Purpose |
|------|---------|
| `@wordpress/scripts` | Block compilation |
| `sass` | SCSS compilation |
| `esbuild` | Provider JS compilation |
| Custom scripts | Auto-discovery & compilation |

### Build Outputs

```
dist/
├── blocks/                    # Compiled block assets
│   ├── index.js              # All blocks JS
│   ├── style-index.css       # All blocks frontend CSS
│   └── *-view.js             # Individual view scripts
├── css/                       # Provider CSS
│   ├── block-service.css
│   └── theme-service.css
└── js/                        # Provider JS
    └── block-service/
        └── button.js
```

### Makefile Commands

```bash
make install    # Install all dependencies
make build      # Build all assets
make watch      # Development mode
make clean      # Remove generated files
make autoload   # Regenerate autoloaders
```

### Provider Asset Auto-Discovery

The `compile-providers.js` script automatically discovers and compiles:

- `src/Providers/*/assets/scss/main.scss` → `dist/css/{provider}.css`
- `src/Providers/*/assets/js/*.js` → `dist/js/{provider}/*.js`

No configuration needed—just add assets to the provider directory.

### Webpack Configuration

Custom webpack config auto-injects breakpoint mixins into all block SCSS:

```javascript
// webpack.config.js
additionalData: `@use "${breakpointsPath}" as *;\n`,
```

This eliminates the need for `@use` statements in every block file.
