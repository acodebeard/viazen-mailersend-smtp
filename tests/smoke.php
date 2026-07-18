<?php

namespace PHPMailer\PHPMailer {
	class Exception extends \Exception {}
	class SMTP {}

	class PHPMailer {
		public const ENCRYPTION_STARTTLS = 'tls';
		public static bool $smtpConnectResult = true;
		public static bool $throwOnConnect = false;
		public static int $smtpCloseCount = 0;
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

		public function smtpConnect(): bool {
			if ( self::$throwOnConnect ) {
				throw new \RuntimeException( 'Fake SMTP error with password=saved-password' );
			}

			return self::$smtpConnectResult;
		}

		public function smtpClose(): void {
			++self::$smtpCloseCount;
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
	$GLOBALS['viazen_test_user_meta'] = array();
	$GLOBALS['viazen_test_styles'] = array();

	function add_action() {}
	function add_filter() {}
	function register_activation_hook() {}
	function register_uninstall_hook() {}
	function get_bloginfo() { return 'Viazen Test'; }
	function get_option( $name, $default = false ) { return $GLOBALS['viazen_test_options'][ $name ] ?? $default; }
	function update_option( $name, $value ) { $GLOBALS['viazen_test_options'][ $name ] = $value; return true; }
	function delete_option( $name ) { unset( $GLOBALS['viazen_test_options'][ $name ] ); return true; }
	function sanitize_email( $value ) { return filter_var( $value, FILTER_SANITIZE_EMAIL ); }
	function is_email( $value ) { return false !== filter_var( $value, FILTER_VALIDATE_EMAIL ); }
	function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
	function wp_unslash( $value ) { return $value; }
	function add_settings_error() {}
	function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr__( $value ) { return $value; }
	function __( $value ) { return $value; }
	function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
	function esc_html__( $value ) { return $value; }
	function esc_html_e( $value ) { echo $value; }
	function esc_url( $value ) { return filter_var( $value, FILTER_SANITIZE_URL ); }
	function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . $path; }
	function wp_nonce_field() { echo '<input type="hidden" name="_wpnonce" value="test-nonce">'; }
	function disabled( $condition ) { if ( $condition ) { echo ' disabled="disabled"'; } }
	function get_current_user_id() { return 1; }
	function get_user_meta( $user_id, $key ) { return $GLOBALS['viazen_test_user_meta'][ $user_id ][ $key ] ?? ''; }
	function plugin_dir_url() { return 'https://example.test/wp-content/plugins/viazen-mailersend-smtp/'; }
	function wp_enqueue_style( $handle, $src, $dependencies, $version ) {
		$GLOBALS['viazen_test_styles'][ $handle ] = compact( 'src', 'dependencies', 'version' );
	}

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

	\PHPMailer\PHPMailer\PHPMailer::$smtpConnectResult = true;
	viazen_assert( true === $class::check_smtp_credentials(), 'Valid SMTP credentials were not accepted.' );
	\PHPMailer\PHPMailer\PHPMailer::$smtpConnectResult = false;
	viazen_assert( false === $class::check_smtp_credentials(), 'Rejected SMTP credentials were accepted.' );
	\PHPMailer\PHPMailer\PHPMailer::$throwOnConnect = true;
	viazen_assert( false === $class::check_smtp_credentials(), 'SMTP exception did not produce a safe invalid result.' );
	\PHPMailer\PHPMailer\PHPMailer::$throwOnConnect = false;
	viazen_assert( 3 === \PHPMailer\PHPMailer\PHPMailer::$smtpCloseCount, 'SMTP connections were not closed after credential checks.' );

	ob_start();
	$class::render_credential_check();
	$unchecked_credentials_html = ob_get_clean();
	viazen_assert( str_contains( $unchecked_credentials_html, 'Not checked' ), 'Unchecked credential status is missing.' );
	viazen_assert( false === str_contains( $unchecked_credentials_html, 'disabled="disabled"' ), 'Credential check was disabled with saved credentials.' );
	$GLOBALS['viazen_test_options']['viazen_mailersend_smtp_credential_status'] = 'valid';
	ob_start();
	$class::render_credential_check();
	$valid_credentials_html = ob_get_clean();
	viazen_assert( str_contains( $valid_credentials_html, '>Valid</span>' ), 'Valid credential status is missing.' );
	$GLOBALS['viazen_test_options']['viazen_mailersend_smtp_credential_status'] = 'invalid';
	ob_start();
	$class::render_credential_check();
	$invalid_credentials_html = ob_get_clean();
	viazen_assert( str_contains( $invalid_credentials_html, '>Not valid</span>' ), 'Invalid credential status is missing.' );

