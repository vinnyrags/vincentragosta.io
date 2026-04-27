<?php
/**
 * The base configuration for WordPress
 */

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication Unique Keys and Salts.
 * You already have these, so you can leave them as they are.
 */
define( 'AUTH_KEY', 'tUsyNAYcnAXdfTPSAzMgEVjRzOoUJklvAJxqAUYWrUnfrYUjxYRlqJASRIurNTGI' );
define( 'SECURE_AUTH_KEY', 'pKfzCBAKQFqLXrXNSGXlOHbQNxrAqgdONVwazylrzIiJDvrcvbpUVPTuMmiukciS' );
define( 'LOGGED_IN_KEY', 'icjUeTyestPmZPkgkhTvVQxBsreEQbGwdtZzCmwSIehVcaBhGzvThCORTCXVMuOQ' );
define( 'NONCE_KEY', 'lEBlIpwgmfgssWrHtiUPKTfAUqNuuoRVPdLVCTsvKbywCyzutwCdOOTPSsFgnqLS' );
define( 'AUTH_SALT', 'WvXayMAzKBXpcrYCVkCjPCjXdCECcqfAYRrVHKIBIWVRXznZVcfAKxPtUGYVEXKI' );
define( 'SECURE_AUTH_SALT', 'fvwJIaxqWHwilFymLzaKLjeUyOeVZtyfGRMHLKOKIyNAcJPeYQrFHkezRNnjLOkH' );
define( 'LOGGED_IN_SALT', 'LgCdkqiEhLTBiruriRdSgnDlzDKZJQKEeuoAbLWRczEymCsWUQnIpStbOYahDjbF' );
define( 'NONCE_SALT', 'eAfjwChTCqqDWmBrbnerfVsIjGQELtXKgYHUWDYBmXEyJgZqVAmxSpvEZNOxpjCH' );
/**#@-*/

/**
 * WordPress database table prefix.
 */
$table_prefix = 'wp_';

// =====================================================================
// Environment-specific settings (loaded from wp-config-env.php if present)
// =====================================================================
$env_settings = __DIR__ . '/wp-config-env.php';
if ( is_readable( $env_settings ) ) {
    require_once $env_settings;
}

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