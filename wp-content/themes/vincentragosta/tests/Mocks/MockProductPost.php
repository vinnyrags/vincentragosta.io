<?php

namespace ChildTheme\Tests\Mocks;

use ChildTheme\Providers\Shop\ProductPost;

/**
 * Mock ProductPost for testing.
 *
 * Allows creating ProductPost instances without Timber's protected constructor.
 */
class MockProductPost extends ProductPost
{
    private array $mockMeta = [];

    /**
     * Create a mock product post with the given data.
     */
    public static function create(array $data = [], array $meta = []): self
    {
        $post = new self();

        $defaults = [
            'ID' => 1,
            'post_title' => 'Test Product',
            'post_status' => 'publish',
            'post_type' => ProductPost::POST_TYPE,
            'post_name' => 'test-product',
        ];

        $data = array_merge($defaults, $data);

        foreach ($data as $key => $value) {
            $post->$key = $value;
        }

        // Timber uses lowercase `id` as an alias for `ID`
        if (isset($data['ID'])) {
            $post->id = $data['ID'];
        }

        $post->mockMeta = $meta;

        return $post;
    }

    /**
     * Override getField to return mock data directly (bypasses ACF/meta lookup).
     */
    public function getField(string $key): mixed
    {
        return $this->mockMeta[$key] ?? '';
    }

    /**
     * Override getMeta for testing — returns mock data.
     */
    public function getMeta(string $key, bool $single = true): mixed
    {
        if (isset($this->mockMeta[$key])) {
            return $single ? $this->mockMeta[$key] : [$this->mockMeta[$key]];
        }
        return $single ? '' : [];
    }
}
