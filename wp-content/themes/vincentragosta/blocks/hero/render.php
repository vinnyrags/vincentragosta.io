<?php
/**
 * Server-side rendering for the Hero block.
 *
 * @param array    $attributes Block attributes.
 * @param string   $content    The block content (inner blocks HTML).
 * @param WP_Block $block      The block instance.
 */

use ChildTheme\Services\Icon;

$svg_asset = $attributes['svgAsset'] ?? '';
$video_url = $attributes['videoUrl'] ?? '';

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
        <?php if (empty($video_url) && !empty($svg_asset)) : ?>
            <?= new Icon($svg_asset); ?>
        <?php endif; ?>
    </div>

    <div class="hero__content">
        <?= $content; ?>
    </div>
</div>
