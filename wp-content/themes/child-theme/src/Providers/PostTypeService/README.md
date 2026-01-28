# PostTypeServiceProvider

Extends the parent theme's PostTypeServiceProvider for custom post type registration.

## How It Works

Post types are registered from JSON files in the `/config` directory. See the [parent provider documentation](../../../parent-theme/src/Providers/PostTypeServiceProvider.README.md) for details.

## Adding Post Types

1. Create a JSON file in `child-theme/config/`:

```
child-theme/
└── config/
    └── project.json
```

2. Define the post type configuration:

```json
{
  "post_type": "project",
  "args": {
    "label": "Projects",
    "public": true,
    "has_archive": true,
    "show_in_rest": true,
    "supports": ["title", "editor", "thumbnail"],
    "menu_icon": "dashicons-portfolio"
  }
}
```

3. The post type is automatically registered on `init`.

## Customizing

Override methods to add child theme-specific logic:

```php
public function registerPostTypes(): void
{
    parent::registerPostTypes();

    // Add programmatic post types
    register_post_type('custom', [...]);
}
```
