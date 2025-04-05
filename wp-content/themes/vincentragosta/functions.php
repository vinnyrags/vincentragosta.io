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
// Add 'blocks' directory for block-specific templates
Timber::$dirname = [ 'templates', 'views', 'blocks' ];

new StarterSite();

// --- NEW: ACF Block Render Callback ---
/**
 * Generic Render Callback for ACF Blocks.
 * Renders the Twig file based on the block's name.
 *
 * @param array    $block      The block settings and attributes.
 * @param string   $content    The block inner content (empty unless using <InnerBlocks>).
 * @param bool     $is_preview True during editor preview.
 * @param int      $post_id    The post ID this block is saved to.
 * @param WP_Block $wp_block   The block instance (since WP 5.5).
 * @param array    $acf_context The ACF context provided block values (since ACF 6.0).
 */
function theme_acf_block_render_callback( $block, $content = '', $is_preview = false, $post_id = 0, $wp_block = null, $acf_context = null ) {
    // Use Timber context as a base
    $context = Timber\Timber::context();

    // Store block values.
    $context['block']      = $block; // Contains block name, ID, alignment, etc.
    $context['post_id']    = $post_id;

    // Store field values.
    // Use $acf_context if available (ACF 6.0+), otherwise fallback to get_fields().
    $context['fields'] = $acf_context['fields'] ?? get_fields();

    // Store whether previewing in the editor.
    $context['is_preview'] = $is_preview;

    // Get the block slug (e.g., 'my-block' from 'acf/my-block')
    $slug = str_replace( 'acf/', '', $block['name'] );

    // Construct the path to the Twig file within the block's folder
    // Example: 'blocks/my-block/my-block.twig'
    $template_path = $slug . '/' . $slug . '.twig';

    // Check if the template file exists before rendering
    $full_template_path = get_template_directory() . '/blocks/' . $template_path;

    if ( file_exists( $full_template_path ) ) {
        Timber\Timber::render( $template_path, $context );
    } else {
        // Optional: Output an error if the template is missing
        if ( current_user_can( 'edit_posts' ) ) {
            echo '<p style="color: red; font-weight: bold;">Error: Block template not found at <code>' . esc_html( 'blocks/' . $template_path ) . '</code></p>';
        }
        // Or render a fallback template or nothing
        // Timber\Timber::render( 'blocks/fallback-block.twig', $context );
    }
}
// --- END NEW ---

/**
 * Populates ACF select field choices with SVG filenames from the theme's assets/images directory.
 *
 * @param array $field The ACF field array.
 * @return array The modified ACF field array.
 */
function populate_svg_choices( $field ) {
    // Reset choices
    $field['choices'] = array();

    // Path to the SVG directory
    $svg_dir = get_template_directory() . '/assets/images/';

    // Check if the directory exists
    if ( is_dir( $svg_dir ) ) {
        // Scan the directory for .svg files
        $svg_files = glob( $svg_dir . '*.svg' );

        if ( $svg_files ) {
            foreach ( $svg_files as $file_path ) {
                $filename = basename( $file_path );
                // Use filename as both value and label (or create a nicer label)
                $label = ucwords( str_replace( ['-', '_', '.svg'], ' ', $filename ) );
                $field['choices'][ $filename ] = $label;
            }
        }
    }

    // Add a default "None" option if needed
    // $field['choices'] = array_merge( array( '' => '- None -' ), $field['choices'] );

    return $field;
}
// Hook the function to the specific ACF field name used in Step 1
add_filter('acf/load_field/name=hero_svg_asset', 'populate_svg_choices');


/**
 * Safely retrieves the content of an SVG file from the theme's assets/images directory.
 *
 * @param string $filename The filename of the SVG (e.g., 'squiggle.svg').
 * @return string SVG content or an empty string if not found or invalid.
 */
