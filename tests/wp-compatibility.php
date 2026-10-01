<?php
/**
 * Real WordPress 7.1 compatibility checks with in-memory mail delivery only.
 *
 * Explicit approval is required for WP-CLI eval-file. Stage this script beside
 * the reviewed viazen-mailersend-smtp.php; never install or activate it here.
 * Invoke with --skip-plugins --skip-themes --user=<approved-admin> and two
 * eval-file arguments: managed or legacy, then the absolute staged directory.
 * Legacy also requires the sibling
 * wp-compatibility-legacy-bootstrap.php via WP-CLI --require before bootstrap.
 *
 * No lifecycle, settings sanitizer, direct allowed credential check, or real
 * transport is invoked. SQL writes and WordPress HTTP are rejected throughout
 * this test phase; diagnostics are captured before Options API persistence.
 */

use PHPMailer\PHPMailer\Exception as MailException;
use Viazen\MailerSendSmtp\Plugin;

/** Fail without dumping credentials, rendered admin HTML, or message content. */
function viazen_compat_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

$mode = $args[0] ?? '';
viazen_compat_assert( in_array( $mode, array( 'managed', 'legacy' ), true ), 'Choose managed or legacy mode.' );
viazen_compat_assert( 'cli' === PHP_SAPI && defined( 'WP_CLI' ) && true === WP_CLI && defined( 'ABSPATH' ), 'DDEV WP-CLI WordPress bootstrap is required.' );
viazen_compat_assert( 'true' === getenv( 'IS_DDEV_PROJECT' ), 'Refusing to run outside DDEV.' );
viazen_compat_assert( 1 === preg_match( '/^7\.1(?:\.\d+)?$/', $GLOBALS['wp_version'] ?? '' ), 'This check requires an actual WordPress 7.1.x release.' );
viazen_compat_assert( current_user_can( 'manage_options' ), 'Select an existing authorized administrator.' );
viazen_compat_assert( ! class_exists( Plugin::class, false ), 'Connector is already loaded; do not test over an installed runtime.' );
viazen_compat_assert( class_exists( 'TALGV_Mail_Safety', false ) && TALGV_Mail_Safety::is_local() && ! TALGV_Mail_Safety::relay_allowed(), 'The local MU mail policy must already be active.' );
viazen_compat_assert( false !== has_filter( 'pre_wp_mail', array( 'TALGV_Mail_Safety', 'guard_mail' ) ), 'The MU policy hook is missing.' );
viazen_compat_assert( defined( 'VIAZEN_MAILERSEND_SMTP_MANAGED' ) && ( 'managed' === $mode ) === VIAZEN_MAILERSEND_SMTP_MANAGED, 'Managed-mode constant does not match the selected fixture.' );
foreach ( array( 'ALLOW_SEND', 'USERNAME', 'PASSWORD', 'FROM_EMAIL', 'FROM_NAME' ) as $suffix ) {
	viazen_compat_assert( ! defined( 'VIAZEN_MAILERSEND_SMTP_' . $suffix ), 'Private SMTP configuration must not be loaded for this synthetic test.' );
}
// Explicit paths avoid depending on magic constants inside WP-CLI eval-file.
$staged_directory = $args[1] ?? '';
viazen_compat_assert( is_string( $staged_directory ) && 1 === preg_match( '#^/tmp/talgv-mail-compat-[A-Za-z0-9_-]+$#', $staged_directory ) && realpath( $staged_directory ) === $staged_directory, 'Expected the explicit container-runtime compatibility directory.' );
$source = $staged_directory . '/viazen-mailersend-smtp.php';
$attachment = $staged_directory . '/wp-compatibility.php';
viazen_compat_assert( is_file( $source ) && ! is_link( $source ) && realpath( $source ) === $source, 'Expected the exact regular sibling connector source file.' );
viazen_compat_assert( is_file( $attachment ) && ! is_link( $attachment ) && realpath( $attachment ) === $attachment, 'Expected the staged test script as the synthetic attachment.' );

