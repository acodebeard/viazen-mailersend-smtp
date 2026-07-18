<?php

use PHPMailer\PHPMailer\PHPMailer;
use Viazen\MailerSendSmtp\Plugin;

function viazen_wp_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
require_once ABSPATH . WPINC . '/class-wp-phpmailer.php';

$settings_option   = 'viazen_mailersend_smtp_settings';
$diagnostic_option = 'viazen_mailersend_smtp_diagnostic';
$fake_username     = 'integration-user';
$fake_password     = 'integration-secret';

Plugin::activate();
update_option(
	$settings_option,
	array(
		'smtp_username' => $fake_username,
		'smtp_password' => $fake_password,
		'from_email'    => 'sender@example.com',
		'from_name'     => 'Viazen Integration',
	),
	false
);

viazen_wp_assert( false === array_key_exists( $settings_option, wp_load_alloptions() ), 'Settings option is autoloaded.' );
viazen_wp_assert( false === array_key_exists( $diagnostic_option, wp_load_alloptions() ), 'Diagnostic option is autoloaded.' );
viazen_wp_assert( PHP_INT_MAX === has_action( 'phpmailer_init', array( Plugin::class, 'configure_phpmailer' ) ), 'phpmailer_init hook is missing or has the wrong priority.' );
viazen_wp_assert( PHP_INT_MAX === has_filter( 'wp_mail_from', array( Plugin::class, 'filter_from_email' ) ), 'wp_mail_from hook is missing or has the wrong priority.' );
viazen_wp_assert( PHP_INT_MAX === has_filter( 'wp_mail_from_name', array( Plugin::class, 'filter_from_name' ) ), 'wp_mail_from_name hook is missing or has the wrong priority.' );
viazen_wp_assert( false !== has_action( 'wp_mail_failed', array( Plugin::class, 'record_failure' ) ), 'wp_mail_failed hook is missing.' );
viazen_wp_assert( false !== has_action( 'wp_mail_succeeded', array( Plugin::class, 'record_success' ) ), 'wp_mail_succeeded hook is missing.' );

$mailer = new WP_PHPMailer( true );
$mailer->addReplyTo( 'visitor@example.com', 'Visitor' );
$mailer->addCC( 'cc@example.com' );
$mailer->addBCC( 'bcc@example.com' );
$mailer->isHTML( true );
$mailer->Body = '<p>Integration body</p>';
$mailer->addStringAttachment( 'attachment content', 'integration.txt' );

do_action( 'phpmailer_init', $mailer );

viazen_wp_assert( 'smtp' === $mailer->Mailer, 'PHPMailer transport is not SMTP.' );
viazen_wp_assert( 'smtp.mailersend.net' === $mailer->Host, 'SMTP host mismatch.' );
viazen_wp_assert( 587 === $mailer->Port, 'SMTP port mismatch.' );
viazen_wp_assert( PHPMailer::ENCRYPTION_STARTTLS === $mailer->SMTPSecure, 'STARTTLS mismatch.' );
viazen_wp_assert( true === $mailer->SMTPAuth && true === $mailer->SMTPAutoTLS, 'SMTP authentication or AutoTLS is disabled.' );
viazen_wp_assert( $fake_username === $mailer->Username && $fake_password === $mailer->Password, 'SMTP credentials were not applied.' );
viazen_wp_assert( 20 === $mailer->Timeout && 0 === $mailer->SMTPDebug, 'Timeout or debug setting mismatch.' );
viazen_wp_assert( 1 === count( $mailer->getReplyToAddresses() ), 'Reply-To was changed.' );
viazen_wp_assert( 1 === count( $mailer->getCcAddresses() ), 'CC was changed.' );
viazen_wp_assert( 1 === count( $mailer->getBccAddresses() ), 'BCC was changed.' );
viazen_wp_assert( PHPMailer::CONTENT_TYPE_TEXT_HTML === $mailer->ContentType, 'HTML content type was changed.' );
viazen_wp_assert( '<p>Integration body</p>' === $mailer->Body, 'Message body was changed.' );
viazen_wp_assert( 1 === count( $mailer->getAttachments() ), 'Attachment was changed.' );

viazen_wp_assert( 'sender@example.com' === apply_filters( 'wp_mail_from', 'visitor@example.com' ), 'From email was not overridden.' );
viazen_wp_assert( 'Viazen Integration' === apply_filters( 'wp_mail_from_name', 'Visitor' ), 'From name was not overridden.' );

$preserved = Plugin::sanitize_settings(
	array(
		'smtp_username' => '',
		'smtp_password' => '',
		'from_email'    => 'sender@example.com',
		'from_name'     => 'Viazen Integration',
	)
);
viazen_wp_assert( $fake_username === $preserved['smtp_username'], 'Blank username did not preserve the saved value.' );
viazen_wp_assert( $fake_password === $preserved['smtp_password'], 'Blank password did not preserve the saved value.' );

$malformed = Plugin::sanitize_settings(
	array(
		'smtp_username' => array( 'unexpected' ),
		'smtp_password' => array( 'unexpected' ),
		'from_email'    => array( 'unexpected' ),
		'from_name'     => array( 'unexpected' ),
	)
);
viazen_wp_assert( $fake_username === $malformed['smtp_username'], 'Malformed username input replaced the saved value.' );
viazen_wp_assert( $fake_password === $malformed['smtp_password'], 'Malformed password input replaced the saved value.' );
viazen_wp_assert( 'sender@example.com' === $malformed['from_email'], 'Malformed From email input replaced the saved value.' );
viazen_wp_assert( 'Viazen Integration' === $malformed['from_name'], 'Malformed From name input replaced the saved value.' );

