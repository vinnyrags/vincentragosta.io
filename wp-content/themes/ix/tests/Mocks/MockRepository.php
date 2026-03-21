<?php

namespace IX\Tests\Mocks;

use IX\Models\Post;
use IX\Repositories\Repository;

/**
 * Mock Repository class for testing.
 *
 * Allows injecting mock query results instead of using Timber.
 */
class MockRepository extends Repository
{
    /**
     * Mock posts indexed by ID.
     *
     * @var array<int, Post>
     */
    private array $mockPosts = [];

    /**
     * Last query arguments received.
     */
    private array $lastQueryArgs = [];

    /**
     * Custom query result to return.
     *
     * @var Post[]|null
     */
    private ?array $customQueryResult = null;

    /**
     * Track saved posts.
     *
     * @var Post[]
     */
    private array $savedPosts = [];

    /**
     * Track deleted posts.
     *
     * @var Post[]
     */
    private array $deletedPosts = [];

    /**
     * Next ID for new posts.
     */
    private int $nextId = 1000;

    /**
     * Simulate current post ID for maybeExcludeCurrentPost.
     */
    private ?int $currentPostId = null;

    /**
     * Simulate singular view for maybeExcludeCurrentPost.
     */
    private bool $isSingular = false;

    /**
     * Set mock posts.
     *
     * @param Post[] $posts
     */
    public function setMockPosts(array $posts): self
    {
        $this->mockPosts = [];
        foreach ($posts as $post) {
            $this->mockPosts[$post->ID] = $post;
        }
        return $this;
    }

    /**
     * Set a custom query result.
     *
     * @param Post[] $posts
     */
    public function setQueryResult(array $posts): self
    {
        $this->customQueryResult = $posts;
        return $this;
    }

    /**
     * Get the last query arguments.
     */
    public function getLastQueryArgs(): array
    {
        return $this->lastQueryArgs;
    }

    /**
     * Override query to use mock data.
     *
     * @return Post[]
     */
    public function query(array $args = []): array
    {
        $this->lastQueryArgs = $this->buildArgs($args);
        $this->lastQueryArgs = $this->maybeExcludeCurrentPost($this->lastQueryArgs);

        // Return custom result if set
        if ($this->customQueryResult !== null) {
            $result = $this->customQueryResult;
            $this->customQueryResult = null; // Reset after use
            return $result;
        }

        // Filter mock posts based on args
        return $this->filterPosts($this->lastQueryArgs);
    }

    /**
     * Filter mock posts based on query args.
     *
     * @return Post[]
     */
    private function filterPosts(array $args): array
    {
        $posts = array_values($this->mockPosts);

        // Filter by post_status
        if (isset($args['post_status'])) {
            $status = $args['post_status'];
            $posts = array_filter($posts, fn($p) => $p->post_status === $status);
        }

        // Filter by post_type
        if (isset($args['post_type'])) {
            $type = $args['post_type'];
            $posts = array_filter($posts, fn($p) => $p->post_type === $type);
        }

        // Filter by post__in
        if (isset($args['post__in']) && !empty($args['post__in'])) {
            $ids = $args['post__in'];
            $posts = array_filter($posts, fn($p) => in_array($p->ID, $ids));

            // Preserve order if orderby is post__in
            if (($args['orderby'] ?? '') === 'post__in') {
                $ordered = [];
                foreach ($ids as $id) {
                    foreach ($posts as $post) {
                        if ($post->ID === $id) {
                            $ordered[] = $post;
                            break;
                        }
                    }
                }
                $posts = $ordered;
            }
        }

        // Filter by post__not_in
        if (isset($args['post__not_in']) && !empty($args['post__not_in'])) {
            $excludeIds = $args['post__not_in'];
            $posts = array_filter($posts, fn($p) => !in_array($p->ID, $excludeIds));
        }

        // Filter by name (slug)
        if (isset($args['name'])) {
            $slug = $args['name'];
            $posts = array_filter($posts, fn($p) => $p->post_name === $slug);
        }

        // Filter by p (single ID)
        if (isset($args['p'])) {
            $id = $args['p'];
            $posts = array_filter($posts, fn($p) => $p->ID === $id);
        }

        // Filter by author
        if (isset($args['author'])) {
            $authorId = $args['author'];
            $posts = array_filter($posts, fn($p) => $p->post_author == $authorId);
        }

        // Filter by meta
        if (isset($args['meta_key']) && isset($args['meta_value'])) {
            $key = $args['meta_key'];
            $value = $args['meta_value'];
            $posts = array_filter($posts, fn($p) => $p->getMeta($key) === $value);
        }

        // Filter by tax_query
        if (isset($args['tax_query']) && !empty($args['tax_query'])) {
            foreach ($args['tax_query'] as $taxQuery) {
                if (!is_array($taxQuery)) {
                    continue;
                }
                $taxonomy = $taxQuery['taxonomy'] ?? 'category';
                $terms = (array) ($taxQuery['terms'] ?? []);
                $field = $taxQuery['field'] ?? 'term_id';
                $operator = $taxQuery['operator'] ?? 'IN';

                $posts = array_filter($posts, function ($p) use ($taxonomy, $terms, $operator) {
                    foreach ($terms as $term) {
                        $hasTerm = $p->hasTerm($term, $taxonomy);
                        if ($operator === 'IN' && $hasTerm) {
                            return true;
                        }
                        if ($operator === 'NOT IN' && $hasTerm) {
                            return false;
                        }
                    }
                    return $operator === 'NOT IN';
                });
            }
        }

        // Sort by date if specified
        if (($args['orderby'] ?? '') === 'date') {
            usort($posts, function ($a, $b) use ($args) {
                $order = $args['order'] ?? 'DESC';
                $comparison = strtotime($a->post_date) <=> strtotime($b->post_date);
                return $order === 'DESC' ? -$comparison : $comparison;
            });
        }

        // Sort by title if specified
        if (($args['orderby'] ?? '') === 'title') {
            usort($posts, function ($a, $b) use ($args) {
                $order = $args['order'] ?? 'ASC';
                $comparison = strcasecmp($a->post_title, $b->post_title);
                return $order === 'DESC' ? -$comparison : $comparison;
            });
        }

        // Apply limit
        $limit = $args['posts_per_page'] ?? -1;
        if ($limit > 0) {
            $posts = array_slice(array_values($posts), 0, $limit);
        }

        return array_values($posts);
    }

