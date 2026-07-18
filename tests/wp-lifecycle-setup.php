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

$admin_ids = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ids',
	)
);
if ( empty( $admin_ids ) ) {
	throw new RuntimeException( 'No administrator was available for the donation-dismissal fixture.' );
}
update_user_meta( (int) $admin_ids[0], 'viazen_mailersend_smtp_donation_dismissed', '1' );

WP_CLI::success( 'Lifecycle fixtures created.' );
