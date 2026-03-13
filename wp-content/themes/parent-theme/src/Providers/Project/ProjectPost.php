<?php

declare(strict_types=1);

namespace ParentTheme\Providers\Project;

use ParentTheme\Models\Post;

/**
 * Project post model.
 *
 * Minimal base model for the project post type.
 * Child themes can extend this with site-specific methods.
 */
class ProjectPost extends Post
{
    public const POST_TYPE = 'project';
}
