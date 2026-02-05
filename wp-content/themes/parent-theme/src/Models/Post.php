<?php

declare(strict_types=1);

namespace ParentTheme\Models;

use DateTime;
use Timber\Post as TimberPost;

/**
 * Base Post model class.
 *
 * Extends Timber\Post to provide additional functionality
 * while maintaining full Timber/Twig compatibility.
 */
class Post extends TimberPost
{
    /**
     * The post type identifier. Override in child classes.
     */
    public const POST_TYPE = 'post';

    /**
     * Get the post URL (alias for link()).
     */
    public function url(): string
    {
        return $this->link();
    }

    /**
     * Get the published date as a DateTime object.
     */
    public function publishedDate(): DateTime
    {
        return new DateTime($this->post_date);
    }

    /**
     * Get the modified date as a DateTime object.
     */
    public function modifiedDate(): DateTime
    {
        return new DateTime($this->post_modified);
    }

    /**
     * Check if the post is published.
     */
    public function isPublished(): bool
    {
        return $this->post_status === 'publish';
    }

    /**
     * Check if the post is a draft.
     */
    public function isDraft(): bool
    {
        return $this->post_status === 'draft';
    }

    /**
     * Get a meta value.
     */
    public function getMeta(string $key, bool $single = true): mixed
    {
        return get_post_meta($this->ID, $key, $single);
    }

    /**
     * Set a meta value.
     */
    public function setMeta(string $key, mixed $value): bool
    {
        return (bool) update_post_meta($this->ID, $key, $value);
    }

    /**
     * Delete a meta value.
     */
    public function deleteMeta(string $key): bool
    {
        return delete_post_meta($this->ID, $key);
    }

    /**
     * Check if post has a specific term.
     */
    public function hasTerm(string|int $term, string $taxonomy): bool
    {
        return has_term($term, $taxonomy, $this->ID);
    }

    /**
     * Refresh the post data from the database.
     */
    public function refresh(): void
    {
        $this->wp_object = null;
        $wp_post = get_post($this->ID);

        if ($wp_post) {
            $this->import(get_object_vars($wp_post));
        }
    }
}
