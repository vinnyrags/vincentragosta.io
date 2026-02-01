# Repositories

Query layer over Timber/WP_Query for fetching posts with a clean, expressive API.

## Repository

Base repository class that implements `RepositoryInterface`.

### Usage

```php
use ParentTheme\Repositories\Repository;

$repository = new Repository();

// Find by ID
$post = $repository->find(123);

// Find by slug
$post = $repository->findBySlug('hello-world');

// Find single matching query
$post = $repository->findOne(['meta_key' => 'featured', 'meta_value' => '1']);

// Get all posts
$posts = $repository->all();
$posts = $repository->all(limit: 10);

// Get latest posts
$posts = $repository->latest(5);

// Find multiple by IDs (preserves order)
$posts = $repository->findMany([1, 2, 3]);

// Find by author
$posts = $repository->byAuthor($userId);

// Find drafts
$posts = $repository->drafts();

// Find by meta
$posts = $repository->whereMetaEquals('color', 'blue');

// Find by taxonomy term
$posts = $repository->whereTerm('category', 'news');
$posts = $repository->whereTerm('category', 5); // by term ID

// Find by term IDs with exclusions
$posts = $repository->whereTermIds([1, 2, 3], 'category', limit: 10, excludeIds: [99]);

// Count and existence
$count = $repository->count(['post_status' => 'draft']);
$exists = $repository->exists(['name' => 'hello-world']);

// Custom query
$posts = $repository->query([
    'posts_per_page' => 10,
    'orderby' => 'title',
    'order' => 'ASC',
]);

// Save (insert or update)
$result = $repository->save($post);

// Delete
$repository->delete($post);
$repository->delete($post, forceDelete: true);
```

### Auto-exclude Current Post

By default, repositories exclude the current post when on a singular view (prevents "related posts" from including the current post). Disable with:

```php
class MyRepository extends Repository
{
    protected bool $excludeCurrentPost = false;
}
```

### Extending

Create custom repositories for specific post types:

```php
namespace ChildTheme\Providers\ProjectService;

use ParentTheme\Repositories\Repository;

class ProjectRepository extends Repository
{
    protected string $model = ProjectPost::class;

    public function featured(int $limit = 5): array
    {
        return $this->whereMetaEquals('_featured', '1', $limit);
    }

    public function inCategory(string|int $category, int $limit = -1): array
    {
        return $this->whereTerm('category', $category, $limit);
    }
}
```

The repository automatically uses `ProjectPost::POST_TYPE` for queries.

## RepositoryInterface

Defines the contract for all repositories:

- `find()`, `findBySlug()`, `findOne()`, `findMany()`
- `all()`, `latest()`
- `count()`, `exists()`
- `save()`, `delete()`
- `query()`

Type-hint against the interface for better testability:

```php
public function __construct(RepositoryInterface $repository)
{
    $this->repository = $repository;
}
```
