<?php
/**
 * Timber starter-theme
 * https://github.com/timber/starter-theme
 */

// Load Composer dependencies.
require_once __DIR__ . '/vendor/autoload.php';

require_once __DIR__ . '/src/StarterSite.php';

Timber\Timber::init();

// Sets the directories (inside your theme) to find .twig files.
Timber::$dirname = [ 'templates', 'views', 'blocks' ];

new StarterSite();

/**
 * Safely retrieves the content of an SVG file from the theme's assets/images directory.
 *
 * @param string $filename The filename of the SVG (e.g., 'squiggle.svg').
 * @return string SVG content or an empty string if not found or invalid.
 */
// In functions.php

/**
 * Safely retrieves the content of an SVG file from the theme's assets/images directory.
 * Includes basic sanitization.
 *
 * @param string $filename The filename of the SVG (e.g., 'squiggle.svg').
 * @return string SVG content or an empty string if not found or invalid.
 */
function get_theme_svg( $filename ) {
    // Prevent directory traversal and ensure it's just the filename
    $filename = basename( $filename );
    if ( empty( $filename ) ) {
        // error_log('get_theme_svg: Empty filename provided.'); // Optional debug logging
        return '';
    }

    // Construct the full path
    $svg_path = get_template_directory() . '/assets/images/' . $filename;

    // Check if the file exists, is readable, and has a .svg extension
    if ( pathinfo( $svg_path, PATHINFO_EXTENSION ) === 'svg' && file_exists( $svg_path ) && is_readable( $svg_path ) ) {

        // Read the file content
        $svg_content = file_get_contents( $svg_path );

        // Check if reading failed
        if ($svg_content === false) {
            error_log('get_theme_svg: Failed to read file content for: ' . $svg_path);
            return '';
        }

        // Trim whitespace
        $sanitized_content = trim($svg_content);

        // Basic sanitization - focus on removing potentially harmful elements
        // 1. Remove script tags completely
        $sanitized_content = preg_replace( '#<script(.*?)>(.*?)</script>#is', '', $sanitized_content );
        // 2. Remove potentially dangerous event handlers (like onload, onerror etc.)
        $sanitized_content = preg_replace( '/\s(on\w+)=("|\').*?\2/is', '', $sanitized_content );
        // 3. Remove XML prolog - simplified and safer regex
        $sanitized_content = preg_replace('/^\s*<\?xml.*?\?>\s*/s', '', $sanitized_content);
        // 4. Remove DOCTYPE declarations which can be vectors for XXE
        $sanitized_content = preg_replace('/^<!DOCTYPE.+?>/is', '', $sanitized_content);
        // 5. Remove HTML comments
        $sanitized_content = preg_replace('//s', '', $sanitized_content);


        // Final check: Ensure the core <svg> tag is still present after sanitization
        // If not, something went wrong, return empty to be safe.
        if (stripos($sanitized_content, '<svg') === false) {
            error_log("get_theme_svg: Sanitization removed the main <svg> tag for: " . $filename);
            return '';
        }

        // Return the sanitized content if it's not empty
        return $sanitized_content ?: '';
    } else {
        // Log if file doesn't exist or isn't readable
        // if (!file_exists($svg_path)) error_log('get_theme_svg: File not found: ' . $svg_path);
        // elseif (!is_readable($svg_path)) error_log('get_theme_svg: File not readable: ' . $svg_path);
        // elseif (pathinfo( $svg_path, PATHINFO_EXTENSION ) !== 'svg') error_log('get_theme_svg: File is not SVG: ' . $svg_path);
    }

    return ''; // Return empty string if file not found, not readable, or invalid
}

// --- Make the SVG function available in Twig ---
add_filter( 'timber/twig', function( $twig ) {
    $twig->addFunction( new Twig\TwigFunction( 'get_theme_svg', 'get_theme_svg' ) );
    return $twig;
});


// --- Native Block Rendering ---

/**
 * Render callback function for the native Hero block.
 *
 * @param array    $attributes Block attributes.
 * @param string   $content    Block default content.
 * @param WP_Block $block      Block instance.
 * @return string HTML markup for the hero block.
 */
