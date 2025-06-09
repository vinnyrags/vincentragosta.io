<?php
/**
 * Server-side rendering for the Hero block.
 *
 * This file is referenced in block.json in the 'render' key.
 *
 * @param array $attributes Block attributes.
 * @param string $content The block content (serialized HTML of inner blocks).
 * @param WP_Block $block The block instance.
 * @return string HTML markup for the hero block.
 */

/** @var array $attributes */
/** @var string $content */
/** @var WP_Block $block */

$title = $attributes['title'] ?? '';
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
    <?php if (!empty($video_url)) : ?>
        <video class="hero__video" src="<?= esc_url($video_url); ?>" autoplay muted loop playsinline></video>
    <?php endif; ?>
    <div class="hero__svg">
        <?php if (!empty($svg_asset)) : ?>
            <?php
            if (function_exists('get_theme_svg')) {
                echo get_theme_svg($svg_asset, false);
            } else {
                error_log('Error in hero/render.php: Global function get_theme_svg() not found.');
            }
            ?>
        <?php endif; ?>
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