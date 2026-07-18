<?php

if (
	false !== get_option( 'viazen_mailersend_smtp_settings', false )
	|| false !== get_option( 'viazen_mailersend_smtp_diagnostic', false )
) {
	throw new RuntimeException( 'Uninstall did not remove all plugin options.' );
}

WP_CLI::success( 'Uninstall removed settings, credentials, and diagnostics.' );
