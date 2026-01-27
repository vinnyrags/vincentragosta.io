# Parent Theme Documentation

This documentation covers the base infrastructure provided by the parent theme.

## Overview

The parent theme provides a reusable foundation for WordPress theme development:

- **Service Provider Pattern** - Modular, testable architecture
- **Contracts & Traits** - Shared interfaces and implementations
- **Base Classes** - Extendable Theme and ServiceProvider classes
- **Common Features** - Disable comments, asset management, etc.

## Documentation

| Document | Description |
|----------|-------------|
| [Service Providers](./SERVICE-PROVIDERS.md) | Contracts, traits, and extension patterns |
| [Testing](./TESTING.md) | PHPUnit testing setup and patterns |

## Quick Start

### 1. Create a Child Theme

```php
// child-theme/functions.php

// Load parent theme's autoloader
$parent_autoloader = get_template_directory() . '/vendor/autoload.php';
if (file_exists($parent_autoloader)) {
    require_once $parent_autoloader;
}

// Load child theme's autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap
new \ChildTheme\Theme();
```

### 2. Extend the Base Theme

```php
// child-theme/src/Theme.php
namespace ChildTheme;

use ParentTheme\Theme as BaseTheme;

class Theme extends BaseTheme
{
    protected array $providers = [
        Providers\AssetServiceProvider::class,
        Providers\BlockServiceProvider::class,
        // Add your providers...
    ];
}
```

### 3. Create Service Providers

```php
// child-theme/src/Providers/AssetServiceProvider.php
namespace ChildTheme\Providers;

use ParentTheme\Providers\AssetServiceProvider as BaseProvider;

class AssetServiceProvider extends BaseProvider
{
    protected string $handlePrefix = 'child-theme';

    public function register(): void
    {
        parent::register();
        // Add child-specific registrations...
    }
}
```

## Namespace Structure

```
ParentTheme\
├── Contracts\
│   ├── Registrable       # Interface for bootable classes
│   └── HasAssets         # Interface for asset management
├── Traits\
│   └── HasAssets         # Asset enqueueing implementation
├── Providers\
│   ├── ServiceProvider   # Abstract base provider
│   ├── AssetServiceProvider
│   ├── ThemeServiceProvider
│   └── DisableComments
└── Theme                 # Base theme bootstrap class
```

## Features

### Disable Comments

Parent theme includes a `DisableComments` provider that removes all comment functionality:

```php
// Already registered in parent - no action needed
// Comments are disabled by default
```

### Asset Management

The `HasAssets` trait provides standardized asset enqueueing:

```php
// In your provider
$this->enqueueStyle('main', 'main.css');
$this->enqueueScript('frontend', 'frontend.js', ['jquery']);
```

## Requirements

- PHP 8.0+
- WordPress 6.0+
- Composer for autoloading
