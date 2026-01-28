# Parent Theme

A foundational WordPress parent theme providing PHP infrastructure for child themes.

## Overview

This theme provides reusable contracts, traits, and base classes that child themes can extend. It handles common WordPress functionality like:

- Theme supports registration
- Asset enqueueing infrastructure
- Post type registration from JSON config
- Twig/Timber integration
- Comment disabling
- SVG upload support

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
│   │       ├── ThemeServiceProvider.php
│   │       └── Features/
│   │           ├── DisableBlocks.php
│   │           ├── DisableComments.php
│   │           └── EnableSvgUploads.php
│   └── Theme.php              # Base theme class (extends Timber\Site)
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

use ParentTheme\Theme as BaseTheme;

class Theme extends BaseTheme
{
    protected array $providers = [
        \ChildTheme\Providers\ThemeServiceProvider::class,
        \ChildTheme\Providers\AssetServiceProvider::class,
        // Add your providers...
    ];
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

Timber\Timber::init();
new \ChildTheme\Theme();
```

---

## Service Provider Pattern

The service provider pattern organizes theme functionality into discrete, testable units.

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

### Contracts

#### Registrable

All providers must implement the `Registrable` interface:

```php
interface Registrable
{
    public function register(): void;
}
```

#### HasAssets

Providers that enqueue styles or scripts implement `HasAssets`:

```php
interface HasAssets
{
    public function enqueueStyle(string $handle, string $filename, array $deps = []): void;
    public function enqueueScript(string $handle, string $filename, array $deps = [], bool $inFooter = true): void;
}
```

### HasAssets Trait

The trait provides asset enqueueing with automatic handle prefixing:

```php
// In your provider
$this->enqueueStyle('main', 'block-service.css');
$this->enqueueScript('button', 'block-service/button.js');
```

Slug generation from class name:

| Class Name | Generated Slug |
|------------|----------------|
| `AssetServiceProvider` | `asset` |
| `BlockServiceProvider` | `block` |
| `ThemeServiceProvider` | `theme` |

### Creating Providers

#### Basic Provider

```php
namespace ChildTheme\Providers;

use ParentTheme\Providers\ServiceProvider;

class MyFeatureProvider extends ServiceProvider
{
    public function register(): void
    {
        parent::register();
        add_action('init', [$this, 'init']);
    }

    public function init(): void
    {
        // Implementation
    }
}
```

#### Extending Parent Providers

```php
namespace ChildTheme\Providers;

use ParentTheme\Providers\AssetServiceProvider as BaseProvider;

class AssetServiceProvider extends BaseProvider
{
    public function register(): void
    {
        parent::register();
        add_action('wp_head', [$this, 'addFontPreconnects'], 1);
    }

    public function enqueueFrontendAssets(): void
    {
        wp_enqueue_style('google-fonts', 'https://fonts.googleapis.com/...');
        parent::enqueueFrontendAssets();
    }
}
```

### Features System

Providers can register "features" - smaller classes for specific functionality:

```php
class BlockServiceProvider extends ServiceProvider
{
    protected array $features = [
        Features\ButtonIconEnhancer::class,
        Features\BlockPatterns::class,
    ];

    public function register(): void
    {
        parent::register(); // Registers features automatically
    }
}
```

Creating a feature:

```php
namespace ChildTheme\Providers\BlockService\Features;

use ParentTheme\Contracts\Registrable;

class ButtonIconEnhancer implements Registrable
{
    public function register(): void
    {
        add_filter('render_block_core/button', [$this, 'addIconToButton'], 10, 2);
    }
}
```

### Best Practices

1. **Single Responsibility** - Each provider handles one aspect
2. **Call Parent Methods** - Always call `parent::register()` when extending
3. **Use Hooks Appropriately** - Register hooks in `register()`, implement logic in separate methods
4. **Keep Features Small** - Features should do one thing

---

## Testing

The parent theme uses PHPUnit with WorDBless for testing.

### Test Structure

```
tests/
├── bootstrap.php
├── Unit/                   # No WordPress dependencies
│   └── Traits/
│       └── HasAssetsTraitTest.php
└── Integration/            # With WordPress
    ├── ThemeTest.php
    └── Providers/
        ├── ServiceProviderTest.php
        └── DisableCommentsTest.php
```

### Running Tests

```bash
# All tests
composer test

# Unit tests only
composer test:unit

# Integration tests only
composer test:integration
```

### Writing Tests

#### Unit Test

```php
namespace ParentTheme\Tests\Unit\Traits;

use PHPUnit\Framework\TestCase;
use ParentTheme\Traits\HasAssets;

class HasAssetsTraitTest extends TestCase
{
    public function testGetSlugFromClassName(): void
    {
        $mock = new class {
            use HasAssets;

            public function exposeGetSlug(): string
            {
                return $this->getSlug();
            }
        };

        $this->assertIsString($mock->exposeGetSlug());
    }
}
```

#### Integration Test

```php
namespace ParentTheme\Tests\Integration\Providers;

use ParentTheme\Providers\ThemeService\Features\DisableComments;
use Yoast\WPTestUtils\WPIntegration\TestCase;

class DisableCommentsTest extends TestCase
{
    public function testRemovesCommentsFromAdminBar(): void
    {
        $provider = new DisableComments();
        $provider->register();

        $this->assertNotFalse(
            has_action('wp_before_admin_bar_render', [$provider, 'removeFromAdminBar'])
        );
    }
}
```

### PHPUnit Configuration

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="tests/bootstrap.php" colors="true" verbose="true">
    <testsuites>
        <testsuite name="Unit">
            <directory suffix="Test.php">./tests/Unit</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory suffix="Test.php">./tests/Integration</directory>
        </testsuite>
    </testsuites>
    <coverage>
        <include>
            <directory suffix=".php">./src</directory>
        </include>
    </coverage>
</phpunit>
```

### Dependencies

```json
{
  "require-dev": {
    "automattic/wordbless": "^0.4.2",
    "yoast/wp-test-utils": "^1.0"
  }
}
```

---

## License

MIT
