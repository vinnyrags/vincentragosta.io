<?php
/**
 * Server-side rendering for the Blog block (child theme override).
 *
 * In "all" mode, loads posts for a single month with a month dropdown
 * for archive navigation. Other modes delegate to the parent render
 * logic unchanged.
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
    // Query available months (single grouped SQL query).
    global $wpdb;
    $availableMonths = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT YEAR(post_date) AS year, MONTH(post_date) AS month, COUNT(*) AS count
             FROM {$wpdb->posts}
             WHERE post_type = %s AND post_status = %s
             GROUP BY YEAR(post_date), MONTH(post_date)
             ORDER BY year DESC, month DESC",
            'post',
            'publish'
        )
    );

    // Determine active month from URL param, validate, default to most recent.
    $monthParam = sanitize_text_field($_GET['month'] ?? '');
    $activeYear = null;
    $activeMonth = null;

    if (preg_match('/^(\d{4})-(\d{2})$/', $monthParam, $matches)) {
        $candidateYear = (int) $matches[1];
        $candidateMonth = (int) $matches[2];
        foreach ($availableMonths as $m) {
            if ((int) $m->year === $candidateYear && (int) $m->month === $candidateMonth) {
                $activeYear = $candidateYear;
                $activeMonth = $candidateMonth;
                break;
            }
        }
    }

    if (!$activeYear && !empty($availableMonths)) {
        $activeYear = (int) $availableMonths[0]->year;
        $activeMonth = (int) $availableMonths[0]->month;
    }

    // Query posts for the active month.
    if ($activeYear && $activeMonth) {
        $posts = $repository->query([
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
            'date_query' => [
                [
                    'year' => $activeYear,
                    'month' => $activeMonth,
                ],
            ],
        ]);
    } else {
        $posts = [];
    }

    $showToolbar = true;

    // Collect unique tags from the loaded posts.
    $tags = [];
    foreach ($posts as $post) {
        foreach ($post->tags() as $term) {
            if (!isset($tags[$term->slug])) {
                $tags[$term->slug] = $term->name;
            }
        }
    }
    ksort($tags);

    // Build month dropdown options.
    $monthOptions = [];
    foreach ($availableMonths as $m) {
        $value = sprintf('%04d-%02d', $m->year, $m->month);
        $timestamp = mktime(0, 0, 0, (int) $m->month, 1, (int) $m->year);
        $monthOptions[] = [
            'value' => $value,
            'label' => date_i18n('F Y', $timestamp),
        ];
    }

    $activeMonthValue = ($activeYear && $activeMonth)
        ? sprintf('%04d-%02d', $activeYear, $activeMonth)
        : '';
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
$context['month_options'] = $monthOptions ?? [];
$context['active_month'] = $activeMonthValue ?? '';

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