ob_start();
Plugin::render_username_field();
$username_html = ob_get_clean();
ob_start();
Plugin::render_password_field();
$password_html = ob_get_clean();
viazen_wp_assert( str_contains( $username_html, 'value="' . $fake_username . '"' ), 'Saved username was not shown in admin HTML.' );
viazen_wp_assert( false === str_contains( $password_html, $fake_password ), 'Saved password entered admin HTML.' );
viazen_wp_assert( str_contains( $password_html, 'value="000000"' ), 'Saved password status did not render a six-character mask.' );
viazen_wp_assert( str_contains( $password_html, '<details>' ), 'Saved password did not render a native change control.' );
viazen_wp_assert( str_contains( $password_html, 'Change password' ), 'Saved password change control is missing its label.' );

do_action(
	'wp_mail_failed',
	new WP_Error(
		'wp_mail_failed',
		'Authentication failed username=' . $fake_username . ' password=' . $fake_password,
		array(
			'to'          => array( 'recipient@example.com' ),
			'subject'     => 'Integration subject',
			'message'     => 'DO NOT STORE THIS BODY',
			'attachments' => array( '/private/attachment.txt' ),
		)
	)
);

$diagnostic = get_option( $diagnostic_option );
viazen_wp_assert( array( 'status', 'category', 'timestamp', 'recipient', 'subject', 'error' ) === array_keys( $diagnostic ), 'Diagnostic contains unexpected fields.' );
viazen_wp_assert( 'authentication-failure' === $diagnostic['category'], 'Authentication failure was classified incorrectly.' );
viazen_wp_assert( false === str_contains( $diagnostic['error'], $fake_username ), 'Diagnostic exposed the username.' );
viazen_wp_assert( false === str_contains( $diagnostic['error'], $fake_password ), 'Diagnostic exposed the password.' );
viazen_wp_assert( false === str_contains( serialize( $diagnostic ), 'DO NOT STORE THIS BODY' ), 'Diagnostic stored a body.' );
viazen_wp_assert( false === str_contains( serialize( $diagnostic ), '/private/attachment.txt' ), 'Diagnostic stored an attachment.' );

$classification_cases = array(
	'connection-failure'  => 'SMTP connect() failed: Connection refused',
	'invalid-from'        => 'Invalid address: (From): invalid',
	'transport-rejection' => 'SMTP Error: data not accepted. 550 rejected',
	'general-failure'     => 'Unknown wp_mail failure',
);

foreach ( $classification_cases as $expected_category => $message ) {
	do_action(
		'wp_mail_failed',
		new WP_Error(
			'wp_mail_failed',
			$message,
			array(
				'to'      => array( 'recipient@example.com' ),
				'subject' => 'Classification test',
			)
		)
	);
	$classified = get_option( $diagnostic_option );
	viazen_wp_assert( $expected_category === $classified['category'], $expected_category . ' was classified incorrectly.' );
}

do_action(
	'wp_mail_succeeded',
	array(
		'to'      => array( 'recipient@example.com' ),
		'subject' => 'Successful handoff',
		'message' => 'DO NOT STORE THIS SUCCESS BODY',
	)
);
$successful = get_option( $diagnostic_option );
viazen_wp_assert( 'success' === $successful['status'], 'Successful handoff was not stored as success.' );
viazen_wp_assert( 'transport-accepted' === $successful['category'], 'Successful handoff category is incorrect.' );
viazen_wp_assert( '' === $successful['error'], 'Successful handoff stored an error.' );
viazen_wp_assert( false === str_contains( serialize( $successful ), 'DO NOT STORE THIS SUCCESS BODY' ), 'Diagnostic stored a successful message body.' );

$invalid_from = Plugin::sanitize_settings(
	array(
		'smtp_username' => '',
		'smtp_password' => '',
		'from_email'    => 'not-an-email',
		'from_name'     => 'Viazen Integration',
	)
);
viazen_wp_assert( 'sender@example.com' === $invalid_from['from_email'], 'Invalid From email replaced the saved valid address.' );

$active_plugins = get_option( 'active_plugins', array() );
update_option(
	'active_plugins',
	array_values(
		array_unique(
			array_merge(
				$active_plugins,
				array( 'mailersend-official-smtp-integration/mailersend-wordpress.php' )
			)
		)
	),
	false
);
ob_start();
Plugin::render_conflict_notice();
$conflict_html = ob_get_clean();
update_option( 'active_plugins', $active_plugins, false );
viazen_wp_assert( str_contains( $conflict_html, 'MailerSend' ), 'Official MailerSend conflict warning was not rendered.' );
viazen_wp_assert( str_contains( $conflict_html, 'unpredictable results' ), 'Conflict warning does not explain the risk.' );
viazen_wp_assert( false === str_contains( $conflict_html, $fake_username ), 'Conflict warning exposed the username.' );
viazen_wp_assert( false === str_contains( $conflict_html, $fake_password ), 'Conflict warning exposed the password.' );

Plugin::register_settings();
$registered = get_registered_settings();
viazen_wp_assert( isset( $registered[ $settings_option ] ), 'Settings API option is not registered.' );
viazen_wp_assert( false === $registered[ $settings_option ]['show_in_rest'], 'Credentials are exposed through the REST settings schema.' );

delete_option( $settings_option );
delete_option( $diagnostic_option );
Plugin::activate();
delete_option( $diagnostic_option );
WP_CLI::success( 'Viazen MailerSend SMTP WordPress integration checks passed.' );
