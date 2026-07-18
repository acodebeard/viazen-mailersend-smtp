<?php

namespace PHPMailer\PHPMailer {
	class PHPMailer {
		public const ENCRYPTION_STARTTLS = 'tls';
		public string $Mailer = 'mail';
		public string $Host = '';
		public int $Port = 0;
		public string $SMTPSecure = '';
		public bool $SMTPAuth = false;
		public bool $SMTPAutoTLS = false;
		public string $Username = '';
		public string $Password = '';
		public int $Timeout = 0;
		public int $SMTPDebug = 0;

		public function isSMTP(): void {
			$this->Mailer = 'smtp';
		}
	}
}

namespace {
	define( 'ABSPATH', __DIR__ . '/' );

	$GLOBALS['viazen_test_options'] = array(
		'admin_email' => 'admin@example.test',
		'viazen_mailersend_smtp_settings' => array(
			'smtp_username' => 'saved-user',
			'smtp_password' => 'saved-password',
			'from_email' => 'sender@example.test',
			'from_name' => 'Viazen Sender',
		),
	);

	function add_action() {}
	function add_filter() {}
	function register_activation_hook() {}
	function register_uninstall_hook() {}
	function get_bloginfo() { return 'Viazen Test'; }
	function get_option( $name, $default = false ) { return $GLOBALS['viazen_test_options'][ $name ] ?? $default; }
	function update_option( $name, $value ) { $GLOBALS['viazen_test_options'][ $name ] = $value; return true; }
	function sanitize_email( $value ) { return filter_var( $value, FILTER_SANITIZE_EMAIL ); }
	function is_email( $value ) { return false !== filter_var( $value, FILTER_VALIDATE_EMAIL ); }
	function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
	function wp_unslash( $value ) { return $value; }
	function add_settings_error() {}
	function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
	function esc_html__( $value ) { return $value; }

	class WP_Error {
		private string $message;
		private $data;

		public function __construct( string $message, $data ) {
			$this->message = $message;
			$this->data = $data;
		}

		public function get_error_message(): string { return $this->message; }
		public function get_error_data() { return $this->data; }
	}

	function viazen_assert( bool $condition, string $message ): void {
		if ( ! $condition ) {
			throw new RuntimeException( $message );
		}
	}

	require dirname( __DIR__ ) . '/viazen-mailersend-smtp.php';

	$class = 'Viazen\\MailerSendSmtp\\Plugin';
	$mailer = new \PHPMailer\PHPMailer\PHPMailer();
	$class::configure_phpmailer( $mailer );

	viazen_assert( 'smtp' === $mailer->Mailer, 'SMTP transport was not selected.' );
	viazen_assert( 'smtp.mailersend.net' === $mailer->Host, 'SMTP host mismatch.' );
	viazen_assert( 587 === $mailer->Port, 'SMTP port mismatch.' );
	viazen_assert( 'tls' === $mailer->SMTPSecure, 'STARTTLS mismatch.' );
	viazen_assert( true === $mailer->SMTPAuth && true === $mailer->SMTPAutoTLS, 'SMTP authentication settings mismatch.' );
	viazen_assert( 20 === $mailer->Timeout && 0 === $mailer->SMTPDebug, 'Timeout or debug setting mismatch.' );
	viazen_assert( 'sender@example.test' === $class::filter_from_email( 'other@example.test' ), 'From email was not overridden.' );
	viazen_assert( 'Viazen Sender' === $class::filter_from_name( 'Other Sender' ), 'From name was not overridden.' );

	ob_start();
	$class::render_username_field();
	$class::render_password_field();
	$credential_html = ob_get_clean();
	viazen_assert( false === str_contains( $credential_html, 'saved-user' ), 'Saved username entered HTML.' );
	viazen_assert( false === str_contains( $credential_html, 'saved-password' ), 'Saved password entered HTML.' );
	viazen_assert( false === str_contains( $credential_html, 'value=' ), 'Credential field rendered a value attribute.' );

