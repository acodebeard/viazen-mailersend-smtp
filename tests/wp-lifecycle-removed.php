<?php

if (
	false !== get_option( 'viazen_mailersend_smtp_settings', false )
	|| false !== get_option( 'viazen_mailersend_smtp_diagnostic', false )
	|| false !== get_option( 'viazen_mailersend_smtp_credential_status', false )
	|| ! empty( get_users( array( 'meta_key' => 'viazen_mailersend_smtp_donation_dismissed', 'fields' => 'ids' ) ) )
) {
	throw new RuntimeException( 'Uninstall did not remove all plugin options, credential status, and user metadata.' );
}

WP_CLI::success( 'Uninstall removed settings, credentials, credential status, diagnostics, and user preferences.' );
