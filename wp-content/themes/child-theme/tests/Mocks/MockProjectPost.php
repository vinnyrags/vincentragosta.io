<?php

namespace ChildTheme\Tests\Mocks;

use ChildTheme\Providers\Project\ProjectPost;

/**
 * Mock ProjectPost for testing.
 *
 * Allows creating ProjectPost instances without Timber's constructor.
 */
class MockProjectPost extends ProjectPost
{
    private array $mockMeta = [];
    private array $mockTerms = [];

    /**
     * Create a mock project post with the given data.
     */
    public static function create(array $data = []): self
    {
        $post = new self();

        $defaults = [
            'ID' => 1,
            'post_title' => 'Test Project',
            'post_content' => 'Test content.',
            'post_excerpt' => 'Test excerpt.',
            'post_status' => 'publish',
            'post_type' => 'project',
            'post_name' => 'test-project',
            'post_date' => '2024-01-15 10:00:00',
            'post_modified' => '2024-01-16 12:00:00',
            'post_author' => 1,
        ];

        $data = array_merge($defaults, $data);

        foreach ($data as $key => $value) {
            $post->$key = $value;
        }

        return $post;
    }

    /**
     * Set mock meta data (used by getField fallback).
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
     * Override link() for testing.
     */
    public function link(): string
    {
        return 'https://example.com/projects/' . ($this->post_name ?? 'test-project') . '/';
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
     * Override terms() for testing.
     */
    public function terms($query_args = [], $options = []): array
    {
        $taxonomy = $query_args['taxonomy'] ?? 'category';
        return $this->mockTerms[$taxonomy] ?? [];
    }
}
