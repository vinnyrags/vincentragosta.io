<?php
/**
 * Server-side rendering for the Blog block (child theme override).
 *
 * In "all" mode, loads every post for client-side search and sort.
 * Other modes delegate to the parent render logic unchanged.
 *
 * @var array $attributes Block attributes.
 */

use IX\Providers\Blog\BlogRepository;
use IX\Theme;
use Timber\Timber;

$mode = $attributes['displayMode'] ?? 'latest';
$perPage = (int) ($attributes['postsPerPage'] ?? get_option('posts_per_page', 10));
$category = $attributes['category'] ?? '';
$currentPage = max(1, (int) (get_query_var('paged') ?: get_query_var('page') ?: 1));

$repository = Theme::container()->get(BlogRepository::class);

$totalPages = 1;

if ($mode === 'all') {
    // Load all posts for client-side search/sort/filter.
    $posts = $repository->all();
    $showToolbar = true;

    // Collect unique tags for the filter dropdown.
    $tags = [];
    foreach ($posts as $post) {
        foreach ($post->tags() as $term) {
            if (!isset($tags[$term->slug])) {
                $tags[$term->slug] = $term->name;
            }
        }
    }
    ksort($tags);
} elseif ($mode === 'category' && !empty($category)) {
    $posts = $repository->byCategory($category, $perPage);
    $showToolbar = false;
} else {
    $result = $repository->paginated($currentPage, $perPage);
    $posts = $result['posts'];
    $totalPages = $result['total_pages'];
    $showToolbar = false;
}

$context = Timber::context();
$context['posts'] = $posts;
$context['display_mode'] = $mode;
$context['show_toolbar'] = $showToolbar;
$context['tags'] = $tags ?? [];

// Build base URL by stripping any existing /page/N/ from the current URL.
$baseUrl = untrailingslashit(get_pagenum_link(1));
$baseUrl = preg_replace('#/page/\d+/?$#', '', $baseUrl);
$baseUrl = trailingslashit($baseUrl);

$context['pagination'] = [
    'current_page' => $currentPage,
    'total_pages' => $totalPages,
    'base_url' => $baseUrl,
];

$wrapper_attributes = get_block_wrapper_attributes();

echo '<div ' . $wrapper_attributes . '>';
Timber::render(__DIR__ . '/grid.twig', $context);
echo '</div>';
