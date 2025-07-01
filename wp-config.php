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
// Custom Directory Structure Settings
// =====================================================================
define('WP_ENVIRONMENT_TYPE', 'development');

// The public-facing URL of your site.
define('WP_HOME', 'https://vincentragosta.io.ddev.site');

// The URL where the WordPress core files are located.
define('WP_SITEURL', 'https://vincentragosta.io.ddev.site/wp');

// The local path to the wp-content directory.
define('WP_CONTENT_DIR', dirname(__FILE__) . '/wp-content');
define('WP_CONTENT_URL', WP_HOME . '/wp-content');

// =====================================================================
// Debugging Settings
// =====================================================================
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
@ini_set( 'display_errors', 0 );


/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
// MODIFIED: This now points to the /wp/ subdirectory.
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/wp/' );
}

// Include for settings managed by ddev.
// PRESERVED: This block is essential for DDEV to connect to the database.
$ddev_settings = __DIR__ . '/wp-config-ddev.php';
if ( ! defined( 'DB_USER' ) && getenv( 'IS_DDEV_PROJECT' ) == 'true' && is_readable( $ddev_settings ) ) {
    require_once( $ddev_settings );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';