# Providers

Child theme providers extend the parent theme's base providers to add site-specific functionality.

## Available Providers

| Provider | Extends | Purpose |
|----------|---------|---------|
| [Theme](./Theme/) | Parent | Site-specific theme supports, features, and blocks |
| [Project](./Project/) | Base | Project post type and projects block |

## Provider Registration

Providers are registered in the `Theme` class:

```php
namespace ChildTheme;

use ChildTheme\Providers\Theme\ThemeProvider;
use ChildTheme\Providers\Project\ProjectProvider;
use ParentTheme\Theme as BaseTheme;

class Theme extends BaseTheme
{
    protected array $providers = [
        ThemeProvider::class,
        ProjectProvider::class,
    ];
}
```

## Adding New Providers

1. Create a new directory under `Providers/` (e.g., `MyFeature/`)
2. Create a provider class extending `ServiceProvider` (or a parent provider)
3. Add a `README.md` documenting the provider
4. Add to the providers array in `Theme.php`

```php
namespace ChildTheme\Providers\MyFeature;

use ParentTheme\Providers\ServiceProvider;

class MyFeatureProvider extends ServiceProvider
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
