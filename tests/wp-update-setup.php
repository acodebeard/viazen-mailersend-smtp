<?php

update_option(
	'viazen_mailersend_smtp_settings',
	array(
		'smtp_username' => 'update-user',
		'smtp_password' => 'update-password',
		'from_email'    => 'update-sender@example.com',
		'from_name'     => 'Update Test',
	),
	false
);

update_option(
	'viazen_mailersend_smtp_diagnostic',
	array(
		'status'    => 'success',
		'category'  => 'transport-accepted',
		'timestamp' => time(),
		'recipient' => 'update-recipient@example.com',
		'subject'   => 'Update preservation test',
		'error'     => '',
	),
	false
);

update_option( 'viazen_mailersend_smtp_credential_status', 'valid', false );

WP_CLI::success( 'Pre-update credentials and diagnostics created.' );
