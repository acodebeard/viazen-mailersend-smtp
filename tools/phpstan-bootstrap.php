<?php
/**
 * Analysis-only constants normally supplied by WordPress.
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! defined( 'WPINC' ) ) {
	define( 'WPINC', 'wp-includes' );
}

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	define( 'WP_UNINSTALL_PLUGIN', true );
}
