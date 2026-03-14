<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Blog;

use ParentTheme\Providers\Blog\BlogPost;
use ParentTheme\Providers\Blog\BlogProvider as BaseBlogProvider;

/**
 * Blog Provider.
 *
 * Extends the parent BlogProvider with site-specific functionality.
 * Registers the BlogPost class map for the post type.
 */
class BlogProvider extends BaseBlogProvider
{
    /**
     * Register the blog provider with site-specific additions.
     */
    public function register(): void
    {
        parent::register();
    }

    /**
     * Enqueue styles on single post pages.
     */
    public function enqueueSingleAssets(): void
    {
        if (!is_singular(BlogPost::POST_TYPE)) {
            return;
        }

        $this->enqueueStyle('child-theme-blog-single', 'blog.css');
    }

    /**
     * Enqueue block assets for frontend and editor.
     */
    public function enqueueBlockAssets(): void
    {
        $this->enqueueStyle('child-theme-blog-block', 'blog.css');
    }
}
