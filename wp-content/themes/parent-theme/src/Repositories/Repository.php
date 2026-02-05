<?php

namespace ParentTheme\Repositories;

use ParentTheme\Repositories\RepositoryInterface;
use ParentTheme\Models\Post;
use Timber\Timber;
use WP_Query;

/**
 * Base Repository class.
 *
 * Provides a clean layer over Timber/WP_Query for querying posts.
 * Extend this class for specific post types.
 */
class Repository implements RepositoryInterface
{
    /**
     * The model class to instantiate.
     *
     * @var class-string<Post>
     */
    protected string $model = Post::class;

    /**
     * Whether to exclude the current singular post from queries.
     */
    protected bool $excludeCurrentPost = true;

    /**
     * Default query arguments.
     */
    protected array $defaultArgs = [
        'post_status' => 'publish',
    ];

    /**
     * Get the post type from the model class.
     */
    protected function postType(): string
    {
        return $this->model::POST_TYPE;
    }

    /**
     * Find a post by ID.
     */
    public function find(int $id): ?Post
    {
        $posts = $this->query([
            'p' => $id,
            'posts_per_page' => 1,
        ]);

        return $posts[0] ?? null;
    }

    /**
     * Find a post by slug.
     */
    public function findBySlug(string $slug): ?Post
    {
        return $this->findOne(['name' => $slug]);
    }

    /**
     * Find a single post matching the query.
     */
    public function findOne(array $args = []): ?Post
    {
        $args['posts_per_page'] = 1;
        $results = $this->query($args);

        return $results[0] ?? null;
    }

    /**
     * Get all posts.
     *
     * @return Post[]
     */
    public function all(int $limit = -1): array
    {
        return $this->query([
            'posts_per_page' => $limit,
        ]);
    }

    /**
     * Get the latest posts.
     *
     * @return Post[]
     */
    public function latest(int $limit = 10): array
    {
        return $this->query([
            'posts_per_page' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);
    }

    /**
     * Get posts by IDs (preserves order).
     *
     * @param int[] $ids
     * @return Post[]
     */
    public function findMany(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        return $this->query([
            'post__in' => $ids,
            'orderby' => 'post__in',
            'posts_per_page' => count($ids),
        ]);
    }

    /**
     * Get posts by author.
     *
     * @return Post[]
     */
    public function byAuthor(int|object $author, int $limit = -1): array
    {
        $authorId = is_object($author) ? $author->ID : $author;

        return $this->query([
            'author' => $authorId,
            'posts_per_page' => $limit,
        ]);
    }

    /**
     * Get all draft posts.
     *
     * @return Post[]
     */
    public function drafts(int $limit = -1): array
    {
        return $this->query([
            'post_status' => 'draft',
            'posts_per_page' => $limit,
        ]);
    }

    /**
     * Get posts by a meta value.
     *
     * @return Post[]
     */
    public function whereMetaEquals(string $key, mixed $value, int $limit = -1): array
    {
        return $this->query([
            'posts_per_page' => $limit,
            'meta_key' => $key,
            'meta_value' => $value,
        ]);
    }

    /**
     * Get posts by taxonomy term.
     *
     * @return Post[]
     */
    public function whereTerm(string $taxonomy, string|int $term, int $limit = -1): array
    {
        return $this->query([
            'posts_per_page' => $limit,
            'tax_query' => [
                [
                    'taxonomy' => $taxonomy,
                    'field' => is_numeric($term) ? 'term_id' : 'slug',
                    'terms' => $term,
                ],
            ],
        ]);
    }

    /**
     * Get posts by taxonomy term IDs with optional exclusions.
     *
     * @param int[] $termIds
     * @param int[] $excludeIds
     * @return Post[]
     */
    public function whereTermIds(array $termIds, string $taxonomy = 'category', int $limit = 10, array $excludeIds = []): array
    {
        if (empty($termIds)) {
            return [];
        }

        $args = [
            'posts_per_page' => $limit,
            'tax_query' => [
                [
                    'taxonomy' => $taxonomy,
                    'terms' => $termIds,
                    'field' => 'term_id',
                    'operator' => 'IN',
                ],
            ],
        ];

        if (!empty($excludeIds)) {
            $args['post__not_in'] = $excludeIds;
        }

        return $this->query($args);
    }

    /**
     * Count posts matching criteria.
     */
    public function count(array $args = []): int
    {
        $args = array_merge($this->buildArgs($args), [
            'posts_per_page' => -1,
            'fields' => 'ids',
        ]);

        $query = new WP_Query($args);

        return $query->found_posts;
    }

    /**
     * Check if any posts exist matching criteria.
     */
    public function exists(array $args = []): bool
    {
        $args = array_merge($this->buildArgs($args), [
            'posts_per_page' => 1,
            'fields' => 'ids',
        ]);

        $query = new WP_Query($args);

        return $query->have_posts();
    }

    /**
     * Save a post (insert or update).
     */
    public function save(Post $post): int|\WP_Error
    {
        $postArr = get_object_vars($post->wp_object());

        return $post->ID
            ? wp_update_post($postArr, true)
            : wp_insert_post($postArr, true);
    }

    /**
     * Delete a post.
     */
    public function delete(Post $post, bool $forceDelete = false): bool
    {
        $result = wp_delete_post($post->ID, $forceDelete);
        return !empty($result);
    }

    /**
     * Execute a custom query.
     *
     * @return Post[]
     */
    public function query(array $args = []): array
    {
        $args = $this->buildArgs($args);
        $args = $this->maybeExcludeCurrentPost($args);

        $result = Timber::get_posts($args, $this->model);

        // Timber 2.x returns PostQuery, convert to array
        if ($result instanceof \Timber\PostQuery) {
            return $result->to_array();
        }

        return is_array($result) ? $result : [];
    }

    /**
     * Build query arguments with defaults.
     */
    protected function buildArgs(array $args): array
    {
        return [...$this->defaultArgs, 'post_type' => $this->postType(), ...$args];
    }

    /**
     * Exclude the current singular post from query results.
     */
    protected function maybeExcludeCurrentPost(array $args): array
    {
        global $wp_query;

        if (!$wp_query || !$this->excludeCurrentPost) {
            return $args;
        }

        $queriedObject = $wp_query->get_queried_object();

        if (
            $wp_query->is_singular &&
            $queriedObject &&
            $queriedObject->post_type === $this->postType()
        ) {
            $args['post__not_in'] = array_merge(
                (array) ($args['post__not_in'] ?? []),
                [get_the_ID()]
            );
        }

        return $args;
    }
}
