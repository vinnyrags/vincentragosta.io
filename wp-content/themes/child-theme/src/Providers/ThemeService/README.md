# ThemeServiceProvider

Extends the parent theme's ThemeServiceProvider to add site-specific configuration.

## What It Adds

### Admin Bar Hidden

Hides the WordPress admin bar on the frontend:

```php
add_filter('show_admin_bar', '__return_false');
```

### Theme Service Styles

Enqueues `theme-service.css` for frontend styling related to theme services.

## Inherited from Parent

All features from the parent ThemeServiceProvider are inherited:

- **Theme Supports** - Standard WordPress theme supports
- **DisableBlocks** - Gutenberg block restrictions
- **DisableComments** - Comment functionality disabled
- **EnableSvgUploads** - SVG upload support with sanitization

## Overriding Parent Features

### Re-enable a Disabled Block

Use the filter in your child theme's `functions.php`:

```php
add_filter('theme/disabled_block_types', function (array $blocks): array {
    // Re-enable the cover block
    return array_diff($blocks, ['core/cover']);
});
```

### Re-enable an Embed Provider

```php
add_filter('theme/disabled_embed_variations', function (array $variations): array {
    // Re-enable Twitter embeds
    return array_diff($variations, ['twitter']);
});
```

## Adding Site-Specific Features

To add features specific to this site, add them to the `$features` array:

```php
protected array $features = [
    MyCustomFeature::class,
];

public function register(): void
{
    parent::register(); // Registers parent features too
}
```

Or create features in `Features/` directory and register them manually if you need more control.