// Treat PHP compatibility warnings/deprecations as failures, without exposing
// potentially sensitive message text from an unexpected runtime callback.
set_error_handler( static function ( $severity, $message, $file, $line ) {
	if ( error_reporting() & $severity ) {
		throw new ErrorException( 'PHP compatibility diagnostic at ' . basename( $file ) . ':' . $line, 0, $severity );
	}
	return false;
} );

// This CLI test must not launch unrelated scheduled jobs or create a cron lock.
// Remove only core's request-shutdown launcher in this process; stored schedules,
// cron configuration, and subsequent site requests are unaffected.
remove_action( 'shutdown', '_wp_cron' );
viazen_compat_assert( false === has_action( 'shutdown', '_wp_cron' ), 'The test process still has the core shutdown cron launcher.' );

// These guards stay installed until process exit, including shutdown callbacks.
// Existing WordPress bootstrap happened before this file; do not claim that
// unrelated bootstrap code was retroactively prevented from running.
add_filter( 'query', static function ( $sql ) {
	if ( ! is_string( $sql ) || ! preg_match( '/^\s*(SELECT|SHOW|DESCRIBE|EXPLAIN)\b/i', $sql ) ) {
		throw new RuntimeException( 'Compatibility test rejected an SQL write or unrecognized query.' );
	}
	return $sql;
}, PHP_INT_MAX );
add_filter( 'pre_http_request', static function () {
	return new WP_Error( 'viazen_compat_http_blocked', 'HTTP is disabled during this compatibility test.' );
}, PHP_INT_MAX );

// All connector reads use synthetic fixtures, never imported real credentials.
// The diagnostic update filter returns the original value so update_option()
// stops before a database write while we inspect the actual plugin's output.
$settings_option = 'viazen_mailersend_smtp_settings';
$diagnostic_option = 'viazen_mailersend_smtp_diagnostic';
$fixture = array(
	$settings_option => array(
		'smtp_username' => 'compat-user',
		'smtp_password' => 'compat-password-do-not-display',
		'from_email' => 'compat-sender@example.invalid',
		'from_name' => 'Compatibility <Sender>',
		'turnstile_site_key' => 'compat-site-key',
		'turnstile_secret_key' => 'compat-turnstile-do-not-display',
	),
	$diagnostic_option => array(),
	'viazen_mailersend_smtp_credential_status' => 'valid',
);
foreach ( $fixture as $option => $value ) {
	add_filter( 'pre_option_' . $option, static function () use ( $value ) { return $value; }, PHP_INT_MAX );
}
$diagnostic = array();
$diagnostic_updates = 0;
add_filter( 'pre_update_option_' . $diagnostic_option, static function ( $value, $old ) use ( &$diagnostic, &$diagnostic_updates ) {
	$diagnostic = $value;
	++$diagnostic_updates;
	return $old;
}, PHP_INT_MAX, 2 );

require_once ABSPATH . 'wp-admin/includes/admin.php';
require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
require_once ABSPATH . WPINC . '/class-wp-phpmailer.php';
require $source;

