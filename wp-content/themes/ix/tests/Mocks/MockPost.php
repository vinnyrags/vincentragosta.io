<?php

namespace IX\Tests\Mocks;

use IX\Models\Post;

/**
 * Mock Post class for testing.
 *
 * Allows creating Post instances without Timber's protected constructor.
 */
class MockPost extends Post
{
    private array $mockMeta = [];
    private array $mockTerms = [];
    private bool $metaUpdated = false;
    private bool $metaDeleted = false;
    private bool $refreshed = false;
    private ?string $lastDeletedMetaKey = null;
    private array $lastSetMeta = [];

    /**
     * Create a mock post with the given data.
     */
    public static function create(array $data = []): self
    {
        $post = new self();

        // Set default values
        $defaults = [
            'ID' => 1,
            'post_title' => 'Test Post',
            'post_content' => 'Test content.',
            'post_excerpt' => 'Test excerpt.',
            'post_status' => 'publish',
            'post_type' => 'post',
            'post_name' => 'test-post',
            'post_date' => '2024-01-15 10:00:00',
            'post_modified' => '2024-01-16 12:00:00',
            'post_author' => 1,
        ];

        $data = array_merge($defaults, $data);

        // Set properties directly
        foreach ($data as $key => $value) {
            $post->$key = $value;
        }

        return $post;
    }

    /**
     * Override link() for testing.
     */
    public function link(): string
    {
        return 'https://example.com/' . ($this->post_name ?? 'test-post') . '/';
    }

    /**
     * Set mock meta data.
     */
    public function setMockMeta(array $meta): self
    {
        $this->mockMeta = $meta;
        return $this;
    }

    /**
     * Override getMeta for testing.
     */
    public function getMeta(string $key, bool $single = true): mixed
    {
        if (isset($this->mockMeta[$key])) {
            return $single ? $this->mockMeta[$key] : [$this->mockMeta[$key]];
        }
        return $single ? '' : [];
    }

    /**
     * Set mock terms.
     */
    public function setMockTerms(string $taxonomy, array $terms): self
    {
        $this->mockTerms[$taxonomy] = $terms;
        return $this;
    }

    /**
     * Override hasTerm for testing.
     */
    public function hasTerm(string|int $term, string $taxonomy): bool
    {
        if (!isset($this->mockTerms[$taxonomy])) {
            return false;
        }

        return in_array($term, $this->mockTerms[$taxonomy], true);
    }

    /**
     * Override terms() for testing.
     */
    public function terms($query_args = [], $options = []): array
    {
        $taxonomy = $query_args['taxonomy'] ?? 'category';
        return $this->mockTerms[$taxonomy] ?? [];
    }

    /**
     * Override setMeta for testing.
     */
    public function setMeta(string $key, mixed $value): bool
    {
        $this->mockMeta[$key] = $value;
        $this->metaUpdated = true;
        $this->lastSetMeta = ['key' => $key, 'value' => $value];
        return true;
    }

    /**
     * Override deleteMeta for testing.
     */
    public function deleteMeta(string $key): bool
    {
        $this->metaDeleted = true;
        $this->lastDeletedMetaKey = $key;
        unset($this->mockMeta[$key]);
        return true;
    }

    /**
     * Override refresh for testing.
     */
    public function refresh(): void
    {
        $this->refreshed = true;
    }

    /**
     * Check if setMeta was called.
     */
    public function wasMetaUpdated(): bool
    {
        return $this->metaUpdated;
    }

    /**
     * Get the last set meta data.
     */
    public function getLastSetMeta(): array
    {
        return $this->lastSetMeta;
    }

    /**
     * Check if deleteMeta was called.
     */
    public function wasMetaDeleted(): bool
    {
        return $this->metaDeleted;
    }

    /**
     * Get the last deleted meta key.
     */
    public function getLastDeletedMetaKey(): ?string
    {
        return $this->lastDeletedMetaKey;
    }

    /**
     * Check if refresh was called.
     */
    public function wasRefreshed(): bool
    {
        return $this->refreshed;
    }

    /**
     * Reset tracking flags.
     */
    public function resetTracking(): void
    {
        $this->metaUpdated = false;
        $this->metaDeleted = false;
        $this->refreshed = false;
        $this->lastDeletedMetaKey = null;
        $this->lastSetMeta = [];
    }
}
