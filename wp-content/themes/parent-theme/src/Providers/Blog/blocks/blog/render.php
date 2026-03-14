<?php
/**
 * Server-side rendering for the Blog block.
 *
 * @var array $attributes Block attributes.
 */

use ParentTheme\Providers\Blog\BlogRepository;
use ParentTheme\Theme;
use Timber\Timber;

$mode = $attributes['displayMode'] ?? 'latest';
$perPage = (int) ($attributes['postsPerPage'] ?? 6);
$category = $attributes['category'] ?? '';

$repository = Theme::container()->get(BlogRepository::class);

if ($mode === 'category' && !empty($category)) {
    $posts = $repository->byCategory($category, $perPage);
} elseif ($mode === 'all') {
    $posts = $repository->all();
} else {
    $posts = $repository->latest($perPage);
}

$context = Timber::context();
$context['posts'] = $posts;
$context['display_mode'] = $mode;

$wrapper_attributes = get_block_wrapper_attributes();

echo '<div ' . $wrapper_attributes . '>';
Timber::render(__DIR__ . '/grid.twig', $context);
echo '</div>';