// These are real hooks, capability APIs, Settings API, styles, and renderers.
// Never trigger broad admin_init/admin_menu actions or submit options.php.
foreach ( array( 'pre_wp_mail' => 'guard_wp_mail', 'phpmailer_init' => 'configure_phpmailer', 'wp_mail_from' => 'filter_from_email', 'wp_mail_from_name' => 'filter_from_name' ) as $hook => $callback ) {
	viazen_compat_assert( PHP_INT_MAX === has_filter( $hook, array( Plugin::class, $callback ) ), 'Connector hook missing: ' . $hook );
}
Plugin::register_settings();
Plugin::add_settings_page();
$registered = get_registered_settings();
viazen_compat_assert( isset( $registered[ $settings_option ] ) && false === $registered[ $settings_option ]['show_in_rest'], 'The real Settings API exposes or omits the credential setting.' );
viazen_compat_assert( array( Plugin::class, 'sanitize_settings' ) === $registered[ $settings_option ]['sanitize_callback'], 'Settings sanitizer registration changed.' );
Plugin::enqueue_admin_assets( 'settings_page_other' );
viazen_compat_assert( ! wp_style_is( 'viazen-mailersend-smtp-admin', 'enqueued' ), 'Styles leaked onto another admin page.' );
Plugin::enqueue_admin_assets( 'settings_page_viazen-mailersend-smtp' );
viazen_compat_assert( wp_style_is( 'viazen-mailersend-smtp-admin', 'enqueued' ), 'Scoped stylesheet did not register.' );
viazen_compat_assert( str_ends_with( wp_styles()->registered['viazen-mailersend-smtp-admin']->src, '/assets/css/admin-settings.css' ), 'Stylesheet URL construction changed.' );
$GLOBALS['title'] = 'SMTP compatibility check';
unset( $_GET['settings-updated'] ); // Prevent settings_errors() consuming a real transient.
ob_start();
Plugin::render_settings_page();
$html = ob_get_clean();
// Compare actual attributes, not quote style or attribute order in core HTML.
viazen_compat_assert( is_string( $html ), 'Settings renderer did not return HTML.' );
$inputs = new WP_HTML_Tag_Processor( $html );
$has_settings_group = false;
$has_settings_nonce = false;
while ( $inputs->next_tag( 'INPUT' ) ) {
	if ( 'hidden' !== $inputs->get_attribute( 'type' ) ) {
		continue;
	}
	$name = $inputs->get_attribute( 'name' );
	$value = $inputs->get_attribute( 'value' );
	if ( 'option_page' === $name && 'viazen_mailersend_smtp' === $value ) {
		$has_settings_group = true;
	}
	if ( '_wpnonce' === $name && is_string( $value ) && false !== wp_verify_nonce( $value, 'viazen_mailersend_smtp-options' ) ) {
		$has_settings_nonce = true;
	}
}
viazen_compat_assert( $has_settings_group, 'Settings form group is missing.' );
viazen_compat_assert( $has_settings_nonce, 'A valid WordPress settings nonce is missing.' );
viazen_compat_assert( str_contains( $html, 'for="viazen-mailersend-smtp-recipient"' ), 'Test recipient label is missing.' );
viazen_compat_assert( ! str_contains( $html, 'compat-password-do-not-display' ) && ! str_contains( $html, 'compat-turnstile-do-not-display' ), 'A synthetic secret leaked into admin HTML.' );
if ( 'managed' === $mode ) {
	viazen_compat_assert( str_contains( $html, 'Read-only:' ) && str_contains( $html, 'Blocked by server policy' ), 'Managed policy UI is missing.' );
	viazen_compat_assert( ! str_contains( $html, 'name="viazen_mailersend_smtp_settings[smtp_password]"' ), 'Managed password remains editable.' );
} else {
	viazen_compat_assert( str_contains( $html, 'value="compat-user"' ) && str_contains( $html, 'value="000000"' ), 'Legacy credential UI did not retain its mask.' );
	viazen_compat_assert( str_contains( $html, 'Compatibility &lt;Sender&gt;' ), 'Sender output is not escaped.' );
}
unset( $html );

// Exercise the renderer's real capability boundary without changing an account.
$admin_id = get_current_user_id();
$die_handler = static function () { return static function () { throw new DomainException( 'COMPAT_ACCESS_DENIED' ); }; };
add_filter( 'wp_die_handler', $die_handler, PHP_INT_MAX );
try {
	wp_set_current_user( 0 );
	try {
		Plugin::render_settings_page();
		throw new RuntimeException( 'Unauthenticated settings rendering was allowed.' );
	} catch ( DomainException $error ) {
		viazen_compat_assert( 'COMPAT_ACCESS_DENIED' === $error->getMessage(), 'Unexpected capability failure.' );
	}
} finally {
	wp_set_current_user( $admin_id );
	remove_filter( 'wp_die_handler', $die_handler, PHP_INT_MAX );
}

/**
 * Keep real MIME/header processing but replace the last transport operation.
 * Any attempted real SMTP connection or PHP/sendmail fallback is a hard error.
 */
final class Viazen_Compatibility_Mailer extends WP_PHPMailer {
	public int $attempts = 0;
	public bool $fail_transport = false;
	public string $captured_mime = '';

