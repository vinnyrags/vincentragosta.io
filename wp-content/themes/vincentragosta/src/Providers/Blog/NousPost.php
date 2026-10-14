<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Blog;

use IX\Providers\Blog\BlogPost;

/**
 * A Nous Signal transmission (the built-in post type).
 */
class NousPost extends BlogPost
{
    /** Reading speed used for the read-time estimate, in words per minute. */
    public const WORDS_PER_MINUTE = 230;

    /**
     * Word count of the rendered text (markup and block comments stripped).
     */
    public function wordCount(): int
    {
        return str_word_count(wp_strip_all_tags((string) $this->post_content));
    }

    /**
     * Estimated reading time in whole minutes (at least one).
     */
    public function readingTime(): int
    {
        return max(1, (int) ceil($this->wordCount() / self::WORDS_PER_MINUTE));
    }

    /**
     * Publish date as Y-m-d, read from the raw post date so it needs no
     * timezone or locale lookups.
     */
    public function publishedOn(): string
    {
        return substr((string) $this->post_date, 0, 10);
    }
}
