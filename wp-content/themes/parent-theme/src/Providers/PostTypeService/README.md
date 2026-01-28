# PostTypeServiceProvider

Registers custom post types from JSON configuration files.

## How It Works

1. Scans the child theme's `/config` directory for `.json` files
2. Parses each file for `post_type` and `args` keys
3. Registers the post type using `register_post_type()`

## Configuration File Format

Create a JSON file in your child theme's `/config` directory:

```
child-theme/
└── config/
    └── project.json
```

### Example: `project.json`

```json
{
  "post_type": "project",
  "args": {
    "label": "Projects",
    "labels": {
      "name": "Projects",
      "singular_name": "Project",
      "add_new": "Add New",
      "add_new_item": "Add New Project",
      "edit_item": "Edit Project",
      "new_item": "New Project",
      "view_item": "View Project",
      "search_items": "Search Projects",
      "not_found": "No projects found",
      "not_found_in_trash": "No projects found in Trash"
    },
    "public": true,
    "has_archive": true,
    "show_in_rest": true,
    "supports": ["title", "editor", "thumbnail", "excerpt"],
    "menu_icon": "dashicons-portfolio",
    "rewrite": {
      "slug": "projects"
    }
  }
}
```

## Args Reference

The `args` object accepts all parameters from [`register_post_type()`](https://developer.wordpress.org/reference/functions/register_post_type/).

Common options:

| Key | Type | Description |
|-----|------|-------------|
| `public` | bool | Whether the post type is public |
| `has_archive` | bool | Enable archive page |
| `show_in_rest` | bool | Enable Gutenberg editor |
| `supports` | array | Features: `title`, `editor`, `thumbnail`, `excerpt`, etc. |
| `menu_icon` | string | Dashicon or custom icon URL |
| `rewrite` | array | Permalink settings |

## Notes

- Files are loaded on the `init` hook
- Invalid JSON files are silently skipped
- Files missing `post_type` or `args` keys are skipped
- Config directory must exist in the child theme (not parent)
