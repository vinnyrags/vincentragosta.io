<?php
/**
 * Server-side rendering for the Nous Signal Log block.
 *
 * The latest transmissions (posts), numbered TX·{n} from the total published
 * count so numbers stay stable as the feed grows. The newest is marked "new".
 */

use ChildTheme\Providers\Blog\NousLog;
use Timber\Timber;

$query = new WP_Query([
    'post_type'           => 'post',
    'post_status'         => 'publish',
    'posts_per_page'      => NousLog::ROWS,
    'ignore_sticky_posts' => true,
    'no_found_rows'       => true,
]);

$context = Timber::context();
$context['rows'] = NousLog::rows(
    array_map(
        static fn(WP_Post $post): array => [
            'title' => get_the_title($post),
            'link'  => get_permalink($post),
        ],
        $query->posts
    ),
    (int) (wp_count_posts('post')->publish ?? 0)
);

echo '<div ' . get_block_wrapper_attributes(['class' => 'nous-log']) . '>';
Timber::render(__DIR__ . '/log.twig', $context);
echo '</div>';
