<?php

namespace PHPMailer\PHPMailer {
	class Exception extends \Exception {}
	class SMTP {}

	class PHPMailer {
		public const ENCRYPTION_STARTTLS = 'tls';
		public static bool $smtpConnectResult = true;
		public static bool $throwOnConnect = false;
		public static int $smtpCloseCount = 0;
		public static int $smtpConnectCount = 0;
		public static int $constructorCount = 0;
		public bool $recipientsCleared = false;
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

		public function __construct() {
			++self::$constructorCount;
		}

		public function clearAllRecipients(): void {
			$this->recipientsCleared = true;
		}

		public function isSMTP(): void {
			$this->Mailer = 'smtp';
		}

		public function smtpConnect(): bool {
			++self::$smtpConnectCount;
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
			'turnstile_site_key' => 'saved-site-key',
			'turnstile_secret_key' => 'saved-secret-key',
		),
	);
	$GLOBALS['viazen_test_user_meta'] = array();
	$GLOBALS['viazen_test_styles'] = array();

	function add_action() {}
	function current_user_can() { return $GLOBALS['viazen_test_authorized'] ?? true; }
	function check_admin_referer() {
		if ( ! ( $GLOBALS['viazen_test_nonce_valid'] ?? true ) ) {
			throw new RuntimeException( 'Invalid test nonce' );
		}
	}
	function wp_die( $message ) { throw new RuntimeException( $message ); }
	function wp_create_nonce() { return 'test-nonce'; }
	function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }
	function wp_safe_redirect( $url ) { throw new RuntimeException( $url ); }
	function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $value ) ); }
	function add_filter( $hook, $callback, $priority = 10 ) {
		$GLOBALS['viazen_test_filters'][ $hook ][] = array( $callback, $priority );
	}
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
	viazen_assert( 2525 === $mailer->Port, 'SMTP port mismatch.' );
	viazen_assert( 'tls' === $mailer->SMTPSecure, 'STARTTLS mismatch.' );
	viazen_assert( true === $mailer->SMTPAuth && true === $mailer->SMTPAutoTLS, 'SMTP authentication settings mismatch.' );
	viazen_assert( 20 === $mailer->Timeout && 0 === $mailer->SMTPDebug, 'Timeout or debug setting mismatch.' );
	viazen_assert( 'sender@example.test' === $class::filter_from_email( 'other@example.test' ), 'From email was not overridden.' );
	viazen_assert( 'Viazen Sender' === $class::filter_from_name( 'Other Sender' ), 'From name was not overridden.' );
	viazen_assert( 'saved-site-key' === $class::filter_turnstile_site_key( '' ), 'Turnstile site key was not supplied.' );
	viazen_assert( 'saved-secret-key' === $class::filter_turnstile_secret_key( '' ), 'Turnstile secret key was not supplied.' );

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
	viazen_assert( str_contains( $unchecked_credentials_html, 'viazen-mailersend-smtp-credential-status--unchecked' ), 'Unchecked credential status is not visually classified.' );
	viazen_assert( str_contains( $unchecked_credentials_html, 'viazen-mailersend-smtp-credential-status__value' ), 'Credential status value is missing its prominent style hook.' );
	viazen_assert( false === str_contains( $unchecked_credentials_html, 'disabled="disabled"' ), 'Credential check was disabled with saved credentials.' );
	$GLOBALS['viazen_test_options']['viazen_mailersend_smtp_credential_status'] = 'valid';
	ob_start();
	$class::render_credential_check();
	$valid_credentials_html = ob_get_clean();
	viazen_assert( str_contains( $valid_credentials_html, '>Valid</strong>' ), 'Valid credential status is missing.' );
	viazen_assert( str_contains( $valid_credentials_html, 'viazen-mailersend-smtp-credential-status--valid' ), 'Valid credential status is not visually classified.' );
	$GLOBALS['viazen_test_options']['viazen_mailersend_smtp_credential_status'] = 'invalid';
	ob_start();
	$class::render_credential_check();
	$invalid_credentials_html = ob_get_clean();
	viazen_assert( str_contains( $invalid_credentials_html, '>Not valid</strong>' ), 'Invalid credential status is missing.' );
	viazen_assert( str_contains( $invalid_credentials_html, 'viazen-mailersend-smtp-credential-status--invalid' ), 'Invalid credential status is not visually classified.' );

	$class::enqueue_admin_assets( 'settings_page_other-plugin' );
	viazen_assert( array() === $GLOBALS['viazen_test_styles'], 'Admin stylesheet loaded on an unrelated page.' );
	$class::enqueue_admin_assets( 'settings_page_viazen-mailersend-smtp' );
	$admin_style = $GLOBALS['viazen_test_styles']['viazen-mailersend-smtp-admin'] ?? array();
	viazen_assert( str_ends_with( $admin_style['src'] ?? '', '/assets/css/admin-settings.css' ), 'Admin stylesheet URL is incorrect.' );
	viazen_assert( '1.1.1' === ( $admin_style['version'] ?? '' ), 'Admin stylesheet version is incorrect.' );

	ob_start();
	$class::render_username_field();
	$username_html = ob_get_clean();
	ob_start();
	$class::render_password_field();
	$password_html = ob_get_clean();
	ob_start();
	$class::render_turnstile_site_key_field();
	$turnstile_site_key_html = ob_get_clean();
	ob_start();
	$class::render_turnstile_secret_key_field();
	$turnstile_secret_key_html = ob_get_clean();
	viazen_assert( str_contains( $username_html, 'value="saved-user"' ), 'Saved username was not shown.' );
	viazen_assert( false === str_contains( $password_html, 'saved-password' ), 'Saved password entered HTML.' );
	viazen_assert( str_contains( $password_html, 'value="000000"' ), 'Saved password status did not render a six-character mask.' );
	viazen_assert( str_contains( $password_html, '<details>' ), 'Saved password did not render a native change control.' );
	viazen_assert( str_contains( $password_html, 'Change password' ), 'Saved password change control is missing its label.' );
	viazen_assert( str_contains( $turnstile_site_key_html, 'value="saved-site-key"' ), 'Saved Turnstile site key was not shown.' );
	viazen_assert( false === str_contains( $turnstile_secret_key_html, 'saved-secret-key' ), 'Saved Turnstile secret entered HTML.' );
	viazen_assert( str_contains( $turnstile_secret_key_html, 'value="000000"' ), 'Saved Turnstile secret status did not render a mask.' );
	viazen_assert( str_contains( $turnstile_secret_key_html, 'Change secret key' ), 'Saved Turnstile secret change control is missing.' );

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
	viazen_assert( 'saved-site-key' === $preserved['turnstile_site_key'], 'Omitted Turnstile site key did not preserve the saved value.' );
	viazen_assert( 'saved-secret-key' === $preserved['turnstile_secret_key'], 'Omitted Turnstile secret did not preserve the saved value.' );
	viazen_assert( 'invalid' === $GLOBALS['viazen_test_options']['viazen_mailersend_smtp_credential_status'], 'Unchanged credentials cleared their status.' );

	$replacement = $class::sanitize_settings(
		array(
			'smtp_username'       => 'saved-user',
			'smtp_password'       => '',
			'from_email'          => 'sender@example.test',
			'from_name'           => 'Viazen Sender',
			'turnstile_site_key'  => 'replacement-site-key',
			'turnstile_secret_key' => "replacement-\nsecret-key",
		)
	);
	viazen_assert( 'replacement-site-key' === $replacement['turnstile_site_key'], 'Replacement Turnstile site key was not sanitized and saved.' );
	viazen_assert( 'replacement-secret-key' === $replacement['turnstile_secret_key'], 'Replacement Turnstile secret was not sanitized and saved.' );

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
	viazen_assert( 'saved-site-key' === $malformed['turnstile_site_key'], 'Malformed Turnstile site key replaced the saved value.' );
	viazen_assert( 'saved-secret-key' === $malformed['turnstile_secret_key'], 'Malformed Turnstile secret replaced the saved value.' );

	$saved_settings = $GLOBALS['viazen_test_options']['viazen_mailersend_smtp_settings'];
	$GLOBALS['viazen_test_options']['viazen_mailersend_smtp_settings'] = array(
		'smtp_username' => array( 'unexpected' ),
		'smtp_password' => array( 'unexpected' ),
		'from_email' => array( 'unexpected' ),
		'from_name' => array( 'unexpected' ),
		'turnstile_site_key' => array( 'unexpected' ),
		'turnstile_secret_key' => array( 'unexpected' ),
	);
	$normalized_mailer = new \PHPMailer\PHPMailer\PHPMailer();
	$class::configure_phpmailer( $normalized_mailer );
	viazen_assert( '' === $normalized_mailer->Username, 'Malformed stored username was not rejected.' );
	viazen_assert( '' === $normalized_mailer->Password, 'Malformed stored password was not rejected.' );
	viazen_assert( 'admin@example.test' === $class::filter_from_email( 'other@example.test' ), 'Malformed stored From email did not use the safe default.' );
	viazen_assert( 'Viazen Test' === $class::filter_from_name( 'Other Sender' ), 'Malformed stored From name did not use the safe default.' );
	viazen_assert( 'fallback-site-key' === $class::filter_turnstile_site_key( 'fallback-site-key' ), 'Malformed stored Turnstile site key did not use the filter fallback.' );
	viazen_assert( 'fallback-secret-key' === $class::filter_turnstile_secret_key( 'fallback-secret-key' ), 'Malformed stored Turnstile secret did not use the filter fallback.' );
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

	// The same stubs cover managed mode. No real PHPMailer or WordPress is
	// loaded, and credential checks below count only calls to the fake object.
	viazen_assert( true === $class::managed_transport_allowed(), 'Unmanaged transport was unexpectedly blocked.' );
	viazen_assert( null === $class::guard_wp_mail( null ), 'Unmanaged mail short-circuit changed.' );
	viazen_assert( false === $class::guard_wp_mail( false ), 'Existing failure was lost in unmanaged mode.' );
	$guard_hooks = $GLOBALS['viazen_test_filters']['pre_wp_mail'] ?? array();
	viazen_assert( in_array( array( array( $class, 'guard_wp_mail' ), PHP_INT_MAX ), $guard_hooks, true ), 'Named mail guard is not registered.' );

	$scenario = $argv[1] ?? 'default';
	define( 'VIAZEN_MAILERSEND_SMTP_MANAGED', true );
	putenv( 'IS_DDEV_PROJECT' );
	putenv( 'WP_ENVIRONMENT_TYPE' );
	viazen_assert( false === $class::managed_transport_allowed(), 'Missing environment enabled SMTP.' );
	putenv( 'WP_ENVIRONMENT_TYPE=production' );
	viazen_assert( false === $class::managed_transport_allowed(), 'Missing sending opt-in enabled SMTP.' );

	$allow_value = match ( $scenario ) {
		'string-allow' => 'true',
		'integer-allow' => 1,
		'false-allow' => false,
		default => true,
	};
	define( 'VIAZEN_MAILERSEND_SMTP_ALLOW_SEND', $allow_value );
	viazen_assert( false === $class::managed_transport_allowed(), 'Imported DB credentials enabled managed SMTP.' );
	define( 'VIAZEN_MAILERSEND_SMTP_USERNAME', 'private-user' );
	viazen_assert( false === $class::managed_transport_allowed(), 'Missing private password enabled SMTP.' );
	define( 'VIAZEN_MAILERSEND_SMTP_PASSWORD', 'bad-password' === $scenario ? "private\npassword" : 'private-password' );
	viazen_assert( false === $class::managed_transport_allowed(), 'Missing private sender enabled SMTP.' );
	define( 'VIAZEN_MAILERSEND_SMTP_FROM_EMAIL', 'bad-sender' === $scenario ? 'not-an-email' : 'private-sender@example.test' );
	define( 'VIAZEN_MAILERSEND_SMTP_FROM_NAME', 'Private Sender' );
	if ( 'constant-staging' === $scenario ) {
		define( 'WP_ENVIRONMENT_TYPE', 'staging' );
	}
	if ( 'constant-production' === $scenario ) {
		define( 'WP_ENVIRONMENT_TYPE', 'production' );
	}

	$expected_allowed = in_array( $scenario, array( 'default', 'constant-production' ), true );
	viazen_assert( $expected_allowed === $class::managed_transport_allowed(), 'Complete configuration policy mismatch: ' . $scenario );
	viazen_assert( ( $expected_allowed ? null : false ) === $class::guard_wp_mail( null ), 'Managed mail result is not fail-closed.' );
	viazen_assert( false === $class::guard_wp_mail( false ), 'An earlier failure was overridden.' );
	viazen_assert( ( $expected_allowed ? true : false ) === $class::guard_wp_mail( true ), 'Blocked mail accepted an earlier success.' );

	$managed_clean = $class::sanitize_settings(
		array(
			'smtp_username' => 'submitted-user',
			'smtp_password' => 'submitted-password',
			'from_email' => 'submitted@example.test',
			'from_name' => 'Submitted Sender',
			'turnstile_site_key' => 'updated-site-key',
		)
	);
	viazen_assert( '' === $managed_clean['smtp_username'] && '' === $managed_clean['smtp_password'], 'Managed save persisted imported or submitted SMTP secrets.' );
	viazen_assert( ! str_contains( serialize( $managed_clean ), 'private-' ), 'Server-private configuration entered settings persistence.' );
	viazen_assert( 'sender@example.test' === $managed_clean['from_email'], 'Managed save altered read-only stored sender.' );
	viazen_assert( 'updated-site-key' === $managed_clean['turnstile_site_key'], 'Managed mode broke unrelated Turnstile settings.' );
	viazen_assert( 'saved-secret-key' === $managed_clean['turnstile_secret_key'], 'Managed save erased an unrelated secret.' );

	if ( $expected_allowed ) {
		$managed_mailer = new \PHPMailer\PHPMailer\PHPMailer();
		$class::configure_phpmailer( $managed_mailer );
		viazen_assert( 'smtp' === $managed_mailer->Mailer && 'smtp.mailersend.net' === $managed_mailer->Host, 'Managed transport configuration mismatch.' );
		viazen_assert( 'private-user' === $managed_mailer->Username && 'private-password' === $managed_mailer->Password, 'Managed transport used DB credentials.' );
		viazen_assert( 'private-sender@example.test' === $class::filter_from_email( 'other@example.test' ), 'Managed From email was not used.' );
		viazen_assert( 'Private Sender' === $class::filter_from_name( 'Other' ), 'Managed From name was not used.' );
		\PHPMailer\PHPMailer\PHPMailer::$smtpConnectResult = true;
		viazen_assert( true === $class::check_smtp_credentials(), 'Allowed managed check did not reach the fake transport.' );
		$class::record_failure( new WP_Error( 'private-user private-password', array() ) );
		viazen_assert( ! str_contains( serialize( $GLOBALS['viazen_test_options']['viazen_mailersend_smtp_diagnostic'] ), 'private-' ), 'Managed diagnostic exposed a secret.' );
	}

	ob_start();
	$class::render_username_field();
	$class::render_password_field();
	$class::render_from_email_field();
	$class::render_from_name_field();
	$managed_fields = ob_get_clean();
	viazen_assert( ! str_contains( $managed_fields, '<input' ), 'Managed transport fields remain editable.' );
	viazen_assert( ! str_contains( $managed_fields, 'private-' ) && ! str_contains( $managed_fields, 'saved-password' ), 'Managed fields disclosed credentials.' );
	viazen_assert( str_contains( $managed_fields, 'Read-only' ), 'Managed fields are not explained.' );

	// An explicit DDEV marker overrides even a production constant and keys.
	putenv( 'IS_DDEV_PROJECT=true' );
	viazen_assert( false === $class::managed_transport_allowed(), 'DDEV enabled production SMTP.' );
	$before_connections = \PHPMailer\PHPMailer\PHPMailer::$smtpConnectCount;
	$before_instances = \PHPMailer\PHPMailer\PHPMailer::$constructorCount;
	viazen_assert( false === $class::check_smtp_credentials(), 'Direct local credential check was not blocked.' );
	viazen_assert( $before_connections === \PHPMailer\PHPMailer\PHPMailer::$smtpConnectCount, 'Blocked check attempted a connection.' );
	viazen_assert( $before_instances === \PHPMailer\PHPMailer\PHPMailer::$constructorCount, 'Blocked check constructed a mailer.' );
	$blocked_mailer = new \PHPMailer\PHPMailer\PHPMailer();
	$class::configure_phpmailer( $blocked_mailer );
	viazen_assert( 'smtp' === $blocked_mailer->Mailer && $blocked_mailer->recipientsCleared, 'Defensive configuration permitted PHP-mail fallback or retained recipients.' );
	viazen_assert( '' === $blocked_mailer->Username && '' === $blocked_mailer->Password, 'Blocked mailer retained credentials.' );
	viazen_assert( false === $class::guard_wp_mail( null ), 'Local managed mail did not return boolean false.' );
	$blocked_diagnostic = $GLOBALS['viazen_test_options']['viazen_mailersend_smtp_diagnostic'];
	viazen_assert( 'policy-blocked' === $blocked_diagnostic['category'], 'Policy rejection was misclassified.' );
	viazen_assert( '' === $blocked_diagnostic['recipient'] && '' === $blocked_diagnostic['subject'], 'Blocked diagnostic retained mail metadata.' );
	ob_start();
	$class::render_credentials_section();
	$class::render_credential_check();
	$blocked_html = ob_get_clean();
	viazen_assert( str_contains( $blocked_html, 'Blocked by server policy' ), 'Blocked policy status is missing.' );
	viazen_assert( str_contains( $blocked_html, 'disabled="disabled"' ), 'Blocked credential check is still enabled.' );
	viazen_assert( ! str_contains( $blocked_html, '>Valid</strong>' ), 'Imported credential status appears valid on a blocked host.' );

	try {
		$class::handle_check_credentials();
		viazen_assert( false, 'Blocked credential handler did not redirect.' );
	} catch ( RuntimeException $error ) {
		viazen_assert( str_contains( $error->getMessage(), 'policy-blocked' ), 'Blocked handler falsely reported invalid credentials.' );
	}
	$GLOBALS['viazen_test_authorized'] = false;
	try {
		$class::handle_check_credentials();
		viazen_assert( false, 'Unauthorized handler was allowed.' );
	} catch ( RuntimeException $error ) {
		viazen_assert( str_contains( $error->getMessage(), 'not allowed' ), 'Handler lost capability enforcement.' );
	}
	$GLOBALS['viazen_test_authorized'] = true;
	$GLOBALS['viazen_test_nonce_valid'] = false;
	try {
		$class::handle_check_credentials();
		viazen_assert( false, 'Invalid nonce was allowed.' );
	} catch ( RuntimeException $error ) {
		viazen_assert( 'Invalid test nonce' === $error->getMessage(), 'Handler lost nonce enforcement.' );
	}
	viazen_assert( $before_connections === \PHPMailer\PHPMailer\PHPMailer::$smtpConnectCount, 'Admin policy/authorization checks connected to SMTP.' );

	putenv( 'IS_DDEV_PROJECT' );
	if ( ! defined( 'WP_ENVIRONMENT_TYPE' ) ) {
		foreach ( array( 'staging', 'development', 'local', '', 'invalid' ) as $environment ) {
			putenv( 'WP_ENVIRONMENT_TYPE=' . $environment );
			viazen_assert( false === $class::managed_transport_allowed(), 'Non-production environment enabled SMTP: ' . $environment );
		}
	}
	echo "Isolated plugin harness passed: {$scenario}.\n";
}
