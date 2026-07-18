<?php

$settings = get_option( 'viazen_mailersend_smtp_settings', array() );
$diagnostic = get_option( 'viazen_mailersend_smtp_diagnostic', array() );

if (
	'update-user' !== ( $settings['smtp_username'] ?? '' )
	|| 'update-password' !== ( $settings['smtp_password'] ?? '' )
	|| 'update-sender@example.com' !== ( $settings['from_email'] ?? '' )
	|| 'Update Test' !== ( $settings['from_name'] ?? '' )
	|| 'valid' !== get_option( 'viazen_mailersend_smtp_credential_status', '' )
	|| 'Update preservation test' !== ( $diagnostic['subject'] ?? '' )
) {
	throw new RuntimeException( 'Normal ZIP replacement removed or changed saved plugin data.' );
}

WP_CLI::success( 'Normal ZIP replacement preserved credentials and diagnostics.' );
