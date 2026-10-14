<?php

namespace ChildTheme\Tests\Mocks;

use ChildTheme\Providers\Blog\NousPost;

/**
 * Mock NousPost for testing.
 *
 * Allows creating NousPost instances without Timber's constructor.
 */
class MockNousPost extends NousPost
{
    private array $mockTags = [];

    /**
     * Create a mock post with the given data.
     */
    public static function create(array $data = []): self
    {
        $post = new self();

        $defaults = [
            'ID' => 1,
            'post_title' => 'Test Signal',
            'post_content' => 'Test content.',
            'post_excerpt' => '',
            'post_status' => 'publish',
            'post_type' => 'post',
            'post_name' => 'test-signal',
            'post_date' => '2026-08-17 06:00:00',
            'post_author' => 1,
        ];

        foreach (array_merge($defaults, $data) as $key => $value) {
            $post->$key = $value;
        }

        return $post;
    }

    /**
     * Set mock tags as [slug, name] pairs.
     */
    public function setMockTags(array $tags): self
    {
        $this->mockTags = array_map(
            static fn(array $t): object => (object) ['slug' => $t[0], 'name' => $t[1]],
            $tags
        );

        return $this;
    }

    public function title(): string
    {
        return (string) $this->post_title;
    }

    public function link(): string
    {
        return 'https://example.com/' . $this->post_name . '/';
    }

    public function terms($query_args = [], $options = []): array
    {
        return $this->mockTags;
    }
}
