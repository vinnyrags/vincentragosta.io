# Providers

Child theme providers extend the parent theme's base providers to add site-specific functionality.

## Available Providers

| Provider | Extends | Purpose |
|----------|---------|---------|
| [AssetService](./AssetService/) | Parent | Adds Google Fonts and preconnects |
| [BlockService](./BlockService/) | Base | Custom blocks and button icon enhancement |
| [PostTypeService](./PostTypeService/) | Parent | Site-specific post type configuration |
| [ThemeService](./ThemeService/) | Parent | Site-specific theme supports and features |
| [TwigService](./TwigService/) | Parent | Adds `icon()` function |

## Provider Registration

Providers are registered in the `Theme` class:

```php
namespace ChildTheme;

use ChildTheme\Providers\AssetService\AssetServiceProvider;
use ChildTheme\Providers\BlockService\BlockServiceProvider;
use ChildTheme\Providers\PostTypeService\PostTypeServiceProvider;
use ChildTheme\Providers\ThemeService\ThemeServiceProvider;
use ChildTheme\Providers\TwigService\TwigServiceProvider;
use ParentTheme\Theme as BaseTheme;

class Theme extends BaseTheme
{
    protected array $providers = [
        ThemeServiceProvider::class,
        AssetServiceProvider::class,
        BlockServiceProvider::class,
        PostTypeServiceProvider::class,
        TwigServiceProvider::class,
    ];
}
```

## Adding New Providers

1. Create a new directory under `Providers/` (e.g., `MyService/`)
2. Create a provider class extending `ServiceProvider` (or a parent provider)
3. Add a `README.md` documenting the provider
4. Add to the providers array in `Theme.php`

```php
namespace ChildTheme\Providers\MyService;

use ParentTheme\Providers\ServiceProvider;

class MyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        add_action('init', [$this, 'init']);
        parent::register();
    }

    public function init(): void
    {
        // Your initialization logic
    }
}
```