	$class::enqueue_admin_assets( 'settings_page_other-plugin' );
	viazen_assert( array() === $GLOBALS['viazen_test_styles'], 'Admin stylesheet loaded on an unrelated page.' );
	$class::enqueue_admin_assets( 'settings_page_viazen-mailersend-smtp' );
	$admin_style = $GLOBALS['viazen_test_styles']['viazen-mailersend-smtp-admin'] ?? array();
	viazen_assert( str_ends_with( $admin_style['src'] ?? '', '/assets/css/admin-settings.css' ), 'Admin stylesheet URL is incorrect.' );
	viazen_assert( '1.0.1' === ( $admin_style['version'] ?? '' ), 'Admin stylesheet version is incorrect.' );

	ob_start();
	$class::render_username_field();
	$username_html = ob_get_clean();
	ob_start();
	$class::render_password_field();
	$password_html = ob_get_clean();
	viazen_assert( str_contains( $username_html, 'value="saved-user"' ), 'Saved username was not shown.' );
	viazen_assert( false === str_contains( $password_html, 'saved-password' ), 'Saved password entered HTML.' );
	viazen_assert( str_contains( $password_html, 'value="000000"' ), 'Saved password status did not render a six-character mask.' );
	viazen_assert( str_contains( $password_html, '<details>' ), 'Saved password did not render a native change control.' );
	viazen_assert( str_contains( $password_html, 'Change password' ), 'Saved password change control is missing its label.' );

	$GLOBALS['viazen_test_options']['viazen_mailersend_smtp_settings']['smtp_password'] = '';
	ob_start();
	$class::render_password_field();
	$new_password_html = ob_get_clean();
	viazen_assert( false === str_contains( $new_password_html, 'value="000000"' ), 'Empty password rendered a saved-password mask.' );
	viazen_assert( str_contains( $new_password_html, 'name="viazen_mailersend_smtp_settings[smtp_password]"' ), 'Empty password did not render an editable field.' );
	ob_start();
	$class::render_credential_check();
	$missing_credentials_html = ob_get_clean();
	viazen_assert( str_contains( $missing_credentials_html, 'Not checked' ), 'Missing credentials displayed a stale status.' );
	viazen_assert( str_contains( $missing_credentials_html, 'disabled="disabled"' ), 'Credential check was enabled without a saved password.' );
	$GLOBALS['viazen_test_options']['viazen_mailersend_smtp_settings']['smtp_password'] = 'saved-password';

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
	viazen_assert( 'invalid' === $GLOBALS['viazen_test_options']['viazen_mailersend_smtp_credential_status'], 'Unchanged credentials cleared their status.' );

	$class::sanitize_settings(
		array(
			'smtp_username' => 'replacement-user',
			'smtp_password' => '',
			'from_email' => 'sender@example.test',
			'from_name' => 'Viazen Sender',
		)
	);
	viazen_assert( ! isset( $GLOBALS['viazen_test_options']['viazen_mailersend_smtp_credential_status'] ), 'Changed credentials retained a stale status.' );

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

	ob_start();
	$class::render_donation_link();
	$donation_html = ob_get_clean();
	viazen_assert( str_contains( $donation_html, 'https://paypal.me/acodebeard' ), 'Donation link destination is missing.' );
	viazen_assert( str_contains( $donation_html, 'target="_blank"' ), 'Donation link does not open separately.' );
	viazen_assert( str_contains( $donation_html, 'rel="noopener noreferrer"' ), 'Donation link is missing safe relationship attributes.' );
	viazen_assert( str_contains( $donation_html, 'Support this plugin via PayPal' ), 'Donation link label is missing.' );
	viazen_assert( str_contains( $donation_html, 'viazen_mailersend_smtp_dismiss_donation' ), 'Donation dismissal action is missing.' );
	viazen_assert( str_contains( $donation_html, '>Dismiss</button>' ), 'Donation dismissal control is missing.' );

	$GLOBALS['viazen_test_user_meta'][1]['viazen_mailersend_smtp_donation_dismissed'] = '1';
	ob_start();
	$class::render_donation_link();
	$dismissed_donation_html = ob_get_clean();
	viazen_assert( '' === $dismissed_donation_html, 'Dismissed donation link was rendered.' );

	echo "Isolated plugin harness passed.\n";
}
