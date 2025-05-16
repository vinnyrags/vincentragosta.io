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
Timber::$dirname = ['templates', 'views', 'blocks'];

new StarterSite();

/**
 * Safely retrieves the content of an SVG file from the theme's assets/images directory.
 * Includes basic sanitization.
 *
 * @param string $filename The filename of the SVG (e.g., 'squiggle.svg').
 * @return string SVG content or an empty string if not found or invalid.
 */
function get_theme_svg($filename)
{
    // Prevent directory traversal and ensure it's just the filename
    $filename = basename($filename);
    if (empty($filename)) {
        return '';
    }

    // Construct the full path
    $svg_path = get_template_directory() . '/assets/images/' . $filename;

    // Check if the file exists, is readable, and has a .svg extension
    if (pathinfo($svg_path, PATHINFO_EXTENSION) === 'svg' && file_exists($svg_path) && is_readable($svg_path)) {

        // Read the file content
        $svg_content = file_get_contents($svg_path);

        // *** ADD THIS CHECK ***
        // Check if reading failed OR content is not a string (e.g., file was empty or had non-string data)
        if ($svg_content === false || !is_string($svg_content)) {
            // Log a more informative error
            error_log('get_theme_svg: Failed to read file content or content is not string for: ' . $svg_path);
            return ''; // Return empty string immediately on read failure
        }
        // *** END ADDITION ***


        // Trim whitespace
        $sanitized_content = trim($svg_content);

        // Basic sanitization - focus on removing potentially harmful elements
        // ... (rest of your sanitization code - looks OK)
        $sanitized_content = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $sanitized_content);
        $sanitized_content = preg_replace('/\s(on\w+)=("|\').*?\2/is', '', $sanitized_content);
        $sanitized_content = preg_replace('/^\s*<\?xml.*?\?>\s*/s', '', $sanitized_content);
        $sanitized_content = preg_replace('/^<!DOCTYPE.+?>/is', '', $sanitized_content);
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
        // (Your existing commented-out logs are fine, or add active logs if needed)
        // if (!file_exists($svg_path)) error_log('get_theme_svg: File not found: ' . $svg_path);
        // elseif (!is_readable($svg_path)) error_log('get_theme_svg: File not readable: ' . $svg_path);
        // elseif (pathinfo( $svg_path, PATHINFO_EXTENSION ) !== 'svg') error_log('get_theme_svg: File is not SVG: ' . $svg_path);
    }

    return ''; // Return empty string if file not found, not readable, or invalid
}

// --- Make the SVG function available in Twig ---
add_filter('timber/twig', function ($twig) {
    // Using the standalone get_theme_svg function directly
    $twig->addFunction(new Twig\TwigFunction('get_theme_svg', 'get_theme_svg'));
    return $twig;
});


// --- Native Block Rendering ---

/**
 * Render callback function for the native Hero block.
 *
 * @param array $attributes Block attributes.
 * @param string $content Block default content (inner blocks markup).
 * @param WP_Block $block Block instance.
 * @return string HTML markup for the hero block.
 */
function vincentragosta_render_hero_block($attributes, $content, $block)
{
    $title = $attributes['title'] ?? '';
    $subtitle = $attributes['subtitle'] ?? '';
    $svg_asset = $attributes['svgAsset'] ?? '';

    // get_block_wrapper_attributes automatically includes alignment and InnerBlocks classes
    $wrapper_attributes = get_block_wrapper_attributes();

    ob_start(); // Start output buffering
    ?>
    <div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
        <div class="hero-block__content">
            <?php if (!empty($title)) : ?>
                <h1 class="hero-block__title"><?php echo wp_kses_post($title); ?></h1>
            <?php endif; ?>

            <?php if (!empty($subtitle)) : ?>
                <p class="hero-block__subtitle"><?php echo wp_kses_post($subtitle); ?></p>
            <?php endif; ?>

            <?php
            // RENDER INNER BLOCKS (THE BUTTONS)
            // The $content variable contains the rendered markup of all inner blocks.
            // If saving is working correctly, $content will contain the HTML for the core/buttons block.
            if (!empty($content)) :
                // Wrap inner blocks in the existing .hero-block__links div for layout
                echo '<div class="hero-block__links">';
                echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $content is rendered block markup
                echo '</div>';
            else :
                // This fallback indicates a save failure if you expect buttons to be there.
                // Only show this debugging comment in the editor.
                if (current_user_can('edit_posts') && is_admin()) {
                    echo ''; // Empty string as per user's provided code
                }
            endif;
            ?>
        </div>
        <div class="hero-block__svg">
            <?php
            if (!empty($svg_asset)) {
                // Render the SVG content safely using the helper
                echo get_theme_svg($svg_asset); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG is sanitized in get_theme_svg
            }
            ?>
        </div>
    </div>
    <?php
    return ob_get_clean(); // Return buffered content
}

/**
 * Register native Gutenberg blocks from specific directories.
 */
function vincentragosta_register_native_blocks()
{
    // Array of block directories (relative to theme root /blocks/)
    $blocks = [
        'hero', // Register the hero block directory
        'inner-block-test', // Register the inner-block-test block directory
    ];

    foreach ($blocks as $block_name) {
        $block_directory = get_template_directory() . '/blocks/' . $block_name;

        // Check if block.json exists in the directory
        if (file_exists($block_directory . '/block.json')) {
            // register_block_type() will automatically read block.json
            // and register the block with its defined settings (attributes, supports, render, scripts, styles).
            // If block.json has a "render" key, that will be used as the callback.
            // We DO NOT pass the 'render_callback' argument here if "render" is in block.json.
            register_block_type($block_directory);
        } else {
            // Log an error if block.json is missing, helpful for debugging during development
            error_log("Block configuration file not found for block: " . $block_name . " at " . $block_directory . "/block.json");
        }
    }
}

