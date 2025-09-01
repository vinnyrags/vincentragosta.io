<?php
/**
 * Server-side rendering for the Shutter Cards container block.
 *
 * Expected:
 * @var array    $attributes
 * @var string   $content
 * @var WP_Block $block
 */

$card_count = 0;
if (isset($block) && !empty($block->inner_blocks)) {
    $card_count = count($block->inner_blocks);
}

$modified_content = $content;

// If there are two or more cards, append the 'shutter-card--inactive' class.
if ($card_count >= 2) {
    // Define what we're looking for.
    $search = 'wp-block-vincentragosta-shutter-card';

    // Define what we'll replace it with (the original class plus our new inactive class).
    $replace = 'wp-block-vincentragosta-shutter-card shutter-card--inactive';

    // Perform the replacement on the rendered inner block content.
    $modified_content = str_replace($search, $replace, $content);
}

$wrapper_attributes = get_block_wrapper_attributes();
?>
<div <?php echo $wrapper_attributes; ?>>
    <div class="shutter-cards-container">
        <?php echo $modified_content; ?>
    </div>
</div>