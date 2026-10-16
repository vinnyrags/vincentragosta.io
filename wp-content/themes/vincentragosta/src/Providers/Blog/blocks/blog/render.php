<?php
/**
 * Server-side rendering for the Blog block (child theme override).
 *
 * "all" renders the Nous Signal archive: stats, the latest transmission,
 * month pills, and the month-grouped index (view model: NousArchive;
 * behavior: view.js). A ?tag= deep link (post tag links point here) renders
 * a removable tag filter. Other modes fall through to the parent renderer.
 *
 * @var array $attributes Block attributes.
 */

declare(strict_types=1);

use ChildTheme\Providers\Blog\NousArchive;
use IX\Providers\Blog\BlogRepository;
use IX\Theme;
use Timber\Timber;

if (($attributes['displayMode'] ?? 'latest') !== 'all') {
    require get_template_directory() . '/src/Providers/Blog/blocks/blog/render.php';
    return;
}

$repository = Theme::container()->get(BlogRepository::class);
$posts = $repository->query([
    'posts_per_page' => -1,
    'orderby'        => 'date',
    'order'          => 'DESC',
]);

$context = Timber::context();
$context['archive'] = (new NousArchive($posts))->toArray();

$tagSlug = sanitize_title(wp_unslash($_GET['tag'] ?? ''));
$tag = $tagSlug !== '' ? get_term_by('slug', $tagSlug, 'post_tag') : false;
$context['tag'] = $tag ? ['slug' => $tag->slug, 'name' => html_entity_decode($tag->name, ENT_QUOTES)] : null;

$wrapper_attributes = get_block_wrapper_attributes(['class' => 'is-archive']);

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() is escaped by core.
echo '<div ' . $wrapper_attributes . '>';
Timber::render(__DIR__ . '/archive.twig', $context);
echo '</div>';
