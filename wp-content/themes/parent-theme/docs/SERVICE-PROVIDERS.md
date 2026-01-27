# Service Providers

This document covers the service provider pattern implemented in the parent theme, including contracts, traits, and extension patterns.

## Table of Contents

- [Overview](#overview)
- [Contracts](#contracts)
- [Traits](#traits)
- [Base Classes](#base-classes)
- [Creating Providers](#creating-providers)
- [Features System](#features-system)

## Overview

The service provider pattern organizes theme functionality into discrete, testable units. Each provider is responsible for a single aspect of the theme (assets, blocks, post types, etc.).

```
┌─────────────────────────────────────────────────────────┐
│                    Theme Bootstrap                       │
├─────────────────────────────────────────────────────────┤
│  foreach ($providers as $provider) {                    │
│      (new $provider())->register();                     │
│  }                                                      │
└─────────────────────────────────────────────────────────┘
         │              │              │
         ▼              ▼              ▼
┌─────────────┐ ┌─────────────┐ ┌─────────────┐
│   Assets    │ │   Blocks    │ │  PostTypes  │
│  Provider   │ │  Provider   │ │  Provider   │
└─────────────┘ └─────────────┘ └─────────────┘
```

## Contracts

### Registrable

All providers must implement the `Registrable` interface:

```php
namespace ParentTheme\Contracts;

interface Registrable
{
    /**
     * Register the service provider.
     *
     * This method is called during theme bootstrap.
     * Use it to add hooks, filters, and register features.
     */
    public function register(): void;
}
```

### HasAssets

Providers that enqueue styles or scripts should implement `HasAssets`:

```php
namespace ParentTheme\Contracts;

interface HasAssets
{
    /**
     * Enqueue a stylesheet.
     *
     * @param string $handle   Unique handle (will be prefixed)
     * @param string $filename Filename relative to dist/css/
     * @param array  $deps     Dependencies
     */
    public function enqueueStyle(string $handle, string $filename, array $deps = []): void;

    /**
     * Enqueue a script.
     *
     * @param string $handle   Unique handle (will be prefixed)
     * @param string $filename Filename relative to dist/js/
     * @param array  $deps     Dependencies
     * @param bool   $inFooter Load in footer
     */
    public function enqueueScript(
        string $handle,
        string $filename,
        array $deps = [],
        bool $inFooter = true
    ): void;
}
```

## Traits

### HasAssets Trait

The `HasAssets` trait provides the implementation for the `HasAssets` interface:

```php
namespace ParentTheme\Traits;

trait HasAssets
{
    protected string $handlePrefix = 'parent-theme';

    public function enqueueStyle(string $handle, string $filename, array $deps = []): void
    {
        $prefixed_handle = $this->getSlug() . '-' . $handle;
        $file_path = get_stylesheet_directory() . '/dist/css/' . $filename;
        $file_uri = get_stylesheet_directory_uri() . '/dist/css/' . $filename;

        if (file_exists($file_path)) {
            wp_enqueue_style(
                $prefixed_handle,
                $file_uri,
                $deps,
                filemtime($file_path)
            );
        }
    }

    public function enqueueScript(
        string $handle,
        string $filename,
        array $deps = [],
        bool $inFooter = true
    ): void {
        $prefixed_handle = $this->getSlug() . '-' . $handle;
        $file_path = get_stylesheet_directory() . '/dist/js/' . $filename;
        $file_uri = get_stylesheet_directory_uri() . '/dist/js/' . $filename;

        if (file_exists($file_path)) {
            wp_enqueue_script(
                $prefixed_handle,
                $file_uri,
                $deps,
                filemtime($file_path),
                $inFooter
            );
        }
    }

    protected function getSlug(): string
    {
        // Converts "BlockServiceProvider" to "block-service"
        $class = (new \ReflectionClass($this))->getShortName();
        $class = str_replace('ServiceProvider', '', $class);
        $class = str_replace('Provider', '', $class);
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $class));
    }
}
```

#### Slug Generation

The trait automatically generates a slug from the provider class name:

| Class Name | Generated Slug |
|------------|----------------|
| `AssetServiceProvider` | `asset` |
| `BlockServiceProvider` | `block` |
| `ThemeServiceProvider` | `theme` |
| `CustomFeatureProvider` | `custom-feature` |

## Base Classes

### ServiceProvider

The abstract `ServiceProvider` class combines the contract and trait:

```php
namespace ParentTheme\Providers;

use ParentTheme\Contracts\Registrable;
use ParentTheme\Contracts\HasAssets as HasAssetsContract;
use ParentTheme\Traits\HasAssets;

abstract class ServiceProvider implements Registrable, HasAssetsContract
{
    use HasAssets;

    /**
     * Features to register with this provider.
     *
     * Each feature class must implement Registrable.
     */
    protected array $features = [];

    public function register(): void
    {
        $this->registerFeatures();
    }

    protected function registerFeatures(): void
    {
        foreach ($this->features as $feature) {
            if (class_exists($feature)) {
                (new $feature())->register();
            }
        }
    }
}
```

### Theme

The base `Theme` class bootstraps all providers:

```php
namespace ParentTheme;

class Theme
{
    protected array $providers = [];

    public function __construct()
    {
        $this->boot();
    }

    public function boot(): void
    {
        foreach ($this->providers as $provider) {
            if (class_exists($provider)) {
                (new $provider())->register();
            }
        }
    }
}
```

## Creating Providers

### Basic Provider

```php
namespace ChildTheme\Providers;

use ParentTheme\Providers\ServiceProvider;

class MyFeatureProvider extends ServiceProvider
{
    public function register(): void
    {
        parent::register();

        add_action('init', [$this, 'init']);
        add_filter('some_filter', [$this, 'filterSomething']);
    }

    public function init(): void
    {
        // Initialization logic
    }

    public function filterSomething($value): mixed
    {
        // Filter logic
        return $value;
    }
}
```

### Extending Parent Providers

```php
namespace ChildTheme\Providers;

use ParentTheme\Providers\AssetServiceProvider as BaseProvider;

class AssetServiceProvider extends BaseProvider
{
    public function register(): void
    {
        // Call parent first
        parent::register();

        // Add child-specific hooks
        add_action('wp_head', [$this, 'addFontPreconnects'], 1);
    }

    public function enqueueFrontendAssets(): void
    {
        // Add custom assets
        wp_enqueue_style('google-fonts', 'https://fonts.googleapis.com/...');

        // Then call parent
        parent::enqueueFrontendAssets();
    }

    public function addFontPreconnects(): void
    {
        echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
    }
}
```

### Provider with Assets

```php
namespace ChildTheme\Providers;

use ParentTheme\Providers\ServiceProvider;

class BlockServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        parent::register();

        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('init', [$this, 'registerBlocks']);
    }

    public function enqueueAssets(): void
    {
        // Uses HasAssets trait methods
        $this->enqueueStyle('main', 'block-service.css');
        $this->enqueueScript('button', 'block-service/button.js');
    }

    public function registerBlocks(): void
    {
        register_block_type(get_stylesheet_directory() . '/blocks/hero');
    }
}
```

## Features System

Providers can register "features" - smaller classes that implement specific functionality:

### Defining Features

```php
namespace ChildTheme\Providers\BlockService;

use ParentTheme\Providers\ServiceProvider;

class BlockServiceProvider extends ServiceProvider
{
    protected array $features = [
        Features\ButtonIconEnhancer::class,
        Features\BlockPatterns::class,
    ];

    public function register(): void
    {
        parent::register(); // This calls registerFeatures()

        // Additional provider logic...
    }
}
```

### Creating a Feature

```php
namespace ChildTheme\Providers\BlockService\Features;

use ParentTheme\Contracts\Registrable;

class ButtonIconEnhancer implements Registrable
{
    public function register(): void
    {
        add_filter('render_block_core/button', [$this, 'addIconToButton'], 10, 2);
    }

    public function addIconToButton(string $content, array $block): string
    {
        // Add icon logic...
        return $content;
    }
}
```

### Feature Directory Structure

```
src/Providers/
├── BlockService/
│   ├── BlockServiceProvider.php
│   ├── assets/
│   │   ├── scss/main.scss
│   │   └── js/button.js
│   └── Features/
│       ├── ButtonIconEnhancer.php
│       └── BlockPatterns.php
└── ThemeService/
    ├── ThemeServiceProvider.php
    └── Features/
        └── ColorModeToggle.php
```

## Best Practices

### 1. Single Responsibility

Each provider should handle one aspect of the theme:

```php
// Good
class BlockServiceProvider { }   // Handles blocks
class AssetServiceProvider { }   // Handles assets
class PostTypeServiceProvider { } // Handles CPTs

// Avoid
class EverythingProvider { }     // Too broad
```

### 2. Call Parent Methods

Always call parent methods when extending:

```php
public function register(): void
{
    parent::register(); // Don't forget this!
    // Your code...
}
```

### 3. Use Hooks Appropriately

Register hooks in `register()`, implement logic in separate methods:

```php
// Good
public function register(): void
{
    add_action('init', [$this, 'registerPostTypes']);
}

public function registerPostTypes(): void
{
    // Implementation
}

// Avoid - logic in register()
public function register(): void
{
    register_post_type('project', [...]);
}
```

### 4. Keep Features Small

Features should do one thing:

```php
// Good - focused feature
class ButtonIconEnhancer implements Registrable { }

// Avoid - too broad
class ButtonEnhancements implements Registrable { }
```
