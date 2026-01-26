# Architecture

This document explains the technical architecture and design decisions behind the Vincent Ragosta theme.

## Table of Contents

- [Hybrid Theme Approach](#hybrid-theme-approach)
- [PHP Architecture](#php-architecture)
- [Template Hierarchy](#template-hierarchy)
- [Service Provider Pattern](#service-provider-pattern)
- [Block Editor Integration](#block-editor-integration)
- [Build System](#build-system)

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

### Why Not Full FSE?

| Aspect | FSE Approach | Our Hybrid Approach |
|--------|--------------|---------------------|
| Header/Footer | Block patterns (editable) | Twig templates (controlled) |
| Page Content | Blocks | Blocks |
| Design Tokens | theme.json | theme.json |
| Complex Interactions | Difficult | Native PHP/JS |
| Maintenance | User can break | Developer controlled |

## PHP Architecture

### Directory Structure

```
src/
├── Contracts/
│   └── Registrable.php          # Interface for service providers
├── Providers/
│   ├── AssetServiceProvider.php        # Enqueues styles/scripts
│   ├── BlockService/
│   │   ├── BlockServiceProvider.php    # Registers custom blocks
│   │   └── Features/
│   │       └── ButtonIconEnhancer.php  # Block modifications
│   ├── PostTypeServiceProvider.php     # Custom post types
│   ├── ThemeService/
│   │   ├── Provider.php                # Theme setup
│   │   └── Features/
│   │       └── DisableComments.php     # Feature modules
│   └── TwigServiceProvider.php         # Twig extensions
├── Services/
│   └── IconService.php          # SVG icon rendering
└── Theme.php                    # Main bootstrap class
```

### Bootstrap Flow

```php
// functions.php
require_once __DIR__ . '/vendor/autoload.php';
Timber\Timber::init();
new Theme();

// Theme.php
class Theme extends Site
{
    protected array $providers = [
        Providers\ThemeService\Provider::class,
        Providers\AssetServiceProvider::class,
        Providers\BlockService\BlockServiceProvider::class,
        Providers\PostTypeServiceProvider::class,
        Providers\TwigServiceProvider::class,
    ];

    public function __construct()
    {
        Timber::$dirname = ['templates', 'views', 'blocks'];
        $this->registerAll($this->providers);
        parent::__construct();
    }
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

```twig
{# page.twig - Extends base #}
{% extends "base.twig" %}

{% block content %}
    <article>
        {{ post.content }}
    </article>
{% endblock %}
```

## Service Provider Pattern

All theme functionality is organized into service providers that implement the `Registrable` interface:

```php
namespace ChildTheme\Contracts;

interface Registrable
{
    public function register(): void;
}
```

### Example Provider

```php
namespace ChildTheme\Providers;

use ChildTheme\Contracts\Registrable;

class AssetServiceProvider implements Registrable
{
    public function register(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueStyles']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueScripts']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueueEditorAssets']);
    }

    public function enqueueStyles(): void
    {
        wp_enqueue_style('theme-style', get_stylesheet_uri());
    }

    // ...
}
```

### Benefits

1. **Separation of Concerns**: Each provider handles one aspect of the theme
2. **Testability**: Providers can be unit tested in isolation
3. **Maintainability**: Easy to add/remove functionality
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
public function register(): void
{
    add_action('init', [$this, 'registerBlocks']);
}

public function registerBlocks(): void
{
    $blocks = ['hero', 'projects', 'shutter-cards', 'shutter-card'];

    foreach ($blocks as $block) {
        register_block_type(__DIR__ . "/../../blocks/{$block}");
    }
}
```

### Custom Block Structure

Each block follows this structure:

```
blocks/hero/
├── block.json      # Block metadata & registration
├── index.js        # Editor component (React)
├── edit.js         # Editor UI
├── save.js         # Save function (or null for dynamic)
├── render.php      # Server-side rendering
├── style.scss      # Frontend styles
└── editor.scss     # Editor-only styles
```

## Build System

### Webpack Configuration

The theme extends `@wordpress/scripts` with custom configuration:

```javascript
// webpack.config.js
const defaultConfig = require('@wordpress/scripts/config/webpack.config');

module.exports = {
  ...defaultConfig,
  module: {
    ...defaultConfig.module,
    rules: defaultConfig.module.rules.map((rule) => {
      if (rule.test && rule.test.toString().includes('(sc|sa)ss')) {
        return {
          ...rule,
          use: rule.use.map((loader) => {
            if (loader.loader?.includes('sass-loader')) {
              return {
                ...loader,
                options: {
                  ...loader.options,
                  additionalData: `@use "${breakpointsPath}" as *;\n`,
                },
              };
            }
            return loader;
          }),
        };
      }
      return rule;
    }),
  },
};
```

### Why Custom Webpack?

The custom configuration auto-injects the `_breakpoints.scss` file into all block stylesheets, eliminating the need for `@use` statements in every file:

```scss
// Before: Required in every block file
@use '../../assets/src/scss/common/breakpoints' as *;

.hero {
  @include container(hero, md) { ... }
}

// After: Auto-injected by webpack
.hero {
  @include container(hero, md) { ... }
}
```

### Build Outputs

All compiled assets output to the `dist/` directory:

| Input | Output | Command |
|-------|--------|---------|
| `blocks/index.js` | `dist/blocks/index.js` | `npm run build-blocks` |
| `blocks/*/style.scss` | `dist/blocks/style-index.css` | `npm run build-blocks` |
| `blocks/*/view.js` | `dist/blocks/{block}-view.js` | `npm run build-block-views` |
| `assets/src/js/*.js` | `dist/js/*.js` | `npm run build-assets` |
| `assets/src/scss/main.scss` | `style.css` | `npm run compile-theme-css` |
| `src/Providers/*/assets/scss/` | `dist/css/{provider}.css` | `npm run compile-providers` |
