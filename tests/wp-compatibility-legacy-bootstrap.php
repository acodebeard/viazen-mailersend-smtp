<?php
/**
 * Process-only legacy-mode fixture for an explicitly approved DDEV WP-CLI run.
 *
 * Load with WP-CLI --require before WordPress bootstraps. This does not change
 * the database, wp-config.php, DDEV marker, environment type, or MU safety code.
 */

if ( 'cli' !== PHP_SAPI || ! defined( 'WP_CLI' ) || true !== WP_CLI || 'true' !== getenv( 'IS_DDEV_PROJECT' ) ) {
	throw new RuntimeException( 'The legacy compatibility fixture requires DDEV WP-CLI.' );
}
if ( function_exists( 'wp_get_environment_type' ) || defined( 'VIAZEN_MAILERSEND_SMTP_MANAGED' ) ) {
	throw new RuntimeException( 'Load the legacy fixture before WordPress and mail configuration.' );
}

define( 'VIAZEN_MAILERSEND_SMTP_MANAGED', false );
