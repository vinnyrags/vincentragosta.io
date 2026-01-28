# Providers

Service providers handle specific areas of theme functionality. Each provider is a self-contained module that registers hooks, filters, and features.

## Architecture

### Base ServiceProvider

All providers extend `ServiceProvider`, which implements:

- `Registrable` interface (requires `register()` method)
- `HasAssets` trait (provides asset enqueueing helpers)
- Feature registration system

```php
use ParentTheme\Providers\ServiceProvider;

class MyProvider extends ServiceProvider
{
    protected array $features = [
        MyFeature::class,
    ];

    public function register(): void
    {
        // Register hooks here
        add_action('init', [$this, 'doSomething']);

        // Always call parent to register features
        parent::register();
    }
}
```

### Features

Features are smaller, focused classes that handle a single responsibility. They implement `Registrable` and are automatically instantiated by their parent provider.

```php
use ParentTheme\Contracts\Registrable;

class MyFeature implements Registrable
{
    public function register(): void
    {
        add_filter('some_filter', [$this, 'handleFilter']);
    }
}
```

## Available Providers

| Provider | Purpose |
|----------|---------|
| [AssetService](./AssetService/) | Frontend and editor asset enqueueing |
| [PostTypeService](./PostTypeService/) | Custom post type registration from JSON config |
| [TwigService](./TwigService/) | Custom Twig functions and filters |
| [ThemeService](./ThemeService/) | Theme supports and core features |

## Extending in Child Themes

Child themes can extend parent providers to add or modify functionality:

```php
namespace ChildTheme\Providers\AssetService;

use ParentTheme\Providers\AssetService\AssetServiceProvider as BaseAssetServiceProvider;

class AssetServiceProvider extends BaseAssetServiceProvider
{
    protected string $handlePrefix = 'child-theme';

    public function enqueueFrontendAssets(): void
    {
        // Add child-specific assets
        wp_enqueue_style('my-style', ...);

        // Call parent for base assets
        parent::enqueueFrontendAssets();
    }
}
```
