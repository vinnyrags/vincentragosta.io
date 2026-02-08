# Models

Base model classes that extend Timber for cleaner object-oriented access to WordPress data.

## Post

Extends `Timber\Post` with additional convenience methods.

### Usage

```php
use ParentTheme\Models\Post;

// Get a post by ID (via repository)
$post = $repository->find(123);

// Access properties
$post->title();           // Post title
$post->url();             // Permalink (alias for link())
$post->content();         // Filtered content
$post->excerpt();         // Excerpt

// Dates as DateTime objects
$post->publishedDate();   // DateTime
$post->modifiedDate();    // DateTime

// Status checks
$post->isPublished();     // bool
$post->isDraft();         // bool

// Meta operations
$post->getMeta('key');              // Get meta value
$post->setMeta('key', 'value');     // Set meta value
$post->deleteMeta('key');           // Delete meta

// Terms
$post->hasTerm('news', 'category'); // Check if has term

// Refresh from database
$post->refresh();
```

### Extending

Create custom post type models by extending the base Post class:

```php
namespace ChildTheme\Providers\ProjectService;

use ParentTheme\Models\Post;

class ProjectPost extends Post
{
    public const POST_TYPE = 'project';

    public function categories(): array
    {
        return $this->terms(['taxonomy' => 'category']);
    }

    public function categoryName(): ?string
    {
        $categories = $this->categories();
        return $categories[0]->name ?? null;
    }
}
```

### Twig Integration

Since Post extends Timber\Post, all models work seamlessly in Twig templates:

```twig
<article>
    <h1>{{ post.title }}</h1>
    <time>{{ post.date }}</time>
    <div>{{ post.content }}</div>
    {% if post.thumbnail %}
        <img src="{{ post.thumbnail.src }}" alt="{{ post.thumbnail.alt }}">
    {% endif %}
</article>
```

## Image

Extends `Timber\Image` with a fluent resize/crop API and template-based rendering. When the `ClassMapFeature` is active (registered in `ThemeProvider`), `post.thumbnail` automatically returns an `Image` instance for image attachments.

### Fluent API

```php
use ParentTheme\Models\Image;
use ParentTheme\Models\CropDirection;

// Resize to specific dimensions
$image->resize(800, 600);

// Set width or height individually (the other is calculated proportionally)
$image->setWidth(400);
$image->setHeight(300);

// Use a registered WordPress image size
$image->setSize('thumbnail');

// Crop with a direction
$image->crop(CropDirection::CENTER);

// Disable lazy loading
$image->setLazy(false);

// Add custom HTML attributes
$image->setAttr('class', 'hero-image');

// Chain methods
$image->resize(800, 600)->crop(CropDirection::TOP)->setAttr('class', 'cover');
```

### Twig Usage

```twig
{# Render with fluent API (outputs <img> tag) #}
{{ post.thumbnail.resize(800, 600) }}

{# With cropping #}
{{ post.thumbnail.resize(400, 400).crop }}

{# Direct property access still works #}
<img src="{{ post.thumbnail.src }}" alt="{{ post.thumbnail.alt }}">
```

### Template

The `Image::render()` method compiles `partial/image.twig`, which can be overridden by the child theme. The default template outputs a single `<img>` tag with all configured attributes.

## CropDirection

Backed string enum for image crop directions. Maps to Timber's `ImageHelper::resize()` crop parameter values.

| Case | Value |
|------|-------|
| `NONE` | `default` |
| `CENTER` | `center` |
| `TOP` | `top` |
| `BOTTOM` | `bottom` |
| `LEFT` | `left` |
| `RIGHT` | `right` |
