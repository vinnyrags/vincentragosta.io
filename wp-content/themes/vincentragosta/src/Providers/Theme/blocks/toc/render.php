<?php
/**
 * Server-side rendering for the Contents (TOC) block.
 *
 * Lists the page's anchored H2s (see TableOfContents) with a progress bar;
 * the shared toc.js script marks the section being read.
 */

declare(strict_types=1);

use ChildTheme\Providers\Theme\TableOfContents;
use Timber\Timber;

$postId = (int) ($post_id ?? get_the_ID());
$content = $postId ? (string) get_post_field('post_content', $postId) : '';

$context = Timber::context();
$context['heading'] = get_field('heading') ?: __('On this page', 'vincentragosta');
$context['entries'] = TableOfContents::fromBlocks(parse_blocks($content));
$context['uid'] = wp_unique_id('toc-');

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() is escaped by core.
echo '<div ' . get_block_wrapper_attributes() . '>';
Timber::render(__DIR__ . '/toc.twig', $context);
echo '</div>';
