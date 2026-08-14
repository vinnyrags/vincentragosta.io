<?php
/**
 * The base configuration for WordPress
 */

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**
 * WordPress database table prefix.
 */
$table_prefix = 'wp_';

// =====================================================================
// Environment-specific settings (loaded from wp-config-env.php if present)
// =====================================================================
// Loaded BEFORE the salt fallbacks below so each environment supplies its own
// secrets. wp-config-env.php is gitignored; salts must never be committed.
$env_settings = __DIR__ . '/wp-config-env.php';
if ( is_readable( $env_settings ) ) {
    require_once $env_settings;
}

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Real secrets live in the gitignored wp-config-env.php, per environment.
 * These placeholders only exist so a fresh checkout boots; they are
 * deliberately non-secret and must never be used by a served environment.
 * Generate real ones via https://api.wordpress.org/secret-key/1.1/salt/.
 */
if ( ! defined( 'AUTH_KEY' ) )         define( 'AUTH_KEY',         'insecure-placeholder-set-in-wp-config-env' );
if ( ! defined( 'SECURE_AUTH_KEY' ) )  define( 'SECURE_AUTH_KEY',  'insecure-placeholder-set-in-wp-config-env' );
if ( ! defined( 'LOGGED_IN_KEY' ) )    define( 'LOGGED_IN_KEY',    'insecure-placeholder-set-in-wp-config-env' );
if ( ! defined( 'NONCE_KEY' ) )        define( 'NONCE_KEY',        'insecure-placeholder-set-in-wp-config-env' );
if ( ! defined( 'AUTH_SALT' ) )        define( 'AUTH_SALT',        'insecure-placeholder-set-in-wp-config-env' );
if ( ! defined( 'SECURE_AUTH_SALT' ) ) define( 'SECURE_AUTH_SALT', 'insecure-placeholder-set-in-wp-config-env' );
if ( ! defined( 'LOGGED_IN_SALT' ) )   define( 'LOGGED_IN_SALT',   'insecure-placeholder-set-in-wp-config-env' );
if ( ! defined( 'NONCE_SALT' ) )       define( 'NONCE_SALT',       'insecure-placeholder-set-in-wp-config-env' );
/**#@-*/

// =====================================================================
// Custom Directory Structure Settings
// =====================================================================
if ( ! defined( 'WP_ENVIRONMENT_TYPE' ) ) {
    define( 'WP_ENVIRONMENT_TYPE', 'development' );
}

if ( ! defined( 'WP_HOME' ) ) {
    define( 'WP_HOME', 'https://vincentragosta.io.ddev.site' );
}

if ( ! defined( 'WP_SITEURL' ) ) {
    define( 'WP_SITEURL', WP_HOME . '/wp' );
}

// The local path to the wp-content directory.
define('WP_CONTENT_DIR', dirname(__FILE__) . '/wp-content');
define('WP_CONTENT_URL', WP_HOME . '/wp-content');

// =====================================================================
// Debugging Settings (defaults for development, overridden per environment)
// =====================================================================
// Default to debug OFF in production. wp-config-env.php (per-environment,
// gitignored) overrides for local DDEV and staging where debug logging is
// useful. Leaving debug enabled in production wrote stack traces to a
// world-readable /wp-content/debug.log that could leak internal details.
if ( ! defined( 'WP_DEBUG' ) ) {
    define( 'WP_DEBUG', false );
}
if ( ! defined( 'WP_DEBUG_LOG' ) ) {
    define( 'WP_DEBUG_LOG', false );
}
if ( ! defined( 'WP_DEBUG_DISPLAY' ) ) {
    define( 'WP_DEBUG_DISPLAY', false );
}
@ini_set( 'display_errors', 0 );

// Lock down WP admin: even with admin credentials, the file editor and
// plugin/theme installer cannot be used to inject code through the dashboard.
// Defense-in-depth — protects against admin-credential compromise.
if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
    define( 'DISALLOW_FILE_EDIT', true );
}
if ( ! defined( 'DISALLOW_FILE_MODS' ) ) {
    define( 'DISALLOW_FILE_MODS', true );
}


/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/wp/' );
}

// Include for settings managed by ddev.
$ddev_settings = __DIR__ . '/wp-config-ddev.php';
if ( ! defined( 'DB_USER' ) && getenv( 'IS_DDEV_PROJECT' ) == 'true' && is_readable( $ddev_settings ) ) {
    require_once( $ddev_settings );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';