// Hook block registration to the 'init' action
add_action('init', 'vincentragosta_register_native_blocks');

// Note: The vincentragosta_render_hero_block function in this file is now unused
// because block.json's render key takes precedence. You can remove it if you like,
// or keep it for reference if you prefer.

/**
 * Enqueue assets for the block editor and localize data.
 *
 * This function is primarily needed for wp_localize_script.
 * The block's editorScript and editorStyle are automatically enqueued
 * by WordPress because they are defined in block.json with the 'file:' prefix.
 */
function vincentragosta_native_block_editor_assets() {
    error_log('>>> HERO BLOCK LOCALIZATION DEBUG: Function started.');

    $block_name = 'hero';
    $block_directory = get_template_directory() . '/blocks/' . $block_name;
    $asset_file_path = $block_directory . '/build/index.asset.php';

    // Check if the asset file exists (generated by wp-scripts build)
    if ( ! file_exists( $asset_file_path ) ) {
        error_log('>>> HERO BLOCK LOCALIZATION DEBUG: ERROR - Asset file not found: ' . $asset_file_path);
        error_log('>>> HERO BLOCK LOCALIZATION DEBUG: Function finished.');
        return; // Stop execution if asset file is missing
    }

    // We include the asset file to get dependencies and version,
    // but the handle isn't the main key of the returned array.
    $asset_file = include( $asset_file_path );

    // *** HARDCODE THE CORRECT SCRIPT HANDLE ***
    // This handle is derived from your block name (vincentragosta/hero)
    // and the standard wp-scripts output format for editor scripts.
    $script_handle = 'vincentragosta-hero-editor-script'; // <--- Corrected handle
    // **************************************

    error_log('>>> HERO BLOCK LOCALIZATION DEBUG: Using the correct script handle: ' . $script_handle);

    // --- Prepare data for wp_localize_script (for SVG dropdown/preview) ---
    $svg_dir = get_template_directory() . '/assets/images/';
    $svg_options = [['label' => __('Select SVG', 'vincentragosta'), 'value' => '']];
    $svg_content_map = [];

    if (is_dir($svg_dir)) {
        $svg_files = glob($svg_dir . '*.svg');
        if ($svg_files) {
            foreach ($svg_files as $file_path) {
                $filename = basename($file_path);
                $label = ucwords(str_replace(['-', '_', '.svg'], ' ', pathinfo($filename, PATHINFO_FILENAME)));
                $svg_options[] = ['label' => $label, 'value' => $filename];
                // Using the fixed get_theme_svg function
                $svg_content_map[$filename] = get_theme_svg($filename);
            }
        }
    }

    $localized_data = [
        'svgOptions' => $svg_options,
        'svgContent' => $svg_content_map,
    ];
    error_log('>>> HERO BLOCK LOCALIZATION DEBUG: Data prepared.');


    // Check if the target script handle is registered or enqueued before localizing
    $is_registered = wp_script_is( $script_handle, 'registered' );
    $is_enqueued = wp_script_is( $script_handle, 'enqueued' );

    error_log('>>> HERO BLOCK LOCALIZATION DEBUG: Script handle "' . $script_handle . '" is registered: ' . ($is_registered ? 'Yes' : 'No'));
    error_log('>>> HERO BLOCK LOCALIZATION DEBUG: Script handle "' . $script_handle . '" is enqueued: ' . ($is_enqueued ? 'Yes' : 'No'));


    if ( $is_registered || $is_enqueued ) {
        wp_localize_script(
            $script_handle, // Use the correct handle
            'vincentragostaHeroBlockData',
            $localized_data
        );
        error_log('>>> HERO BLOCK LOCALIZATION DEBUG: wp_localize_script called.');
    } else {
        // This branch should ideally NOT be hit if the script is registered via block.json
        error_log('>>> HERO BLOCK LOCALIZATION DEBUG: WARNING - Script handle "' . $script_handle . '" is NOT registered or enqueued. Localization skipped.');
    }

    error_log('>>> HERO BLOCK LOCALIZATION DEBUG: Function finished.');
}
// add_action hook remains correct
add_action( 'enqueue_block_editor_assets', 'vincentragosta_native_block_editor_assets' );

// Note: Your theme includes 'acf-json/group_67ee122690dbb.json' which defines fields for an ACF block named 'acf/hero'.
// The code above registers a *native* Gutenberg block named 'vincentragosta/hero'.
// These are distinct block types. Based on your description, you are working with the 'vincentragosta/hero' native block.
// Ensure that if the ACF plugin is active, there is no unintended conflict or confusion between the two blocks.
// For the current issue (native block inner blocks not saving), the ACF JSON file itself
// is likely not the direct cause if the ACF plugin is disabled and not registering its 'acf/hero' block.

// IMPORTANT: Based on your console logs showing duplicate loading, the issue is likely
// caused by your blocks/hero/build/index.js script being loaded more than once in the editor.
// This functions.php now *correctly* avoids manually enqueueing the script/style when they
// are defined in block.json. If the script is still loading twice, the duplicate
// enqueue/inclusion is happening elsewhere in your theme or environment setup.
// The console.log statements you added to blocks/hero/index.js are crucial for finding this.
// Look for duplicate `add_action( 'enqueue_block_editor_assets', ... )` lines, manual script tags
// in header/footer/twig files, or other code that might include functions.php multiple times
// in the editor context.