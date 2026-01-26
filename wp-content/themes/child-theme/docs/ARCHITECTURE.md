# Architecture

This document explains the technical architecture and design decisions behind the Vincent Ragosta theme.

## Table of Contents

- [Parent/Child Theme Structure](#parentchild-theme-structure)
- [Hybrid Theme Approach](#hybrid-theme-approach)
- [PHP Architecture](#php-architecture)
- [Template Hierarchy](#template-hierarchy)
- [Service Provider Pattern](#service-provider-pattern)
- [Block Editor Integration](#block-editor-integration)
- [Build System](#build-system)

## Parent/Child Theme Structure

This theme uses a WordPress parent/child theme architecture:

```
themes/
├── parent-theme/           # Infrastructure & base classes
│   ├── src/
│   │   ├── Contracts/      # Interfaces (Registrable, HasAssets)
│   │   ├── Traits/         # Implementations (HasAssets)
│   │   ├── Providers/      # Base service providers
│   │   └── Theme.php       # Base theme class
│   └── vendor/             # Composer dependencies (Timber)
│
└── child-theme/            # Site-specific implementation
    ├── src/
    │   ├── Providers/      # Extended/custom providers
    │   ├── Services/       # Site-specific services
    │   └── Theme.php       # Extends ParentTheme\Theme
    ├── blocks/             # Custom Gutenberg blocks
    ├── views/              # Twig templates
    └── vendor/             # Child theme dependencies
```

### Why Parent/Child?

| Benefit | Description |
|---------|-------------|
| **Reusability** | Base classes can be used across multiple sites |
| **Separation** | Infrastructure vs. site-specific code |
| **Maintainability** | Update parent without touching child |
| **Inheritance** | Child extends and overrides as needed |

### Namespace Structure

```
ParentTheme\              # Parent theme namespace
├── Contracts\
├── Traits\
├── Providers\
└── Theme

ChildTheme\               # Child theme namespace
├── Providers\
├── Services\
└── Theme
```

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

All theme functionality is organized into service providers. The parent theme provides base classes and contracts:

### Contracts (from parent-theme)

```php
namespace ParentTheme\Contracts;

interface Registrable
{
    public function register(): void;
}

interface HasAssets
{
    public function enqueueStyle(string $handle, string $filename, array $deps = []): void;
    public function enqueueScript(string $handle, string $filename, array $deps = [], bool $inFooter = true): void;
}
```

### Base ServiceProvider (from parent-theme)

```php
namespace ParentTheme\Providers;

abstract class ServiceProvider implements Registrable, HasAssets
{
    use \ParentTheme\Traits\HasAssets;

    protected array $features = [];

    public function register(): void
    {
        $this->registerFeatures();
    }
}
```

### Child Theme Provider Example

```php
namespace ChildTheme\Providers;

use ParentTheme\Providers\AssetServiceProvider as BaseAssetServiceProvider;

class AssetServiceProvider extends BaseAssetServiceProvider
{
    protected string $handlePrefix = 'child-theme';

    public function register(): void
    {
        parent::register();
        add_action('wp_head', [$this, 'addFontPreconnects']);
    }

    public function enqueueFrontendAssets(): void
    {
        // Add Google Fonts
        wp_enqueue_style('fira-code-font', '...');

        // Call parent for standard assets
        parent::enqueueFrontendAssets();
    }
}
```

### Benefits

1. **Inheritance**: Extend parent providers, override only what's needed
2. **Separation of Concerns**: Each provider handles one aspect
3. **Testability**: Providers can be unit tested in isolation
4. **Organization**: Clear structure for finding code

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
