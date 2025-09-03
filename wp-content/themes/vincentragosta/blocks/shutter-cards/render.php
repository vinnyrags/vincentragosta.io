<?php
/**
 * Server-side rendering for the Shutter Cards container block.
 */

$wrapper_attributes = get_block_wrapper_attributes();
$inner_blocks = isset($block) ? $block->inner_blocks : [];
$card_count = count($inner_blocks);
$container_styles = '';

// If we have more than one card, calculate the inactive width and set it as a CSS variable.
if ($card_count > 1) {
    $inactive_width_percentage = 60 / $card_count;
    $container_styles = sprintf('style="--card-inactive-width: %f%%;"', $inactive_width_percentage);
}
?>
<div <?php echo $wrapper_attributes; ?>>
    <div class="shutter-cards-container" <?php echo $container_styles; ?>>
        <?php if (!empty($inner_blocks)) : ?>
            <?php foreach ($inner_blocks as $index => $inner_block) : ?>
                <?php
                $inner_block_content = $inner_block->render();

                // Add the 'inactive' class to all cards except the first one.
                if ($card_count >= 2 && $index > 0) {
                    $search = 'wp-block-vincentragosta-shutter-card';
                    $replace = 'wp-block-vincentragosta-shutter-card shutter-card--inactive';
                    echo str_replace($search, $replace, $inner_block_content);
                } else {
                    echo $inner_block_content;
                }
                ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>