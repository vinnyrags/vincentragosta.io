# ProjectServiceProvider

Self-contained provider for all project-related functionality, including the projects block, custom post type, and configuration.

## Registered Blocks

| Block | Slug | Description |
|-------|------|-------------|
| Projects | `child-theme/projects` | Displays a grid of project posts |

Block files are located in `blocks/projects/`.

## Custom Post Type

Registers the `project` post type from JSON configuration.

### Configuration

Post type settings are defined in `config/post-type.json`:

```json
{
  "post_type": "project",
  "args": {
    "labels": { ... },
    "public": true,
    "show_in_rest": true,
    "supports": ["title", "editor", "thumbnail"],
    "menu_icon": "dashicons-portfolio"
  }
}
```

Labels are automatically translated using the child-theme text domain.

## Assets

### Block Assets

- **Frontend + Editor**: `projects.css`
- **Editor only**: `projects.js`, `projects-editor.css`

## Directory Structure

```
ProjectService/
├── ProjectServiceProvider.php
├── README.md
├── blocks/
│   └── projects/
│       ├── block.json
│       ├── editor/
│       │   ├── index.js
│       │   └── editor.scss
│       └── frontend/
│           └── style.scss
└── config/
    └── post-type.json
```

## Extending

To add additional project-related functionality, create features in a `Features/` directory:

```php
protected array $features = [
    ProjectArchiveFeature::class,
];
```