	protected function smtpSend( $header, $body ) {
		++$this->attempts;
		$this->captured_mime = $this->getSentMIMEMessage();
		if ( $this->fail_transport ) {
			throw new MailException( 'SMTP authentication failed username=compat-user password=compat-password-do-not-display' );
		}
		return true;
	}
	public function smtpConnect( $options = null ) {
		throw new RuntimeException( 'Real SMTP connections are forbidden in this compatibility test.' );
	}
	protected function mailSend( $header, $body ) {
		throw new RuntimeException( 'PHP mail fallback is forbidden in this compatibility test.' );
	}
	protected function sendmailSend( $header, $body ) {
		throw new RuntimeException( 'Sendmail fallback is forbidden in this compatibility test.' );
	}
}
$GLOBALS['phpmailer'] = new Viazen_Compatibility_Mailer( true );
$mailer = $GLOBALS['phpmailer'];

// A callback that sends earlier than PHPMailer would evade the transport
// double. Fail rather than silently testing alongside unknown mail handlers.
$allowed_callbacks = array( 'wp_staticize_emoji_for_email' );
foreach ( array(
	array( 'TALGV_Mail_Safety', 'guard_mail' ), array( 'TALGV_Mail_Safety', 'configure_mailpit' ),
	array( Plugin::class, 'guard_wp_mail' ), array( Plugin::class, 'configure_phpmailer' ),
	array( Plugin::class, 'filter_from_email' ), array( Plugin::class, 'filter_from_name' ),
	array( Plugin::class, 'record_success' ), array( Plugin::class, 'record_failure' ),
) as $callback ) {
	$allowed_callbacks[] = $callback;
}
foreach ( array( 'wp_mail', 'pre_wp_mail', 'phpmailer_init', 'wp_mail_from', 'wp_mail_from_name', 'wp_mail_succeeded', 'wp_mail_failed' ) as $hook ) {
	foreach ( $GLOBALS['wp_filter'][ $hook ]->callbacks ?? array() as $callbacks ) {
		foreach ( $callbacks as $callback ) {
			$known_callback = in_array( $callback['function'], $allowed_callbacks, true );
			if ( ! $known_callback && 'wp_mail_from' === $hook && $callback['function'] instanceof Closure ) {
				// Reviewed WP-CLI Runner helper only fixes an otherwise empty
				// wordpress@ sender. It neither sends mail nor changes transport.
				// Do not generally permit anonymous filters from MU-plugins.
				$reflection = new ReflectionFunction( $callback['function'] );
				$scope = $reflection->getClosureScopeClass();
				$runner = new ReflectionClass( 'WP_CLI\\Runner' );
				$parameters = $reflection->getParameters();
				$known_callback = $reflection->isStatic()
					&& null !== $scope && 'WP_CLI\\Runner' === $scope->getName()
					&& $runner->getFileName() === $reflection->getFileName()
					&& 1 === count( $parameters ) && 'from_email' === $parameters[0]->getName();
			}
			viazen_compat_assert( $known_callback, 'Unexpected callback on mail hook: ' . $hook );
		}
	}
}

if ( 'managed' === $mode ) {
	viazen_compat_assert( false === Plugin::managed_transport_allowed(), 'DDEV incorrectly permits managed SMTP.' );
	viazen_compat_assert( false === wp_mail( 'compat-recipient@example.invalid', 'Blocked compatibility test', 'Synthetic only.' ), 'Managed mail did not fail closed.' );
	viazen_compat_assert( 0 === $mailer->attempts && 'policy-blocked' === ( $diagnostic['category'] ?? '' ), 'Blocked mail reached transport or lost its policy diagnostic.' );
	viazen_compat_assert( false === Plugin::check_smtp_credentials(), 'Managed direct credential check was not blocked.' );
	// Manual inclusion happened after plugins_loaded. Reapply the existing local
	// MU policy so its named hook removals and local transport now take effect.
	TALGV_Mail_Safety::finish_registration();
	viazen_compat_assert( false === has_action( 'phpmailer_init', array( Plugin::class, 'configure_phpmailer' ) ), 'Managed connector transport was not removed for capture.' );
} else {
	// Never call check_smtp_credentials() in legacy mode: it creates a different
	// PHPMailer object, outside the no-network global transport double.
	viazen_compat_assert( true === Plugin::managed_transport_allowed(), 'Legacy mode unexpectedly changed permission.' );
}

