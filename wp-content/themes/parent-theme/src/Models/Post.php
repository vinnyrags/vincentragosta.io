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
     * Magic method for dynamic ACF field access.
     *
     * Routes zero-argument method calls through getField() with
     * automatic camelCase to snake_case conversion. This allows
     * $post->externalUrl() to resolve to getField('external_url').
     */
    public function __call($field, $arguments)
    {
        if (empty($arguments)) {
            $fieldKey = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $field));
            return $this->getField($fieldKey);
        }

        return parent::__call($field, $arguments);
    }

    /**
     * Get an ACF field value, with fallback to post meta.
     */
    public function getField(string $key): mixed
    {
        if (function_exists('get_field')) {
            return get_field($key, $this->ID) ?: '';
        }

        return $this->getMeta($key);
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
     * Get the post's categories.
     *
     * @return \Timber\Term[]
     */
    public function categories(): array
    {
        return $this->terms(['taxonomy' => 'category']);
    }

    /**
     * Get the first category name.
     */
    public function categoryName(): ?string
    {
        $categories = $this->categories();
        return $categories[0]->name ?? null;
    }

    /**
     * Get the first category slug.
     */
    public function categorySlug(): ?string
    {
        $categories = $this->categories();
        return $categories[0]->slug ?? null;
    }

    /**
     * Get all category slugs as a space-separated string.
     */
    public function categorySlugs(): string
    {
        return implode(' ', array_map(
            fn ($term) => $term->slug,
            $this->categories(),
        ));
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
