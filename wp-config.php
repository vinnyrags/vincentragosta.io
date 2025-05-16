<?php
/**
 * #ddev-generated: Automatically generated WordPress settings file.
 * ddev manages this file and may delete or overwrite the file unless this comment is removed.
 * It is recommended that you leave this file alone.
 *
 * @package ddevapp
 */

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/** Authentication Unique Keys and Salts. */
define( 'AUTH_KEY', 'tUsyNAYcnAXdfTPSAzMgEVjRzOoUJklvAJxqAUYWrUnfrYUjxYRlqJASRIurNTGI' );
define( 'SECURE_AUTH_KEY', 'pKfzCBAKQFqLXrXNSGXlOHbQNxrAqgdONVwazylrzIiJDvrcvbpUVPTuMmiukciS' );
define( 'LOGGED_IN_KEY', 'icjUeTyestPmZPkgkhTvVQxBsreEQbGwdtZzCmwSIehVcaBhGzvThCORTCXVMuOQ' );
define( 'NONCE_KEY', 'lEBlIpwgmfgssWrHtiUPKTfAUqNuuoRVPdLVCTsvKbywCyzutwCdOOTPSsFgnqLS' );
define( 'AUTH_SALT', 'WvXayMAzKBXpcrYCVkCjPCjXdCECcqfAYRrVHKIBIWVRXznZVcfAKxPtUGYVEXKI' );
define( 'SECURE_AUTH_SALT', 'fvwJIaxqWHwilFymLzaKLjeUyOeVZtyfGRMHLKOKIyNAcJPeYQrFHkezRNnjLOkH' );
define( 'LOGGED_IN_SALT', 'LgCdkqiEhLTBiruriRdSgnDlzDKZJQKEeuoAbLWRczEymCsWUQnIpStbOYahDjbF' );
define( 'NONCE_SALT', 'eAfjwChTCqqDWmBrbnerfVsIjGQELtXKgYHUWDYBmXEyJgZqVAmxSpvEZNOxpjCH' );

/* Add any custom values between this line and the "stop editing" line. */

define( 'WP_DEBUG', true ); // Enable debug mode
define( 'WP_DEBUG_LOG', true ); // Enable debug logging to debug.log
define( 'WP_DEBUG_DISPLAY', false ); // Disable displaying errors on the page (recommended for DDEV/development)
@ini_set( 'display_errors', 0 ); // Ensure display_errors is off at the PHP level

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
defined( 'ABSPATH' ) || define( 'ABSPATH', dirname( __FILE__ ) . '/' );

// Include for settings managed by ddev.
$ddev_settings = __DIR__ . '/wp-config-ddev.php';
if ( ! defined( 'DB_USER' ) && getenv( 'IS_DDEV_PROJECT' ) == 'true' && is_readable( $ddev_settings ) ) {
	require_once( $ddev_settings );
}

/** Include wp-settings.php */
if ( file_exists( ABSPATH . '/wp-settings.php' ) ) {
	require_once ABSPATH . '/wp-settings.php';
}
