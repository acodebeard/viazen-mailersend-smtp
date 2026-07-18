<?php

$settings = get_option( 'viazen_mailersend_smtp_settings', array() );
$admin_ids = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ids',
	)
);

if (
	'lifecycle-user' !== ( $settings['smtp_username'] ?? '' )
	|| 'lifecycle-password' !== ( $settings['smtp_password'] ?? '' )
	|| false === get_option( 'viazen_mailersend_smtp_diagnostic', false )
	|| empty( $admin_ids )
	|| '1' !== get_user_meta( (int) $admin_ids[0], 'viazen_mailersend_smtp_donation_dismissed', true )
) {
	throw new RuntimeException( 'Deactivation removed or changed plugin data or user preferences.' );
}

WP_CLI::success( 'Deactivation preserved settings, credentials, diagnostics, and user preferences.' );
