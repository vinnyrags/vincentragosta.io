<?php
/**
 * Server-side rendering for the Shutter Cards block.
 */

$cards = $attributes['cards'] ?? [];
$wrapper_attributes = get_block_wrapper_attributes();

?>
<div <?php echo $wrapper_attributes; ?>>
    <div class="shutter-cards-container">
        <?php foreach ($cards as $index => $card) : ?>
            <?php
            $title       = $card['title'] ?? '';
            $subtitle    = $card['subtitle'] ?? '';
            $description = $card['description'] ?? '';
            $is_open     = ($index === 0) ? ' is-open' : '';
            ?>
            <div class="shutter-card<?php echo esc_attr($is_open); ?>" tabindex="0">
                <div class="shutter-card__content">
                    <?php if (!empty($subtitle)) : ?>
                        <span class="shutter-card__subtitle"><?php echo esc_html($subtitle); ?></span>
                    <?php endif; ?>
                    <div class="shutter-card__description">
                        <?php echo wp_kses_post($description); ?>
                    </div>
                </div>
                <h3 class="shutter-card__title"><?php echo esc_html($title); ?></h3>
            </div>
        <?php endforeach; ?>
    </div>
</div>