	$preserved = $class::sanitize_settings(
		array(
			'smtp_username' => '',
			'smtp_password' => '',
			'from_email' => 'sender@example.test',
			'from_name' => 'Viazen Sender',
		)
	);
	viazen_assert( 'saved-user' === $preserved['smtp_username'], 'Blank username did not preserve the saved value.' );
	viazen_assert( 'saved-password' === $preserved['smtp_password'], 'Blank password did not preserve the saved value.' );

	$malformed = $class::sanitize_settings(
		array(
			'smtp_username' => array( 'unexpected' ),
			'smtp_password' => array( 'unexpected' ),
			'from_email' => array( 'unexpected' ),
			'from_name' => array( 'unexpected' ),
		)
	);
	viazen_assert( 'saved-user' === $malformed['smtp_username'], 'Malformed username input replaced the saved value.' );
	viazen_assert( 'saved-password' === $malformed['smtp_password'], 'Malformed password input replaced the saved value.' );
	viazen_assert( 'sender@example.test' === $malformed['from_email'], 'Malformed From email input replaced the saved value.' );
	viazen_assert( 'Viazen Sender' === $malformed['from_name'], 'Malformed From name input replaced the saved value.' );

	$saved_settings = $GLOBALS['viazen_test_options']['viazen_mailersend_smtp_settings'];
	$GLOBALS['viazen_test_options']['viazen_mailersend_smtp_settings'] = array(
		'smtp_username' => array( 'unexpected' ),
		'smtp_password' => array( 'unexpected' ),
		'from_email' => array( 'unexpected' ),
		'from_name' => array( 'unexpected' ),
	);
	$normalized_mailer = new \PHPMailer\PHPMailer\PHPMailer();
	$class::configure_phpmailer( $normalized_mailer );
	viazen_assert( '' === $normalized_mailer->Username, 'Malformed stored username was not rejected.' );
	viazen_assert( '' === $normalized_mailer->Password, 'Malformed stored password was not rejected.' );
	viazen_assert( 'admin@example.test' === $class::filter_from_email( 'other@example.test' ), 'Malformed stored From email did not use the safe default.' );
	viazen_assert( 'Viazen Test' === $class::filter_from_name( 'Other Sender' ), 'Malformed stored From name did not use the safe default.' );
	$GLOBALS['viazen_test_options']['viazen_mailersend_smtp_settings'] = $saved_settings;

	$error = new WP_Error(
		'Authentication failed for username=saved-user password=saved-password',
		array(
			'to' => array( 'recipient@example.test' ),
			'subject' => 'Allowed subject',
			'message' => 'DO NOT STORE THIS BODY',
			'attachments' => array( '/secret/file.pdf' ),
		)
	);
	$class::record_failure( $error );
	$diagnostic = $GLOBALS['viazen_test_options']['viazen_mailersend_smtp_diagnostic'];
	viazen_assert( array( 'status', 'category', 'timestamp', 'recipient', 'subject', 'error' ) === array_keys( $diagnostic ), 'Diagnostic stored unexpected fields.' );
	viazen_assert( 'authentication-failure' === $diagnostic['category'], 'Authentication failure was classified incorrectly.' );
	viazen_assert( false === str_contains( $diagnostic['error'], 'saved-user' ), 'Diagnostic exposed the SMTP username.' );
	viazen_assert( false === str_contains( $diagnostic['error'], 'saved-password' ), 'Diagnostic exposed the SMTP password.' );
	viazen_assert( false === str_contains( serialize( $diagnostic ), 'DO NOT STORE THIS BODY' ), 'Diagnostic stored a message body.' );
	viazen_assert( false === str_contains( serialize( $diagnostic ), '/secret/file.pdf' ), 'Diagnostic stored an attachment path.' );

	$class::record_failure(
		new WP_Error(
			'Unknown mail failure',
			array(
				'to' => 'recipient@example.test',
				'subject' => array( 'unexpected' ),
			)
		)
	);
	$malformed_diagnostic = $GLOBALS['viazen_test_options']['viazen_mailersend_smtp_diagnostic'];
	viazen_assert( '' === $malformed_diagnostic['subject'], 'Malformed diagnostic subject was not rejected.' );

	echo "Isolated plugin harness passed.\n";
}