function get_theme_svg( $filename ) {
    // Sanitize the filename to prevent directory traversal
    $filename = basename( $filename );

    // Construct the full path
    $svg_path = get_template_directory() . '/assets/images/' . $filename;

    // Check if the file exists and has a .svg extension
    if ( $filename && pathinfo( $svg_path, PATHINFO_EXTENSION ) === 'svg' && file_exists( $svg_path ) ) {
        // Read the file content
        $svg_content = file_get_contents( $svg_path );

        // Basic sanitization (remove script tags) - More robust sanitization might be needed
        // depending on the source of SVGs. Consider libraries like SVG Sanitizer if SVGs
        // could come from untrusted sources.
        $svg_content = preg_replace( '#<script(.*?)>(.*?)</script>#is', '', $svg_content );

        // Remove comments
        $svg_content = preg_replace('//', '', $svg_content);

        // Remove XML prolog if present
        $svg_content = preg_replace('/<\?xml(.|\s)*?\?>/', '', $svg_content);

        // You might want further sanitization here depending on trust level of SVGs

        return $svg_content ?: ''; // Return content or empty string
    }

    return ''; // Return empty string if file not found or invalid
}

// --- Make the SVG function available in Twig ---
// Option A: Add directly if StarterSite::add_to_twig is used
// Inside StarterSite class, add_to_twig method:
// $twig->addFunction(new \Timber\Twig_Function('get_theme_svg', 'get_theme_svg'));

// Option B: Add filter globally (if not using class method or for simplicity)
// Place this outside the class, e.g., after the function definitions above:
add_filter( 'timber/twig', function( $twig ) {
    $twig->addFunction( new Twig\TwigFunction( 'get_theme_svg', 'get_theme_svg' ) );
    return $twig;
});

// Function to render the block on the frontend
function vincentragosta_render_hero_block($attributes, $content, $block) {
    // Note: $content is empty because innerBlocks are not used here.
    // $block contains full block context.

    $title      = $attributes['title'] ?? '';
    $subtitle   = $attributes['subtitle'] ?? '';
    $links      = $attributes['links'] ?? [];
    $svg_asset  = $attributes['svgAsset'] ?? '';
    $align      = $attributes['align'] ?? '';
    // $anchor     = $attributes['anchor'] ?? ''; // Anchor is handled by wrapper automatically

    // Use core function to get wrapper attributes (includes class, style, anchor id etc.)
    $wrapper_attributes = get_block_wrapper_attributes([
        'class' => $align ? 'align' . $align : '', // Add alignment class if present
    ]);

    // Ensure the SVG helper function exists (you already have this)
    if (!function_exists('get_theme_svg')) {
        // Define get_theme_svg here or ensure it's included/required
        function get_theme_svg( $filename ) {
            $filename = basename( $filename );
            $svg_path = get_template_directory() . '/assets/images/' . $filename;
            if ( $filename && pathinfo( $svg_path, PATHINFO_EXTENSION ) === 'svg' && file_exists( $svg_path ) ) {
                $svg_content = file_get_contents( $svg_path );
                // Basic sanitization
                $svg_content = preg_replace( '#<script(.*?)>(.*?)</script>#is', '', $svg_content );
                $svg_content = preg_replace('//', '', $svg_content);
                $svg_content = preg_replace('/<\?xml(.|\s)*?\?>/', '', $svg_content);
                return $svg_content ?: '';
            }
            return '';
        }
    }

    ob_start(); // Start output buffering
    ?>
    <div <?php echo $wrapper_attributes; ?>>
        <div class="hero-block__content">
            <?php if ( ! empty( $title ) ) : ?>
                {/* Use wp_kses_post or similar if title allows HTML from RichText */}
                <h1 class="hero-block__title"><?php echo wp_kses_post( $title ); ?></h1>
            <?php endif; ?>

            <?php if ( ! empty( $subtitle ) ) : ?>
                {/* Use wp_kses_post or similar if subtitle allows HTML from RichText */}
                <p class="hero-block__subtitle"><?php echo wp_kses_post( $subtitle ); ?></p>
            <?php endif; ?>

            <?php if ( ! empty( $links ) ) : ?>
                <div class="hero-block__links">
                    <?php foreach ( $links as $link ) : ?>
                        <?php
                        // Ensure keys exist before accessing
                        $url = $link['url'] ?? '#';
                        $text = $link['text'] ?? '';
                        $target = ! empty( $link['opensInNewTab'] ) ? '_blank' : '_self';
                        $rel = ! empty( $link['opensInNewTab'] ) ? 'noopener noreferrer' : '';
                        ?>
                        <?php if ( ! empty( $url ) && ! empty( $text ) ) : ?>
                            <a href="<?php echo esc_url( $url ); ?>"
                               class="wp-block-button__link hero-block__link"
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
                // Render the SVG content safely
                echo get_theme_svg( $svg_asset );
            }
            ?>
        </div>
    </div>
    <?php
    return ob_get_clean(); // Return buffered content
}

