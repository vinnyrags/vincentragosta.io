# Providers

Service providers handle specific areas of theme functionality. Each provider is a self-contained module that registers hooks, filters, and features.

## Architecture

### Base ServiceProvider

All providers extend `ServiceProvider`, which implements:

- `Registrable` interface (requires `register()` method)
- [`AssetManager`](./Support/Asset/) composition (provides asset enqueueing helpers)
- [`BlockManager`](./Support/Block/) composition (provides block registration capabilities)
- [`FeatureManager`](./Support/Feature/) composition (provides feature registration with inheritance and opt-out)

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

        // Always call parent to register features and blocks
        parent::register();
    }
}
```

### Block Registration

Providers can register their own Gutenberg blocks by defining a `$blocks` property and placing block directories in a `blocks/` subdirectory relative to the provider class file.

```php
use ParentTheme\Providers\ServiceProvider;

class MyProvider extends ServiceProvider
{
    protected array $blocks = [
        'my-block',
        'another-block',
    ];

    public function register(): void
    {
        parent::register(); // Registers blocks automatically
    }

    // Enqueue frontend + editor block styles
    public function enqueueBlockAssets(): void
    {
        $this->enqueueStyle('my-block-style', 'my-block.css');
    }

    // Enqueue editor-only scripts and styles
    public function enqueueBlockEditorAssets(): void
    {
        $this->enqueueEditorScript('my-block-editor', 'my-block.js');
        $this->enqueueStyle('my-block-editor-style', 'my-block-editor.css');
    }
}
```

Block assets are compiled to `dist/css/` and `dist/js/` by the build system. The `enqueueEditorScript()` helper automatically includes WordPress block editor dependencies:

- `wp-blocks`
- `wp-element`
- `wp-block-editor`
- `wp-components`
- `wp-i18n`
- `wp-data`

Additional dependencies can be passed as the third argument.

### Features

Features are smaller, focused classes that handle a single responsibility. They implement `Registrable` and are automatically instantiated and registered by the `FeatureManager`.

```php
use ParentTheme\Providers\Contracts\Registrable;

class MyFeature implements Registrable
{
    public function register(): void
    {
        add_filter('some_filter', [$this, 'handleFilter']);
    }
}
```

Features are inherited automatically from parent providers. Child providers only need to declare their own features, and can opt out of inherited features using `=> false`:

```php
// Child provider -- parent features are inherited automatically
protected array $features = [
    MyChildFeature::class,
    SomeParentFeature::class => false,  // disable inherited feature
];
```

## Available Providers

| Provider | Purpose |
|----------|---------|
| [PostType](./PostType/) | Custom post type registration from JSON config |
| [Twig](./Twig/) | Custom Twig functions and filters |
| [Theme](./Theme/) | Theme supports and core features |

## Extending in Child Themes

Child themes can extend parent providers to add or modify functionality:

```php
namespace ChildTheme\Providers\Theme;

use ParentTheme\Providers\Theme\ThemeProvider as BaseThemeProvider;

class ThemeProvider extends BaseThemeProvider
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
