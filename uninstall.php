<?php
/**
 * Uninstall SMTP Connector for MailerSend.
 *
 * @package ViazenMailerSendSmtp
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'viazen_mailersend_smtp_settings' );
delete_option( 'viazen_mailersend_smtp_diagnostic' );
delete_metadata( 'user', 0, 'viazen_mailersend_smtp_donation_dismissed', '', true );
