<?php

update_option(
	'viazen_mailersend_smtp_settings',
	array(
		'smtp_username' => 'lifecycle-user',
		'smtp_password' => 'lifecycle-password',
		'from_email'    => 'sender@example.com',
		'from_name'     => 'Lifecycle Test',
	),
	false
);

update_option(
	'viazen_mailersend_smtp_diagnostic',
	array(
		'status'    => 'failure',
		'category'  => 'general-failure',
		'timestamp' => time(),
		'recipient' => 'recipient@example.com',
		'subject'   => 'Lifecycle test',
		'error'     => 'Lifecycle test error',
	),
	false
);

WP_CLI::success( 'Lifecycle fixtures created.' );