    /**
     * Override count to use mock data.
     */
    public function count(array $args = []): int
    {
        $args['posts_per_page'] = -1;
        return count($this->query($args));
    }

    /**
     * Override exists to use mock data.
     */
    public function exists(array $args = []): bool
    {
        $args['posts_per_page'] = 1;
        return count($this->query($args)) > 0;
    }

    /**
     * Expose buildArgs for testing.
     */
    public function exposeBuildArgs(array $args): array
    {
        return $this->buildArgs($args);
    }

    /**
     * Expose postType for testing.
     */
    public function exposePostType(): string
    {
        return $this->postType();
    }

    /**
     * Override save to use mock data.
     */
    public function save(Post $post): int|\WP_Error
    {
        $this->savedPosts[] = $post;

        // If no ID, assign one (simulating insert)
        if (!$post->ID) {
            $post->ID = $this->nextId++;
        }

        // Add to mock posts
        $this->mockPosts[$post->ID] = $post;

        return $post->ID;
    }

    /**
     * Override delete to use mock data.
     */
    public function delete(Post $post, bool $forceDelete = false): bool
    {
        $this->deletedPosts[] = ['post' => $post, 'force' => $forceDelete];

        if (isset($this->mockPosts[$post->ID])) {
            unset($this->mockPosts[$post->ID]);
            return true;
        }

        return false;
    }

    /**
     * Get saved posts.
     *
     * @return Post[]
     */
    public function getSavedPosts(): array
    {
        return $this->savedPosts;
    }

    /**
     * Get deleted posts.
     */
    public function getDeletedPosts(): array
    {
        return $this->deletedPosts;
    }

    /**
     * Set current post ID for testing maybeExcludeCurrentPost.
     */
    public function setCurrentPostId(?int $id): self
    {
        $this->currentPostId = $id;
        return $this;
    }

    /**
     * Set singular state for testing maybeExcludeCurrentPost.
     */
    public function setIsSingular(bool $isSingular): self
    {
        $this->isSingular = $isSingular;
        return $this;
    }

    /**
     * Override maybeExcludeCurrentPost to use mock state.
     */
    protected function maybeExcludeCurrentPost(array $args): array
    {
        if (!$this->excludeCurrentPost || !$this->isSingular || !$this->currentPostId) {
            return $args;
        }

        $args['post__not_in'] = array_merge(
            (array) ($args['post__not_in'] ?? []),
            [$this->currentPostId]
        );

        return $args;
    }

    /**
     * Expose excludeCurrentPost for testing.
     */
    public function getExcludeCurrentPost(): bool
    {
        return $this->excludeCurrentPost;
    }
}
