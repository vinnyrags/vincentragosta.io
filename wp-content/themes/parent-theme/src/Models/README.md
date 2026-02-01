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
