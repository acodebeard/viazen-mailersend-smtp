<?php

delete_option( 'viazen_mailersend_smtp_settings' );
delete_option( 'viazen_mailersend_smtp_diagnostic' );
delete_option( 'viazen_mailersend_smtp_credential_status' );
\Viazen\MailerSendSmtp\Plugin::activate();
delete_option( 'viazen_mailersend_smtp_diagnostic' );
delete_option( 'viazen_mailersend_smtp_credential_status' );

$settings = get_option( 'viazen_mailersend_smtp_settings', array() );

if (
	'' !== ( $settings['smtp_username'] ?? '' )
	|| '' !== ( $settings['smtp_password'] ?? '' )
	|| false !== get_option( 'viazen_mailersend_smtp_credential_status', false )
) {
	throw new RuntimeException( 'Fresh reinstall retained test credentials or credential status.' );
}

WP_CLI::success( 'Sandbox plugin state has no SMTP credentials, credential status, or diagnostic result.' );
