<?php

declare(strict_types=1);

namespace IX\Providers\Blog;

use IX\Models\Post;

/**
 * Blog post model.
 *
 * Minimal base model for the built-in post type.
 * Child themes can extend this with site-specific methods.
 */
class BlogPost extends Post
{
    public const POST_TYPE = 'post';

    /**
     * Get a truncated excerpt as plain text.
     *
     * @param int $length Maximum word count.
     */
    public function shortExcerpt(int $length = 25): string
    {
        $excerpt = $this->post_excerpt ?: wp_strip_all_tags($this->post_content);

        return wp_trim_words($excerpt, $length, '&hellip;');
    }

    /**
     * Estimate reading time in minutes.
     */
    public function readingTime(): int
    {
        $wordCount = str_word_count(wp_strip_all_tags($this->post_content));

        return max(1, (int) ceil($wordCount / 200));
    }
}
