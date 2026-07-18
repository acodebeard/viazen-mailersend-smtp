<?php

delete_option( 'viazen_mailersend_smtp_settings' );
delete_option( 'viazen_mailersend_smtp_diagnostic' );
\Viazen\MailerSendSmtp\Plugin::activate();
delete_option( 'viazen_mailersend_smtp_diagnostic' );

$settings = get_option( 'viazen_mailersend_smtp_settings', array() );

if ( '' !== ( $settings['smtp_username'] ?? '' ) || '' !== ( $settings['smtp_password'] ?? '' ) ) {
	throw new RuntimeException( 'Fresh reinstall retained test credentials.' );
}

WP_CLI::success( 'Sandbox plugin state has no SMTP credentials or diagnostic result.' );
