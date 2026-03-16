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
$perPage = (int) ($attributes['postsPerPage'] ?? get_option('posts_per_page', 10));
$category = $attributes['category'] ?? '';
$currentPage = max(1, (int) (get_query_var('paged') ?: get_query_var('page') ?: 1));

$repository = Theme::container()->get(BlogRepository::class);

$totalPages = 1;

if ($mode === 'category' && !empty($category)) {
    $posts = $repository->byCategory($category, $perPage);
} elseif ($mode === 'all') {
    $result = $repository->paginated($currentPage, $perPage);
    $posts = $result['posts'];
    $totalPages = $result['total_pages'];
} else {
    $result = $repository->paginated($currentPage, $perPage);
    $posts = $result['posts'];
    $totalPages = $result['total_pages'];
}

$context = Timber::context();
$context['posts'] = $posts;
$context['display_mode'] = $mode;
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
