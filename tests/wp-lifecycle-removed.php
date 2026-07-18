<?php

if (
	false !== get_option( 'viazen_mailersend_smtp_settings', false )
	|| false !== get_option( 'viazen_mailersend_smtp_diagnostic', false )
	|| ! empty( get_users( array( 'meta_key' => 'viazen_mailersend_smtp_donation_dismissed', 'fields' => 'ids' ) ) )
) {
	throw new RuntimeException( 'Uninstall did not remove all plugin options and user metadata.' );
}

WP_CLI::success( 'Uninstall removed settings, credentials, diagnostics, and user preferences.' );
