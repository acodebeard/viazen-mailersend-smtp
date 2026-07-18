<?php

$settings = get_option( 'viazen_mailersend_smtp_settings', array() );

if (
	'lifecycle-user' !== ( $settings['smtp_username'] ?? '' )
	|| 'lifecycle-password' !== ( $settings['smtp_password'] ?? '' )
	|| false === get_option( 'viazen_mailersend_smtp_diagnostic', false )
) {
	throw new RuntimeException( 'Deactivation removed or changed plugin data.' );
}

WP_CLI::success( 'Deactivation preserved settings, credentials, and diagnostics.' );
