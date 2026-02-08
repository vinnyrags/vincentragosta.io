# ThemeProvider

Handles core theme setup, configuration, and feature registration.

## Theme Supports

Registers standard WordPress theme supports:

- `automatic-feed-links`
- `title-tag`
- `post-thumbnails`
- `menus`
- `html5` (gallery, caption, style, script)
- `editor-styles`
- `wp-block-styles`
- `layout`
- `custom-spacing`
- `align-wide`

## Class Map

The `registerClassMap()` method registers the `timber/post/classmap` filter as core infrastructure. This maps WordPress post types to custom model classes:

- `post` → `ParentTheme\Models\Post`
- `page` → `ParentTheme\Models\Post`
- `attachment` → `ParentTheme\Models\Image` (for `image/*` mime types) or `Timber\Attachment` (for other attachments)

This ensures that `post.thumbnail` returns an `Image` instance with the fluent resize/crop API. Child themes can override `registerClassMap()` to add their own mappings (e.g., `project` → `ProjectPost`) while calling `parent::registerClassMap()` to preserve the base map.

## Features

### DisableBlocks

Disables specified Gutenberg blocks and embed variations from the editor.

#### Filters

**`theme/disabled_block_types`**

Filter the array of block types to disable.

```php
// Re-enable a block (remove from disabled list)
add_filter('theme/disabled_block_types', function (array $blocks): array {
    return array_diff($blocks, ['core/cover']);
});

// Disable additional blocks
add_filter('theme/disabled_block_types', function (array $blocks): array {
    $blocks[] = 'core/pullquote';
    return $blocks;
});
```

**`theme/disabled_embed_variations`**

Filter the array of embed variations to disable.

```php
// Re-enable an embed provider
add_filter('theme/disabled_embed_variations', function (array $variations): array {
    return array_diff($variations, ['twitter']);
});

// Disable additional embed providers
add_filter('theme/disabled_embed_variations', function (array $variations): array {
    $variations[] = 'youtube';
    return $variations;
});
```

#### Default Disabled Blocks

- Template/Site blocks: `template-part`, `post-content`, `navigation`, `site-logo`, `site-title`, `site-tagline`
- Query/Loop blocks: `query`, `query-title`, `query-pagination`, `post-template`
- Post blocks: `avatar`, `post-title`, `post-excerpt`, `post-featured-image`, `post-author`, `post-date`, `post-terms`, `read-more`
- Comment blocks: `comments`, `comment-author-name`, `comment-content`, `comment-date`, `comments-pagination`
- Widget blocks: `loginout`, `term-description`, `archives`, `calendar`, `categories`, `latest-comments`, `latest-posts`, `page-list`, `rss`, `search`, `tag-cloud`
- Layout blocks: `spacer`, `separator`, `nextpage`, `more`
- Content blocks: `cover`, `media-text`, `verse`, `details`, `quote`, `freeform`

#### Default Disabled Embed Variations

All embed providers except `youtube` are disabled by default.

---

### DisableComments

Completely disables comment functionality across the site:

- Removes comment support from all post types
- Closes comments and pings on all content
- Removes comments from admin menu
- Redirects `/wp-admin/edit-comments.php` to dashboard
- Removes comments from admin bar

No configuration needed. To re-enable comments, remove `DisableComments::class` from the features array in a child theme's service provider.

---

### EnableSvgUploads

Enables SVG uploads in the WordPress media library with security sanitization.

#### What it does

- Adds `svg` and `svgz` to allowed mime types
- Fixes WordPress SVG mime type detection issues
- Sanitizes uploaded SVGs by removing:
  - `<script>` elements
  - Event handler attributes (`onclick`, `onload`, `onerror`, etc.)
  - `javascript:` URLs in `href` and `xlink:href` attributes

#### Usage

No configuration needed. SVG uploads are enabled automatically.

**Note:** While sanitization removes common attack vectors, SVGs can still contain complex content. Only allow trusted users to upload SVGs.