// The final hook asserts the expected real configuration before the transport
// double accepts anything. Only fake credentials ever enter the legacy object.
add_action( 'phpmailer_init', static function ( $configured ) use ( $mailer, $mode ): void {
	viazen_compat_assert( $configured === $mailer && 'smtp' === $configured->Mailer, 'Unexpected mailer or PHP-mail fallback.' );
	viazen_compat_assert( ( 'managed' === $mode ? '127.0.0.1' : 'smtp.mailersend.net' ) === $configured->Host, 'Transport host mismatch.' );
	viazen_compat_assert( ( 'managed' === $mode ? 1025 : 2525 ) === $configured->Port, 'Transport port mismatch.' );
	if ( 'managed' === $mode ) {
		viazen_compat_assert( ! $configured->SMTPAuth && '' === $configured->Username && '' === $configured->Password && '' === $configured->SMTPSecure && ! $configured->SMTPAutoTLS, 'Local transport retained credentials or TLS.' );
	} else {
		viazen_compat_assert( $configured->SMTPAuth && $configured->SMTPAutoTLS && 'tls' === $configured->SMTPSecure && 'compat-user' === $configured->Username && 'compat-password-do-not-display' === $configured->Password, 'Legacy SMTP configuration changed.' );
	}
}, PHP_INT_MAX );

$headers = array(
	'Content-Type: text/html; charset=UTF-8',
	'From: Compatibility <compat-envelope@example.invalid>',
	'Reply-To: Visitor <compat-reply@example.invalid>',
	'Cc: compat-cc@example.invalid', 'Bcc: compat-bcc@example.invalid',
);
$body = '<p>Synthetic compatibility body. No staff or applicant information.</p>';
$sent = wp_mail( 'compat-recipient@example.invalid', 'Compatibility MIME test', $body, $headers, array( $attachment ) );
viazen_compat_assert( true === $sent && 1 === $mailer->attempts, 'Real WordPress mail did not reach the in-memory transport.' );
viazen_compat_assert( ( 'managed' === $mode ? 'compat-envelope@example.invalid' : 'compat-sender@example.invalid' ) === $mailer->From, 'From handling changed.' );
viazen_compat_assert( 1 === count( $mailer->getReplyToAddresses() ) && 1 === count( $mailer->getCcAddresses() ) && 1 === count( $mailer->getBccAddresses() ), 'Reply-To, CC, or BCC was lost.' );
viazen_compat_assert( $body === $mailer->Body && 1 === count( $mailer->getAttachments() ) && str_contains( $mailer->captured_mime, basename( $attachment ) ), 'Real MIME/body/attachment handling changed.' );
viazen_compat_assert( 'success' === ( $diagnostic['status'] ?? '' ) && 'transport-accepted' === ( $diagnostic['category'] ?? '' ), 'Real success hook did not reach connector diagnostics.' );
viazen_compat_assert( ! str_contains( serialize( $diagnostic ), $body ) && ! str_contains( serialize( $diagnostic ), $attachment ), 'Diagnostic retained body or attachment path.' );
$mailer->fail_transport = true;
viazen_compat_assert( false === wp_mail( 'compat-recipient@example.invalid', 'Compatibility failure test', 'Synthetic failure.' ) && 2 === $mailer->attempts, 'Real WordPress exception handling changed.' );
viazen_compat_assert( 'failure' === ( $diagnostic['status'] ?? '' ) && 'authentication-failure' === ( $diagnostic['category'] ?? '' ), 'Real failure hook did not classify the test exception.' );
viazen_compat_assert( ! str_contains( serialize( $diagnostic ), 'compat-password-do-not-display' ), 'Diagnostic failed to redact the fake password.' );
viazen_compat_assert( ( 'managed' === $mode ? 3 : 2 ) === $diagnostic_updates, 'Unexpected diagnostic update count.' );

WP_CLI::success( 'Compatibility passed: WordPress ' . $GLOBALS['wp_version'] . ', PHP ' . PHP_VERSION . ', mode ' . $mode . '. Test-phase SQL writes rejected; MIME captured in memory, no transport sockets.' );
WP_CLI::log( 'Reviewed source SHA-256: ' . hash_file( 'sha256', $source ) );
