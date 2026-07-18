<?php
/**
 * Uninstall Viazen MailerSend SMTP.
 *
 * @package ViazenMailerSendSmtp
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'viazen_mailersend_smtp_settings' );
delete_option( 'viazen_mailersend_smtp_diagnostic' );