function vincentragosta_render_hero_block($attributes, $content, $block) {
    $title     = $attributes['title'] ?? '';
    $subtitle  = $attributes['subtitle'] ?? '';
    $links     = $attributes['links'] ?? [];
    $svg_asset = $attributes['svgAsset'] ?? '';
    $align     = $attributes['align'] ?? '';

    $wrapper_attributes = get_block_wrapper_attributes([
        'class' => $align ? 'align' . $align : '', // Add alignment class if present
    ]);

    // Ensure the SVG helper function exists (defined above)
    if (!function_exists('get_theme_svg')) {
        return '<p style="color: red;">Error: get_theme_svg function missing.</p>'; // Should not happen
    }

    ob_start(); // Start output buffering
    ?>
    <div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
        <div class="hero-block__content">
            <?php if ( ! empty( $title ) ) : ?>
                <h1 class="hero-block__title"><?php echo wp_kses_post( $title ); ?></h1>
            <?php endif; ?>

            <?php if ( ! empty( $subtitle ) ) : ?>
                <p class="hero-block__subtitle"><?php echo wp_kses_post( $subtitle ); ?></p>
            <?php endif; ?>

            <?php if ( ! empty( $links ) ) : ?>
                <div class="hero-block__links">
                    <?php foreach ( $links as $link ) : ?>
                        <?php
                        $url = $link['url'] ?? '#';
                        $text = $link['text'] ?? '';
                        $target = ! empty( $link['opensInNewTab'] ) ? '_blank' : '_self';
                        $rel = ! empty( $link['opensInNewTab'] ) ? 'noopener noreferrer' : '';
                        ?>
                        <?php if ( ! empty( $url ) && ! empty( $text ) ) : ?>
                            <a href="<?php echo esc_url( $url ); ?>"
                               class="wp-block-button__link hero-block__link" <?php // Apply button class ?>
                               target="<?php echo esc_attr( $target ); ?>"
                               <?php if ( $rel ) : ?>rel="<?php echo esc_attr( $rel ); ?>"<?php endif; ?>>
                                <?php echo esc_html( $text ); ?>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="hero-block__svg">
            <?php
            if ( ! empty( $svg_asset ) ) {
                // Render the SVG content safely using the helper
                echo get_theme_svg( $svg_asset ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG is sanitized in get_theme_svg
            }
            ?>
        </div>
    </div>
    <?php
    return ob_get_clean(); // Return buffered content
}

/**
 * Register native Gutenberg blocks.
 */
function vincentragosta_register_native_blocks() {
    register_block_type( get_template_directory() . '/blocks/hero', array(
        'render_callback' => 'vincentragosta_render_hero_block',
    ) );
    // Register other native blocks here...
}
add_action( 'init', 'vincentragosta_register_native_blocks' );


/**
 * Enqueue assets for the block editor and localize data.
 */
function vincentragosta_native_block_editor_assets() {
    $block_name = 'hero'; // Or derive from directory if needed
    $block_path = '/blocks/' . $block_name . '/build/'; // Path relative to theme root
    $asset_file_path = get_template_directory() . $block_path . 'index.asset.php';

    if ( ! file_exists( $asset_file_path ) ) {
        // Optional: Log error or display admin notice if build files are missing
        error_log("Block asset file not found: " . $asset_file_path);
        return;
    }

    $asset_file = include( $asset_file_path ); // Generated by @wordpress/scripts
    $script_handle = 'vincentragosta-' . $block_name . '-block-editor'; // Consistent handle

    // Enqueue the editor script
    wp_enqueue_script(
        $script_handle,
        get_template_directory_uri() . $block_path . 'index.js',
        $asset_file['dependencies'],
        $asset_file['version'],
        true // Load in footer
    );

    // Enqueue the editor style (can also be done via block.json)
    wp_enqueue_style(
        $script_handle . '-style', // Style handle often mirrors script handle + '-style'
        get_template_directory_uri() . $block_path . 'index.css',
        array('wp-edit-blocks'),
        $asset_file['version']
    );

    // --- Prepare data for wp_localize_script ---

    $svg_dir = get_template_directory() . '/assets/images/';
    $svg_options = [['label' => __('Select SVG', 'vincentragosta'), 'value' => '']];
    $svg_content_map = []; // Store SVG filename => content mapping

    if (is_dir($svg_dir)) {
        $svg_files = glob($svg_dir . '*.svg');
        if ($svg_files) {
            foreach ($svg_files as $file_path) {
                $filename = basename($file_path);
                // Create a nicer label (e.g., "Squiggle 1" from "squiggle-1.svg")
                $label = ucwords(str_replace(['-', '_', '.svg'], ' ', $filename));
                $svg_options[] = ['label' => $label, 'value' => $filename];

                // Get and store the sanitized SVG content
                $svg_content_map[$filename] = get_theme_svg($filename);
            }
        }
    }

    // Data to pass to JavaScript
    $localized_data = [
        'svgOptions' => $svg_options,
        'svgContent' => $svg_content_map,
        // Add any other data needed by the block's JS here
    ];

    // Localize the script with the data
    wp_localize_script(
        $script_handle,
        'vincentragostaHeroBlockData', // Unique JS object name
        $localized_data
    );
}
add_action( 'enqueue_block_editor_assets', 'vincentragosta_native_block_editor_assets' );

// --- REMOVED REST API Endpoint ---
// The add_action('rest_api_init', ...) and the vincentragosta_get_svg_rest_callback()
// function have been removed as they are no longer needed.


// --- Note: ACF related functions like theme_acf_block_render_callback and populate_svg_choices
// remain unchanged as they pertain to the separate ACF block definition. ---