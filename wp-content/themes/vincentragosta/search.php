<?php
/**
 * Template for search results.
 *
 * The main query (Relevanssi, 20 per page, optionally filtered to one type by
 * ?post_type= — see SearchSetup) supplies the results; one unfiltered ID-only
 * query supplies the per-type counts for the filter pills and summary.
 */

declare(strict_types=1);

use ChildTheme\Providers\Theme\SearchResults;
use Timber\Timber;

$context = Timber::context();
$query   = get_search_query();
$active  = SearchResults::activeType(sanitize_key($_GET['post_type'] ?? ''));

// Per-type counts across every searchable type.
$counts = [];
if ($query !== '') {
    $countQuery = new WP_Query();
    $countQuery->parse_query([
        's'              => $query,
        'post_type'      => array_keys(SearchResults::TYPES),
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ]);
    $ids = function_exists('relevanssi_do_query') ? relevanssi_do_query($countQuery) : $countQuery->get_posts();

    foreach ($ids as $id) {
        $type = get_post_type(is_object($id) ? $id->ID : (int) $id);
        $counts[$type] = ($counts[$type] ?? 0) + 1;
    }
}

// Result rows: type, title, a highlighted excerpt (Relevanssi's, or the
// post's own summary when it has no body text, e.g. projects), and meta.
$rows = [];
foreach (Timber::get_posts() as $post) {
    $type    = $post->post_type;
    $excerpt = trim(wp_kses((string) $post->post_excerpt, ['strong' => [], 'mark' => [], 'em' => []]));
    $meta    = [];

    if ($type === 'project') {
        $excerpt = $excerpt !== '' ? $excerpt : esc_html(wp_trim_words(wp_strip_all_tags($post->lead()), 32));
        $meta    = array_filter([$post->client(), $post->year()]);
    } elseif ($type === 'post') {
        $excerpt = $excerpt !== '' ? $excerpt : esc_html($post->shortExcerpt(32));
        $meta    = [$post->date('M j, Y'), $post->readingTime() . ' min'];
    } elseif ($excerpt === '') {
        $excerpt = esc_html((string) $post->meta('_yoast_wpseo_metadesc'));
    }

    $rows[] = [
        'type'    => $type,
        'label'   => SearchResults::TYPES[$type] ?? ucfirst($type),
        'title'   => $post->title(),
        'link'    => $post->link(),
        'excerpt' => $excerpt,
        'meta'    => implode(' · ', $meta),
    ];
}

$context['query']   = $query;
$context['rows']    = $rows;
$context['pills']   = SearchResults::pills($counts, $active);
$context['summary'] = SearchResults::summary($active === '' ? $counts : [$active => $counts[$active] ?? 0]);
$context['active']  = $active;
$context['search_form'] = do_blocks('<!-- wp:search {"label":"Search the site","showLabel":false,"placeholder":"Search projects, posts, pages…","buttonText":"Search"} /-->');

// Pagination (the main query is paged; keep the type filter on every link).
global $wp_query;
$context['pagination'] = $wp_query->max_num_pages > 1 ? paginate_links([
    'total'     => $wp_query->max_num_pages,
    'current'   => max(1, (int) get_query_var('paged')),
    'type'      => 'array',
    'prev_text' => '← Prev',
    'next_text' => 'Next →',
    'add_args'  => $active !== '' ? ['post_type' => $active] : false,
]) : [];

Timber::render('search.twig', $context);