// Function to register the block type using the render_callback
function vincentragosta_register_native_blocks() {
    register_block_type( get_template_directory() . '/blocks/hero', array(
        'render_callback' => 'vincentragosta_render_hero_block',
    ) );
    // Register other native blocks here...
}
add_action( 'init', 'vincentragosta_register_native_blocks' );


// Function to enqueue block assets and pass SVG data
//function vincentragosta_native_block_editor_assets() {
//    $block_path = '/src/blocks/hero/build/'; // Path relative to theme root
//    $asset_file = include( get_template_directory() . $block_path . 'hero.asset.php'); // Generated by @wordpress/scripts
//
//    // Enqueue the editor script
//    wp_enqueue_script(
//        'vincentragosta-hero-block-editor', // Handle
//        get_template_directory_uri() . $block_path . 'hero.js', // File URL
//        $asset_file['dependencies'],
//        $asset_file['version'],
//        true // Load in footer
//    );
//
//    // Enqueue the editor style
//    wp_enqueue_style(
//        'vincentragosta-hero-block-editor-style',
//        get_template_directory_uri() . $block_path . 'hero.css',
//        array('wp-edit-blocks'),
//        $asset_file['version']
//    );
//
//    // Pass SVG options to the script
//    $svg_dir = get_template_directory() . '/assets/images/';
//    $svg_choices = [['label' => __('Select SVG', 'vincentragosta'), 'value' => '']];
//    if (is_dir($svg_dir)) {
//        $svg_files = glob($svg_dir . '*.svg');
//        if ($svg_files) {
//            foreach ($svg_files as $file_path) {
//                $filename = basename($file_path);
//                $label = ucwords(str_replace(['-', '_', '.svg'], ' ', $filename));
//                $svg_choices[] = ['label' => $label, 'value' => $filename];
//            }
//        }
//    }
//    wp_add_inline_script(
//        'vincentragosta-hero-block-editor',
//        'const myThemeNativeHeroSvgOptions = ' . wp_json_encode( $svg_choices ) . ';',
//        'before'
//    );
//}
//add_action( 'enqueue_block_editor_assets', 'vincentragosta_native_block_editor_assets' );

// Enqueue frontend styles (if not handled by block.json 'style')
function vincentragosta_native_block_frontend_assets() {
    // Only load if the block exists? Optional performance tweak.
    // if ( ! is_admin() && has_block('vincentragosta/hero') ) {
    $block_path = '/src/blocks/hero/build/';
    $style_asset_file = include( get_template_directory() . $block_path . 'style-hero.asset.php'); // Separate asset file for style-only build
    wp_enqueue_style(
        'vincentragosta-hero-block-style',
        get_template_directory_uri() . $block_path . 'style-hero.css',
        array(), // Dependencies
        $style_asset_file['version']
    );
    // }
}
add_action( 'wp_enqueue_scripts', 'vincentragosta_native_block_frontend_assets' );
