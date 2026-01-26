# Parent Theme

A foundational WordPress parent theme providing PHP infrastructure for child themes.

## Overview

This theme provides reusable contracts, traits, and base classes that child themes can extend. It handles common WordPress functionality like:

- Theme supports registration
- Asset enqueueing infrastructure
- Post type registration from JSON config
- Twig/Timber integration
- Comment disabling

## Requirements

- PHP 8.1+
- WordPress 6.0+
- Composer

## Installation

```bash
composer install
```

## Directory Structure

```
parent-theme/
├── src/
│   ├── Contracts/
│   │   ├── HasAssets.php      # Interface for asset enqueueing
│   │   └── Registrable.php    # Interface for registrable classes
│   ├── Traits/
│   │   └── HasAssets.php      # Asset enqueueing implementation
│   ├── Providers/
│   │   ├── ServiceProvider.php         # Base provider class
│   │   ├── AssetServiceProvider.php    # Frontend/editor assets
│   │   ├── PostTypeServiceProvider.php # JSON-based CPT registration
│   │   ├── TwigServiceProvider.php     # Twig function registration
│   │   └── ThemeService/
│   │       ├── ThemeServiceProvider.php    # Theme supports
│   │       └── Features/
│   │           └── DisableComments.php     # Disables comments
│   └── Theme.php              # Base theme class (extends Timber\Site)
├── composer.json              # PSR-4 autoloading for ParentTheme namespace
├── functions.php              # Loads autoloader
├── style.css                  # Theme metadata
└── index.php                  # Required template file
```

## Creating a Child Theme

1. Create a new theme directory
2. Add `Template: parent-theme` to your `style.css` header
3. In `composer.json`, set up PSR-4 autoloading for your namespace
4. Extend `ParentTheme\Theme` in your Theme class
5. Extend parent providers as needed

### Example Child Theme Setup

**style.css:**
```css
/*
 * Theme Name: My Child Theme
 * Template: parent-theme
 */
```

**src/Theme.php:**
```php
<?php
namespace ChildTheme;

use ParentTheme\Theme as BaseTheme;

class Theme extends BaseTheme
{
    protected array $providers = [
        \ChildTheme\Providers\ThemeServiceProvider::class,
        // Add your providers...
    ];
}
```

**functions.php:**
```php
<?php
// Load parent theme's autoloader
$parent_autoloader = get_template_directory() . '/vendor/autoload.php';
if (file_exists($parent_autoloader)) {
    require_once $parent_autoloader;
}

// Load child theme's autoloader
require_once __DIR__ . '/vendor/autoload.php';

Timber\Timber::init();
new \ChildTheme\Theme();
```

## Contracts

### `Registrable`
Interface for classes that register WordPress hooks/filters.

```php
interface Registrable
{
    public function register(): void;
}
```

### `HasAssets`
Interface for classes that enqueue styles and scripts.

```php
interface HasAssets
{
    public function enqueueStyle(string $handle, string $filename, array $deps = []): void;
    public function enqueueScript(string $handle, string $filename, array $deps = [], bool $inFooter = true): void;
}
```

## Base Classes

### `ServiceProvider`
Base class for all providers. Uses `HasAssets` trait and supports feature registration.

### `Theme`
Base theme class extending `Timber\Site`. Registers providers and sets up Timber directories.

## License

MIT
