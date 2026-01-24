<?php
/**
 * Server-side rendering for the Hero block.
 *
 * This file is referenced in block.json in the 'render' key.
 *
 * @param array    $attributes Block attributes.
 * @param string   $content    The block content (serialized HTML of inner blocks).
 * @param WP_Block $block      The block instance.
 * @return string HTML markup for the hero block.
 */

use ChildTheme\Services\Icon;

/** @var array $attributes */
/** @var string $content */
/** @var WP_Block $block */

$title     = $attributes['title'] ?? '';
$svg_asset = $attributes['svgAsset'] ?? '';
$video_url = $attributes['videoUrl'] ?? '';

// Add a class to the block if a video URL is provided.
$block_classes = ['hero'];
if (!empty($video_url)) {
    $block_classes[] = 'hero--has-video';
}
$wrapper_attributes = get_block_wrapper_attributes(['class' => implode(' ', $block_classes)]);

?>
<div <?= $wrapper_attributes; ?>>
    <?php // The video is rendered first to act as a background layer controlled by CSS.
    if (!empty($video_url)) : ?>
        <video class="hero__video" src="<?= esc_url($video_url); ?>" autoplay muted loop playsinline></video>
    <?php endif; ?>

    <div class="hero__svg">
        <?php // The SVG graphic itself is only rendered if NO video URL is provided.
        if (empty($video_url) && !empty($svg_asset)) {
            echo new Icon($svg_asset);
        }
        ?>
    </div>

    <div class="hero__content">
        <?php if (!empty($title)) : ?>
            <div class="hero__mask">
                <h1 class="hero__title"><?= wp_kses_post($title); ?></h1>
            </div>
        <?php endif; ?>

        <?php if (!empty($content)) : ?>
            <div class="hero__links">
                <?= $content; ?>
            </div>
        <?php endif; ?>
    </div>
</div>