<?php

declare(strict_types=1);

namespace IX\Repositories;

use IX\Models\Post;

/**
 * Interface for post repositories.
 *
 * Defines the contract for querying and persisting posts.
 */
interface RepositoryInterface
{
    /**
     * Find a post by ID.
     */
    public function find(int $id): ?Post;

    /**
     * Find a post by slug.
     */
    public function findBySlug(string $slug): ?Post;

    /**
     * Find a single post matching the query.
     */
    public function findOne(array $args = []): ?Post;

    /**
     * Get all posts.
     *
     * @return Post[]
     */
    public function all(int $limit = -1): array;

    /**
     * Get the latest posts.
     *
     * @return Post[]
     */
    public function latest(int $limit = 10): array;

    /**
     * Get posts by IDs (preserves order).
     *
     * @param int[] $ids
     * @return Post[]
     */
    public function findMany(array $ids): array;

    /**
     * Count posts matching criteria.
     */
    public function count(array $args = []): int;

    /**
     * Check if any posts exist matching criteria.
     */
    public function exists(array $args = []): bool;

    /**
     * Save a post (insert or update).
     */
    public function save(Post $post): int|\WP_Error;

    /**
     * Delete a post.
     */
    public function delete(Post $post, bool $forceDelete = false): bool;

    /**
     * Execute a custom query.
     *
     * @return Post[]
     */
    public function query(array $args = []): array;
}
