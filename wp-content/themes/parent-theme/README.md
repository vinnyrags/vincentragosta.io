# Parent Theme

A foundational WordPress parent theme providing PHP infrastructure for child themes.

## Overview

This theme provides reusable base classes and support systems that child themes extend. It handles common WordPress functionality like:

- Service provider pattern with dependency injection (PHP-DI)
- Asset, block, feature, and Twig function management via `Provider`
- Post type registration from JSON config
- Timber/Twig integration
- Comment/post disabling, SVG upload support

## Requirements

- PHP 8.1+
- WordPress 6.0+
- Composer
- Node.js 18+

## Installation

```bash
composer install
npm install
```

## Directory Structure

```
parent-theme/
├── src/
│   ├── Models/
│   │   └── Post.php                  # Base post model (extends Timber\Post)
│   ├── Providers/
│   │   ├── Contracts/
│   │   │   └── Registrable.php       # Interface for registrable classes
│   │   ├── Support/
│   │   │   ├── Asset/AssetManager    # Asset enqueueing helpers
│   │   │   ├── Block/BlockManager    # Block registration helpers
│   │   │   └── Feature/FeatureManager # Feature registration with inheritance
│   │   ├── PostType/
│   │   │   └── PostTypeProvider.php  # JSON-based CPT registration
│   │   ├── Theme/
│   │   │   ├── ThemeProvider.php     # Theme supports, assets, Twig functions
│   │   │   └── Features/            # DisableBlocks, DisableComments, etc.
│   │   └── Provider.php              # Abstract base provider
│   ├── Repositories/
│   │   ├── Repository.php            # Base repository class
│   │   └── RepositoryInterface.php
│   ├── Services/
│   │   └── IconService.php           # SVG icon handling
│   ├── config/
│   │   └── container.php             # DI container definitions
│   └── Theme.php                     # Base theme class (extends Timber\Site)
├── composer.json
├── functions.php
├── style.css
└── index.php
```

## Creating a Child Theme

### 1. Create Theme Directory

```
child-theme/
├── style.css
├── functions.php
├── composer.json
└── src/
    └── Theme.php
```

### 2. Configure style.css

```css
/*
 * Theme Name: My Child Theme
 * Template: parent-theme
 */
```

### 3. Set Up Autoloading (composer.json)

```json
{
  "autoload": {
    "psr-4": {
      "ChildTheme\\": "src/"
    }
  }
}
```

### 4. Create Theme Class

```php
<?php
// src/Theme.php
namespace ChildTheme;

use ChildTheme\Providers\Theme\ThemeProvider;
use ParentTheme\Theme as BaseTheme;

class Theme extends BaseTheme
{
    protected array $providers = [
        ThemeProvider::class,
    ];

    protected function getContainerDefinitions(): array
    {
        return array_merge(parent::getContainerDefinitions(), [
            get_stylesheet_directory() . '/src/config/container.php',
        ]);
    }
}
```

### 5. Bootstrap in functions.php

```php
<?php
// Load parent theme's autoloader
$parent_autoloader = get_template_directory() . '/vendor/autoload.php';
if (file_exists($parent_autoloader)) {
    require_once $parent_autoloader;
}

// Load child theme's autoloader
require_once __DIR__ . '/vendor/autoload.php';

(new \ChildTheme\Theme())->bootstrap();
```

---

## Service Provider Pattern

The service provider pattern organizes theme functionality into discrete, testable modules. Each provider is a self-contained unit with its own assets, blocks, features, and Twig functions.

```
┌─────────────────────────────────────────────────────────┐
│                    Theme Bootstrap                       │
├─────────────────────────────────────────────────────────┤
│  Container resolves each provider via autowiring,       │
│  then calls $provider->register()                       │
└─────────────────────────────────────────────────────────┘
         │              │              │
         ▼              ▼              ▼
┌─────────────┐ ┌─────────────┐ ┌─────────────┐
│   Theme     │ │  PostType   │ │  Project    │
│  Provider   │ │  Provider   │ │  Provider   │
└─────────────┘ └─────────────┘ └─────────────┘
```

### Provider Base Class

All providers extend `Provider`, which composes:

- **AssetManager** — style/script enqueueing with slug-based paths
- **BlockManager** — Gutenberg block registration and editor asset hooks
- **FeatureManager** — feature class registration with inheritance and opt-out

Every provider automatically gets these capabilities. The base `register()` method wires up features, blocks, and a `timber/twig` filter:

```php
use ParentTheme\Providers\Provider;

class MyProvider extends Provider
{
    protected array $features = [
        MyFeature::class,
    ];

    protected array $blocks = [
        'my-block',
    ];

    public function register(): void
    {
        add_action('init', [$this, 'doSomething']);
        parent::register(); // registers features, blocks, and twig filter
    }
}
```

### Dependency Injection

Providers receive a `DI\Container` via constructor injection. The container uses autowiring — no manual definitions needed for most classes. Container definitions can be added in `src/config/container.php`.

### Features

Features are smaller, focused classes that implement `Registrable`. They're listed in a provider's `$features` array and automatically instantiated via the container.

Features are inherited from parent providers. Child providers only declare their own, and can opt out with `=> false`:

```php
protected array $features = [
    MyChildFeature::class,
    SomeParentFeature::class => false,  // disable inherited feature
];
```

### Twig Functions

Any provider can register Twig functions by overriding `addTwigFunctions()`:

```php
use Twig\Environment;
use Twig\TwigFunction;

public function addTwigFunctions(Environment $twig): Environment
{
    $twig = parent::addTwigFunctions($twig);

    $twig->addFunction(new TwigFunction('my_func', function (string $arg) {
        return strtoupper($arg);
    }));

    return $twig;
}
```

### Extending Parent Providers

```php
namespace ChildTheme\Providers\Theme;

use ParentTheme\Providers\Theme\ThemeProvider as BaseThemeProvider;

class ThemeProvider extends BaseThemeProvider
{
    protected string $handlePrefix = 'child-theme';

    public function enqueueFrontendAssets(): void
    {
        wp_enqueue_style('google-fonts', 'https://fonts.googleapis.com/...');
        parent::enqueueFrontendAssets();
    }
}
```

### Asset Compilation

Assets live inside each provider's directory. The build system auto-discovers them:

| Source | Output |
|--------|--------|
| `src/Providers/*/assets/scss/index.scss` | `dist/css/{slug}.css` |
| `src/Providers/*/assets/js/*.js` | `dist/js/{slug}/*.js` |
| `src/Providers/*/blocks/*/editor/index.js` | `dist/js/{block}.js` |
| `src/Providers/*/blocks/*/frontend/view.js` | `dist/js/{block}-view.js` |
| `src/Providers/*/blocks/*/frontend/style.scss` | `dist/css/{block}.css` |

Slug is derived from the provider class name minus "Provider" suffix (e.g., `ThemeProvider` -> `theme`).

---

## Testing

Uses PHPUnit 9 with WorDBless for WordPress function stubs.

### Test Structure

```
tests/
├── bootstrap.php
├── Support/
│   └── HasContainer.php        # Test helper for DI container
├── Unit/
│   ├── Models/
│   ├── Providers/Support/      # AssetManager, BlockManager, FeatureManager
│   ├── Repositories/
│   └── Services/
└── Integration/
    ├── Models/
    ├── Providers/
    ├── Repositories/
    └── ThemeTest.php
```

### Running Tests

```bash
composer test            # All tests
composer test:unit       # Unit tests only
composer test:integration # Integration tests only
```

---

## License

MIT
