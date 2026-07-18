<?php
/**
 * Plugin Name:       SMTP Connector for MailerSend
 * Plugin URI:        https://github.com/acodebeard/viazen-mailersend-smtp
 * Description:       Independent integration that routes WordPress email through MailerSend SMTP.
 * Version:           1.0.1
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            acodebeard
 * Author URI:        https://github.com/acodebeard
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       viazen-mailersend-smtp
 *
 * @package ViazenMailerSendSmtp
 */

namespace Viazen\MailerSendSmtp;

use PHPMailer\PHPMailer\PHPMailer;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Focused, independent MailerSend SMTP integration.
 */
final class Plugin {

	/** Plugin settings option. */
	private const OPTION_SETTINGS = 'viazen_mailersend_smtp_settings';

	/** Plugin version used for cache-safe admin assets. */
	private const VERSION = '1.0.1';

	/** Most recent mail result option. */
	private const OPTION_DIAGNOSTIC = 'viazen_mailersend_smtp_diagnostic';

	/** Settings group used by the Settings API. */
	private const SETTINGS_GROUP = 'viazen_mailersend_smtp';

	/** Settings page slug. */
	private const PAGE_SLUG = 'viazen-mailersend-smtp';

	/** Nonce action for test email requests. */
	private const TEST_NONCE_ACTION = 'viazen_mailersend_smtp_send_test';

	/** Nonce action for diagnostic clearing. */
	private const CLEAR_NONCE_ACTION = 'viazen_mailersend_smtp_clear_diagnostic';

	/** Nonce action for one-time action notices. */
	private const NOTICE_NONCE_ACTION = 'viazen_mailersend_smtp_notice';

	/** Optional support link shown only on this plugin's settings page. */
	private const DONATE_URL = 'https://paypal.me/acodebeard';

	/** Nonce action for dismissing the settings-page support link. */
	private const DONATION_DISMISS_NONCE_ACTION = 'viazen_mailersend_smtp_dismiss_donation';

	/** Per-user record for a dismissed settings-page support link. */
	private const DONATION_DISMISSED_META = 'viazen_mailersend_smtp_donation_dismissed';

	/**
	 * Conservative list of plugins known to configure SMTP or mail routing.
	 *
	 * @var array<string, string>
	 */
	private const KNOWN_MAIL_PLUGINS = array(
		'mailersend-official-smtp-integration/mailersend-wordpress.php' => 'MailerSend – Official SMTP Integration',
		'wp-mail-smtp/wp_mail_smtp.php' => 'WP Mail SMTP',
		'easy-wp-smtp/easy-wp-smtp.php' => 'Easy WP SMTP',
		'fluent-smtp/fluent-smtp.php'   => 'FluentSMTP',
		'post-smtp/postman-smtp.php'    => 'Post SMTP',
	);

	/**
	 * Registers plugin hooks.
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_action( 'phpmailer_init', array( self::class, 'configure_phpmailer' ), PHP_INT_MAX );
		add_filter( 'wp_mail_from', array( self::class, 'filter_from_email' ), PHP_INT_MAX );
		add_filter( 'wp_mail_from_name', array( self::class, 'filter_from_name' ), PHP_INT_MAX );

		add_action( 'wp_mail_failed', array( self::class, 'record_failure' ) );
		add_action( 'wp_mail_succeeded', array( self::class, 'record_success' ) );

		add_action( 'admin_menu', array( self::class, 'add_settings_page' ) );
		add_action( 'admin_init', array( self::class, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_admin_assets' ) );
		add_action( 'admin_notices', array( self::class, 'render_conflict_notice' ) );
		add_action( 'admin_post_viazen_mailersend_smtp_send_test', array( self::class, 'handle_send_test' ) );
		add_action( 'admin_post_viazen_mailersend_smtp_clear_diagnostic', array( self::class, 'handle_clear_diagnostic' ) );
		add_action( 'admin_post_viazen_mailersend_smtp_dismiss_donation', array( self::class, 'handle_dismiss_donation' ) );
	}

	/**
	 * Creates non-autoloaded options without storing any credentials.
	 *
	 * @return void
	 */
	public static function activate(): void {
		add_option( self::OPTION_SETTINGS, self::get_default_settings(), '', false );
		add_option( self::OPTION_DIAGNOSTIC, array(), '', false );
	}

	/**
	 * Routes PHPMailer through MailerSend SMTP.
	 *
	 * This changes only the transport. Existing recipients, Reply-To, CC, BCC,
	 * content type, message content, and attachments remain on the mail object.
	 *
	 * @param PHPMailer $phpmailer WordPress PHPMailer instance.
	 * @return void
	 */
	public static function configure_phpmailer( PHPMailer $phpmailer ): void {
		$settings = self::get_settings();

		$phpmailer->isSMTP();
		// PHPMailer public property names are part of its external API.
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->Host        = 'smtp.mailersend.net';
		$phpmailer->Port        = 587;
		$phpmailer->SMTPSecure  = PHPMailer::ENCRYPTION_STARTTLS;
		$phpmailer->SMTPAuth    = true;
		$phpmailer->SMTPAutoTLS = true;
		$phpmailer->Username    = $settings['smtp_username'];
		$phpmailer->Password    = $settings['smtp_password'];
		$phpmailer->Timeout     = 20;
		$phpmailer->SMTPDebug   = 0;
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	}

	/**
	 * Overrides the sender email at the latest practical filter priority.
	 *
	 * @param string $email Existing sender email.
	 * @return string
	 */
	public static function filter_from_email( string $email ): string {
		$from_email = self::get_settings()['from_email'];

		return is_email( $from_email ) ? $from_email : $email;
	}

	/**
	 * Overrides the sender name at the latest practical filter priority.
	 *
	 * @param string $name Existing sender name.
	 * @return string
	 */
	public static function filter_from_name( string $name ): string {
		$from_name = self::get_settings()['from_name'];

		return '' !== $from_name ? $from_name : $name;
	}

	/**
	 * Adds Settings > SMTP Connector for MailerSend.
	 *
	 * @return void
	 */
	public static function add_settings_page(): void {
		add_options_page(
			esc_html__( 'SMTP Connector for MailerSend', 'viazen-mailersend-smtp' ),
			esc_html__( 'SMTP Connector for MailerSend', 'viazen-mailersend-smtp' ),
			'manage_options',
			self::PAGE_SLUG,
			array( self::class, 'render_settings_page' )
		);
	}

	/**
	 * Loads the small settings stylesheet only on this plugin's admin page.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public static function enqueue_admin_assets( string $hook_suffix ): void {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'viazen-mailersend-smtp-admin',
			plugin_dir_url( __FILE__ ) . 'assets/css/admin-settings.css',
			array(),
			self::VERSION
		);
	}

	/**
	 * Registers the settings and their fields with the Settings API.
	 *
	 * @return void
	 */
	public static function register_settings(): void {
		register_setting(
			self::SETTINGS_GROUP,
			self::OPTION_SETTINGS,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( self::class, 'sanitize_settings' ),
				'default'           => self::get_default_settings(),
				'show_in_rest'      => false,
			)
		);

		add_settings_section(
			'viazen_mailersend_smtp_credentials',
			esc_html__( 'SMTP credentials', 'viazen-mailersend-smtp' ),
			array( self::class, 'render_credentials_section' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			'viazen_mailersend_smtp_username',
			esc_html__( 'SMTP username', 'viazen-mailersend-smtp' ),
			array( self::class, 'render_username_field' ),
			self::PAGE_SLUG,
			'viazen_mailersend_smtp_credentials'
		);

		add_settings_field(
			'viazen_mailersend_smtp_password',
			esc_html__( 'SMTP password', 'viazen-mailersend-smtp' ),
			array( self::class, 'render_password_field' ),
			self::PAGE_SLUG,
			'viazen_mailersend_smtp_credentials'
		);

		add_settings_section(
			'viazen_mailersend_smtp_sender',
			esc_html__( 'Sender', 'viazen-mailersend-smtp' ),
			array( self::class, 'render_sender_section' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			'viazen_mailersend_smtp_from_email',
			esc_html__( 'From email', 'viazen-mailersend-smtp' ),
			array( self::class, 'render_from_email_field' ),
			self::PAGE_SLUG,
			'viazen_mailersend_smtp_sender'
		);

		add_settings_field(
			'viazen_mailersend_smtp_from_name',
			esc_html__( 'From name', 'viazen-mailersend-smtp' ),
			array( self::class, 'render_from_name_field' ),
			self::PAGE_SLUG,
			'viazen_mailersend_smtp_sender'
		);
	}

	/**
	 * Sanitizes settings while preserving blank credential fields.
	 *
	 * @param mixed $input Submitted option value.
	 * @return array<string, string>
	 */
	public static function sanitize_settings( $input ): array {
		$existing = self::get_settings();
		$clean    = $existing;
		$input    = is_array( $input ) ? wp_unslash( $input ) : array();

		if ( isset( $input['smtp_username'] ) && is_string( $input['smtp_username'] ) ) {
			$username = trim( sanitize_text_field( $input['smtp_username'] ) );
			if ( '' !== $username ) {
				$clean['smtp_username'] = self::limit_text( $username, 320 );
			}
		}

		if ( isset( $input['smtp_password'] ) && is_string( $input['smtp_password'] ) && '' !== $input['smtp_password'] ) {
			$password = preg_replace( '/[\x00-\x1F\x7F]/', '', $input['smtp_password'] );
			if ( is_string( $password ) && '' !== $password ) {
				$clean['smtp_password'] = self::limit_text( $password, 1024, false );
			}
		}

		$submitted_email = isset( $input['from_email'] ) && is_string( $input['from_email'] ) ? trim( $input['from_email'] ) : '';
		$from_email      = sanitize_email( $submitted_email );
		if ( '' !== $from_email && is_email( $from_email ) ) {
			$clean['from_email'] = $from_email;
		} else {
			add_settings_error(
				self::OPTION_SETTINGS,
				'viazen_mailersend_smtp_invalid_from_email',
				esc_html__( 'Enter a valid From email address.', 'viazen-mailersend-smtp' )
			);
		}

		$from_name = isset( $input['from_name'] ) && is_string( $input['from_name'] ) ? trim( sanitize_text_field( $input['from_name'] ) ) : '';
		if ( '' !== $from_name ) {
			$clean['from_name'] = self::limit_text( $from_name, 200 );
		} else {
			add_settings_error(
				self::OPTION_SETTINGS,
				'viazen_mailersend_smtp_invalid_from_name',
				esc_html__( 'Enter a From name.', 'viazen-mailersend-smtp' )
			);
		}

		return $clean;
	}

	/**
	 * Renders the fixed SMTP endpoint description.
	 *
	 * @return void
	 */
	public static function render_credentials_section(): void {
		echo '<p>' . esc_html__( 'Mail is sent through smtp.mailersend.net using authenticated STARTTLS on port 587.', 'viazen-mailersend-smtp' ) . '</p>';
	}

	/**
	 * Renders the sender section description.
	 *
	 * @return void
	 */
	public static function render_sender_section(): void {
		echo '<p>' . esc_html__( 'This sender overrides From values supplied by forms and other plugins. Their Reply-To headers remain unchanged.', 'viazen-mailersend-smtp' ) . '</p>';
	}

	/**
	 * Warns administrators when another known mail-routing plugin is active.
	 *
	 * The warning is informational. It never disables another plugin or stops
	 * mail because administrators may need access while resolving a conflict.
	 *
	 * @return void
	 */
	public static function render_conflict_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$conflicts = self::get_active_mail_plugin_conflicts();
		if ( empty( $conflicts ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p><strong>%1$s</strong> %2$s</p></div>',
			esc_html__( 'Possible mail-plugin conflict:', 'viazen-mailersend-smtp' ),
			esc_html(
				sprintf(
					/* translators: %s: Comma-separated plugin names. */
					__( '%s is also active. Multiple mail-routing plugins can configure the same PHPMailer instance and produce unpredictable results. Keep only the intended SMTP plugin active.', 'viazen-mailersend-smtp' ),
					implode( ', ', $conflicts )
				)
			)
		);
	}

	/**
	 * Renders the saved SMTP username for review or replacement.
	 *
	 * @return void
	 */
	public static function render_username_field(): void {
		$settings = self::get_settings();

		printf(
			'<input type="text" class="regular-text" id="viazen-mailersend-smtp-username" name="%1$s[smtp_username]" value="%2$s" autocomplete="off" spellcheck="false"><p class="description">%3$s</p>',
			esc_attr( self::OPTION_SETTINGS ),
			esc_attr( $settings['smtp_username'] ),
			esc_html__( 'The SMTP username currently used to authenticate with MailerSend.', 'viazen-mailersend-smtp' )
		);
	}

	/**
	 * Renders password status and a blank replacement field when requested.
	 *
	 * The six-character value is a fixed visual mask, not the saved password.
	 * The saved password never enters the generated HTML.
	 *
	 * @return void
	 */
	public static function render_password_field(): void {
		$settings = self::get_settings();

		if ( '' === $settings['smtp_password'] ) {
			printf(
				'<input type="password" class="regular-text" id="viazen-mailersend-smtp-password" name="%1$s[smtp_password]" autocomplete="new-password" spellcheck="false"><p class="description">%2$s</p>',
				esc_attr( self::OPTION_SETTINGS ),
				esc_html__( 'Enter the SMTP password supplied by MailerSend.', 'viazen-mailersend-smtp' )
			);
			return;
		}

		printf(
			'<input type="password" class="regular-text" id="viazen-mailersend-smtp-password-saved" value="000000" disabled autocomplete="off" aria-label="%1$s"><details><summary class="button-link">%2$s</summary><p><label class="screen-reader-text" for="viazen-mailersend-smtp-password">%3$s</label><input type="password" class="regular-text" id="viazen-mailersend-smtp-password" name="%4$s[smtp_password]" autocomplete="new-password" spellcheck="false" aria-describedby="viazen-mailersend-smtp-password-description"></p><p class="description" id="viazen-mailersend-smtp-password-description">%5$s</p></details>',
			esc_attr__( 'A saved SMTP password is set.', 'viazen-mailersend-smtp' ),
			esc_html__( 'Change password', 'viazen-mailersend-smtp' ),
			esc_html__( 'New SMTP password', 'viazen-mailersend-smtp' ),
			esc_attr( self::OPTION_SETTINGS ),
			esc_html__( 'Enter a new password to replace the saved password. Leaving this blank keeps the saved password.', 'viazen-mailersend-smtp' )
		);
	}

	/**
	 * Renders the From email field.
	 *
	 * @return void
	 */
	public static function render_from_email_field(): void {
		$settings = self::get_settings();

		printf(
			'<input type="email" class="regular-text" id="viazen-mailersend-smtp-from-email" name="%1$s[from_email]" value="%2$s" required>',
			esc_attr( self::OPTION_SETTINGS ),
			esc_attr( $settings['from_email'] )
		);
	}

	/**
	 * Renders the From name field.
	 *
	 * @return void
	 */
	public static function render_from_name_field(): void {
		$settings = self::get_settings();

		printf(
			'<input type="text" class="regular-text" id="viazen-mailersend-smtp-from-name" name="%1$s[from_name]" value="%2$s" required>',
			esc_attr( self::OPTION_SETTINGS ),
			esc_attr( $settings['from_name'] )
		);
	}

	/**
	 * Renders the settings, test email, and latest diagnostic result.
	 *
	 * @return void
	 */
	public static function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage these settings.', 'viazen-mailersend-smtp' ) );
		}
		?>
		<div class="wrap viazen-mailersend-smtp-settings">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<?php self::render_action_notice(); ?>
			<?php settings_errors(); ?>

			<form action="options.php" method="post">
				<?php settings_fields( self::SETTINGS_GROUP ); ?>
				<?php do_settings_sections( self::PAGE_SLUG ); ?>
				<?php submit_button( esc_html__( 'Save Settings', 'viazen-mailersend-smtp' ) ); ?>
			</form>

			<hr>
			<?php self::render_contact_form_7_guidance(); ?>

			<hr>
			<h2><?php esc_html_e( 'Send Test Email', 'viazen-mailersend-smtp' ); ?></h2>
			<p><?php esc_html_e( 'This calls WordPress wp_mail() and follows the same SMTP path as Contact Form 7.', 'viazen-mailersend-smtp' ); ?></p>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="viazen_mailersend_smtp_send_test">
				<?php wp_nonce_field( self::TEST_NONCE_ACTION ); ?>
				<label for="viazen-mailersend-smtp-recipient"><strong><?php esc_html_e( 'Recipient email', 'viazen-mailersend-smtp' ); ?></strong></label><br>
				<input type="email" class="regular-text" id="viazen-mailersend-smtp-recipient" name="recipient_email" required>
				<?php submit_button( esc_html__( 'Send Test Email', 'viazen-mailersend-smtp' ), 'secondary', 'submit', false ); ?>
			</form>

			<hr>
			<?php self::render_diagnostic(); ?>

			<?php self::render_donation_link(); ?>
		</div>
		<?php
	}

	/**
	 * Renders a restrained support link on this plugin's settings page.
	 *
	 * @return void
	 */
	public static function render_donation_link(): void {
		$user_id = get_current_user_id();
		if ( 0 < $user_id && '1' === get_user_meta( $user_id, self::DONATION_DISMISSED_META, true ) ) {
			return;
		}
		?>
		<hr>
		<div class="notice notice-info inline">
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="viazen_mailersend_smtp_dismiss_donation">
				<?php wp_nonce_field( self::DONATION_DISMISS_NONCE_ACTION ); ?>
				<p>
					<a href="<?php echo esc_url( self::DONATE_URL ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Support this plugin via PayPal', 'viazen-mailersend-smtp' ); ?><span class="screen-reader-text"> <?php esc_html_e( '(opens in a new tab)', 'viazen-mailersend-smtp' ); ?></span></a>
					<span aria-hidden="true"> &middot; </span>
					<button type="submit" class="button-link"><?php esc_html_e( 'Dismiss', 'viazen-mailersend-smtp' ); ?></button>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Persists dismissal of the settings-page support link for one user.
	 *
	 * @return never
	 */
	public static function handle_dismiss_donation() {
		self::require_settings_access();
		check_admin_referer( self::DONATION_DISMISS_NONCE_ACTION );
		update_user_meta( get_current_user_id(), self::DONATION_DISMISSED_META, '1' );

		$url = add_query_arg( 'page', self::PAGE_SLUG, admin_url( 'options-general.php' ) );
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Renders site-specific Contact Form 7 and DNS guidance.
	 *
	 * @return void
	 */
	private static function render_contact_form_7_guidance(): void {
		$settings     = self::get_settings();
		$example_name = '' !== $settings['from_name'] ? $settings['from_name'] : __( 'Website Name', 'viazen-mailersend-smtp' );
		$example_mail = is_email( $settings['from_email'] ) ? $settings['from_email'] : 'forms@example.com';
		$from_example = sprintf( '%1$s <%2$s>', $example_name, $example_mail );
		?>
		<h2><?php esc_html_e( 'Contact Form 7 configuration', 'viazen-mailersend-smtp' ); ?></h2>
		<p><?php esc_html_e( 'Use the MailerSend-verified domain for From. Put the visitor address in Reply-To, never in From.', 'viazen-mailersend-smtp' ); ?></p>
		<pre><?php echo esc_html( "From:\n{$from_example}\n\nReply-To:\n[your-email]" ); ?></pre>
		<ul>
			<li><?php esc_html_e( 'Keep DNS records required by MailerSend and by your existing email provider while those services remain in use.', 'viazen-mailersend-smtp' ); ?></li>
			<li><?php esc_html_e( 'This plugin does not add, edit, remove, or validate DNS records.', 'viazen-mailersend-smtp' ); ?></li>
			<li><?php esc_html_e( 'Keep other SMTP plugins, including the official MailerSend WordPress plugin, deactivated so they cannot configure the same PHPMailer instance.', 'viazen-mailersend-smtp' ); ?></li>
		</ul>
		<?php
	}

	/**
	 * Handles a test message through the normal wp_mail() path.
	 *
	 * @return void
	 */
	public static function handle_send_test(): void {
		self::require_settings_access();
		check_admin_referer( self::TEST_NONCE_ACTION );

		$recipient = isset( $_POST['recipient_email'] ) && is_string( $_POST['recipient_email'] )
			? sanitize_email( wp_unslash( $_POST['recipient_email'] ) )
			: '';
		if ( ! is_email( $recipient ) ) {
			self::redirect_with_notice( 'invalid-recipient' );
		}

		$subject = __( 'SMTP Connector for MailerSend test email', 'viazen-mailersend-smtp' );
		$message = __( 'This test email was sent through WordPress wp_mail() using the SMTP Connector for MailerSend plugin.', 'viazen-mailersend-smtp' );
		$sent    = wp_mail(
			$recipient,
			$subject,
			$message,
			array( 'Content-Type: text/plain; charset=UTF-8' )
		);

		self::redirect_with_notice( $sent ? 'test-success' : 'test-failure' );
	}

	/**
	 * Deletes the latest diagnostic result after nonce and capability checks.
	 *
	 * @return void
	 */
	public static function handle_clear_diagnostic(): void {
		self::require_settings_access();
		check_admin_referer( self::CLEAR_NONCE_ACTION );
		delete_option( self::OPTION_DIAGNOSTIC );
		self::redirect_with_notice( 'diagnostic-cleared' );
	}

	/**
	 * Records the latest successful wp_mail() result.
	 *
	 * @param array<string, mixed> $mail_data Successful mail data from core.
	 * @return void
	 */
	public static function record_success( array $mail_data ): void {
		self::store_diagnostic( 'success', $mail_data, '' );
	}

	/**
	 * Records a redacted error message and only safe mail metadata.
	 *
	 * WP_Error data can contain the full mail payload, so it is used only to
	 * extract recipient and subject and is never stored wholesale.
	 *
	 * @param WP_Error $error Mail failure from WordPress.
	 * @return void
	 */
	public static function record_failure( WP_Error $error ): void {
		$mail_data = $error->get_error_data();
		$mail_data = is_array( $mail_data ) ? $mail_data : array();

		self::store_diagnostic( 'failure', $mail_data, $error->get_error_message() );
	}

	/**
	 * Renders the most recent diagnostic result and clear button.
	 *
	 * @return void
	 */
	private static function render_diagnostic(): void {
		$diagnostic = get_option( self::OPTION_DIAGNOSTIC, array() );

		echo '<h2>' . esc_html__( 'Latest diagnostic result', 'viazen-mailersend-smtp' ) . '</h2>';

		if ( ! is_array( $diagnostic ) || empty( $diagnostic['status'] ) ) {
			echo '<p>' . esc_html__( 'No mail result has been recorded.', 'viazen-mailersend-smtp' ) . '</p>';
			return;
		}

		$stored_status   = is_string( $diagnostic['status'] ) ? $diagnostic['status'] : 'failure';
		$status          = 'success' === $stored_status ? __( 'Success', 'viazen-mailersend-smtp' ) : __( 'Failure', 'viazen-mailersend-smtp' );
		$category        = isset( $diagnostic['category'] ) && is_string( $diagnostic['category'] ) ? sanitize_key( $diagnostic['category'] ) : 'general-failure';
		$meaning         = self::get_diagnostic_category_label( $category );
		$timestamp_value = $diagnostic['timestamp'] ?? 0;
		$timestamp       = is_int( $timestamp_value ) || is_string( $timestamp_value ) ? absint( $timestamp_value ) : 0;
		$date_format     = get_option( 'date_format', 'F j, Y' );
		$date_format     = is_string( $date_format ) ? $date_format : 'F j, Y';
		$time_format     = get_option( 'time_format', 'g:i a' );
		$time_format     = is_string( $time_format ) ? $time_format : 'g:i a';
		$formatted_date  = $timestamp ? wp_date( $date_format . ' ' . $time_format, $timestamp, wp_timezone() ) : '';
		$date            = is_string( $formatted_date ) ? $formatted_date : '';
		$recipient       = isset( $diagnostic['recipient'] ) && is_string( $diagnostic['recipient'] ) ? $diagnostic['recipient'] : '';
		$subject         = isset( $diagnostic['subject'] ) && is_string( $diagnostic['subject'] ) ? $diagnostic['subject'] : '';
		$error           = isset( $diagnostic['error'] ) && is_string( $diagnostic['error'] ) ? $diagnostic['error'] : '';
		?>
		<table class="widefat striped" role="presentation">
			<tbody>
				<tr><th scope="row"><?php esc_html_e( 'Result', 'viazen-mailersend-smtp' ); ?></th><td><?php echo esc_html( $status ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Meaning', 'viazen-mailersend-smtp' ); ?></th><td><?php echo esc_html( $meaning ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Date and time', 'viazen-mailersend-smtp' ); ?></th><td><?php echo esc_html( $date ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Recipient', 'viazen-mailersend-smtp' ); ?></th><td><?php echo esc_html( $recipient ); ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Subject', 'viazen-mailersend-smtp' ); ?></th><td><?php echo esc_html( $subject ); ?></td></tr>
				<?php if ( 'failure' === $stored_status && '' !== $error ) : ?>
					<tr><th scope="row"><?php esc_html_e( 'Error', 'viazen-mailersend-smtp' ); ?></th><td><?php echo esc_html( $error ); ?></td></tr>
				<?php endif; ?>
			</tbody>
		</table>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
			<input type="hidden" name="action" value="viazen_mailersend_smtp_clear_diagnostic">
			<?php wp_nonce_field( self::CLEAR_NONCE_ACTION ); ?>
			<?php submit_button( esc_html__( 'Clear diagnostic result', 'viazen-mailersend-smtp' ), 'secondary' ); ?>
		</form>
		<?php
	}

	/**
	 * Stores only the diagnostic fields explicitly allowed by the plugin.
	 *
	 * @param string       $status    Success or failure.
	 * @param array<mixed> $mail_data Selected wp_mail() metadata source.
	 * @param string       $error     Failure message, if available.
	 * @return void
	 */
	private static function store_diagnostic( string $status, array $mail_data, string $error ): void {
		$category   = 'success' === $status ? 'transport-accepted' : self::classify_failure( $error );
		$diagnostic = array(
			'status'    => 'success' === $status ? 'success' : 'failure',
			'category'  => $category,
			'timestamp' => time(),
			'recipient' => self::sanitize_recipients( $mail_data['to'] ?? '' ),
			'subject'   => self::limit_text( sanitize_text_field( isset( $mail_data['subject'] ) && is_string( $mail_data['subject'] ) ? $mail_data['subject'] : '' ), 500 ),
			'error'     => 'failure' === $status ? self::sanitize_error_message( $error ) : '',
		);

		update_option( self::OPTION_DIAGNOSTIC, $diagnostic, false );
	}

	/**
	 * Classifies a mail failure using only WordPress's available error text.
	 *
	 * @param string $message Raw failure message.
	 * @return string
	 */
	private static function classify_failure( string $message ): string {
		$message = strtolower( $message );

		if ( preg_match( '/authent(?:icate|ication)|smtp auth|\b535\b|username and password/', $message ) ) {
			return 'authentication-failure';
		}

		if ( preg_match( '/invalid[^.]*from|from[^.]*invalid|invalid address.*from|setfrom/', $message ) ) {
			return 'invalid-from';
		}

		if ( preg_match( '/could not connect|connect\(\) failed|connection (?:failed|refused|timed out)|timeout|timed out|getaddrinfo|network is unreachable|failed to open stream/', $message ) ) {
			return 'connection-failure';
		}

		if ( preg_match( '/rejected|denied|not accepted|data not accepted|\b(?:421|450|451|452|550|551|552|553|554)\b/', $message ) ) {
			return 'transport-rejection';
		}

		return 'general-failure';
	}

	/**
	 * Returns careful wording for a diagnostic category.
	 *
	 * @param string $category Stored category key.
	 * @return string
	 */
	private static function get_diagnostic_category_label( string $category ): string {
		$labels = array(
			'transport-accepted'     => __( 'WordPress handed the message to the configured mail transport successfully. This does not prove final inbox delivery.', 'viazen-mailersend-smtp' ),
			'authentication-failure' => __( 'SMTP authentication failure.', 'viazen-mailersend-smtp' ),
			'connection-failure'     => __( 'SMTP connection failure.', 'viazen-mailersend-smtp' ),
			'invalid-from'           => __( 'Invalid From address.', 'viazen-mailersend-smtp' ),
			'transport-rejection'    => __( 'The configured mail transport rejected the message.', 'viazen-mailersend-smtp' ),
			'general-failure'        => __( 'General wp_mail() failure.', 'viazen-mailersend-smtp' ),
		);

		return $labels[ $category ] ?? $labels['general-failure'];
	}

	/**
	 * Returns active plugins known to configure SMTP or mail routing.
	 *
	 * @return string[]
	 */
	private static function get_active_mail_plugin_conflicts(): array {
		$active_plugins = get_option( 'active_plugins', array() );
		$active_plugins = is_array( $active_plugins ) ? $active_plugins : array();
		$network_active = is_multisite() ? get_site_option( 'active_sitewide_plugins', array() ) : array();
		$network_active = is_array( $network_active ) ? $network_active : array();
		$conflicts      = array();

		foreach ( self::KNOWN_MAIL_PLUGINS as $plugin_file => $plugin_name ) {
			if ( in_array( $plugin_file, $active_plugins, true ) || isset( $network_active[ $plugin_file ] ) ) {
				$conflicts[] = $plugin_name;
			}
		}

		return $conflicts;
	}

	/**
	 * Sanitizes recipients without retaining the rest of the mail payload.
	 *
	 * @param mixed $recipients Recipient string or array.
	 * @return string
	 */
	private static function sanitize_recipients( $recipients ): string {
		if ( is_string( $recipients ) ) {
			$recipients = explode( ',', $recipients );
		}

		if ( ! is_array( $recipients ) ) {
			return '';
		}

		$clean = array();
		foreach ( array_slice( $recipients, 0, 20 ) as $recipient ) {
			if ( ! is_scalar( $recipient ) ) {
				continue;
			}

			$value = self::limit_text( sanitize_text_field( (string) $recipient ), 320 );
			if ( '' !== $value ) {
				$clean[] = $value;
			}
		}

		return implode( ', ', $clean );
	}

	/**
	 * Redacts saved credentials and credential-shaped values from an error.
	 *
	 * @param string $message Raw WP_Error message.
	 * @return string
	 */
	private static function sanitize_error_message( string $message ): string {
		$message  = sanitize_text_field( $message );
		$settings = self::get_settings();

		foreach ( array( $settings['smtp_username'], $settings['smtp_password'] ) as $credential ) {
			if ( '' === $credential ) {
				continue;
			}

			$variants = array_unique(
				array(
					$credential,
					rawurlencode( $credential ),
				)
			);

			$message = str_replace( $variants, '[redacted]', $message );
		}

		$redacted = preg_replace(
			'/\b(password|passwd|pwd|username|user)\s*[:=]\s*("[^"]*"|\'[^\']*\'|[^\s;,]+)/i',
			'$1=[redacted]',
			$message
		);

		return self::limit_text( is_string( $redacted ) ? $redacted : '', 1000 );
	}

	/**
	 * Enforces the settings-page capability for custom admin actions.
	 *
	 * @return void
	 */
	private static function require_settings_access(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage these settings.', 'viazen-mailersend-smtp' ) );
		}
	}

	/**
	 * Redirects back to the settings page with a signed notice code only.
	 *
	 * @param string $notice Notice code from a fixed allowlist.
	 * @return never
	 */
	private static function redirect_with_notice( string $notice ) {
		$url = add_query_arg(
			array(
				'page'                            => self::PAGE_SLUG,
				'viazen_mailersend_smtp_notice'   => sanitize_key( $notice ),
				'viazen_mailersend_smtp_notice_n' => wp_create_nonce( self::NOTICE_NONCE_ACTION ),
			),
			admin_url( 'options-general.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Displays a fixed action notice after verifying its URL nonce.
	 *
	 * @return void
	 */
	private static function render_action_notice(): void {
		if ( ! isset( $_GET['viazen_mailersend_smtp_notice'], $_GET['viazen_mailersend_smtp_notice_n'] ) ) {
			return;
		}

		if ( ! is_string( $_GET['viazen_mailersend_smtp_notice'] ) || ! is_string( $_GET['viazen_mailersend_smtp_notice_n'] ) ) {
			return;
		}

		$notice = sanitize_key( wp_unslash( $_GET['viazen_mailersend_smtp_notice'] ) );
		$nonce  = sanitize_text_field( wp_unslash( $_GET['viazen_mailersend_smtp_notice_n'] ) );
		if ( ! wp_verify_nonce( $nonce, self::NOTICE_NONCE_ACTION ) ) {
			return;
		}

		$notices = array(
			'invalid-recipient'  => array( 'error', __( 'Enter a valid recipient email address.', 'viazen-mailersend-smtp' ) ),
			'test-success'       => array( 'success', __( 'WordPress handed the test email to the configured mail transport successfully. This does not prove final inbox delivery.', 'viazen-mailersend-smtp' ) ),
			'test-failure'       => array( 'error', __( 'Test email failed. Review the latest diagnostic result below.', 'viazen-mailersend-smtp' ) ),
			'diagnostic-cleared' => array( 'success', __( 'Diagnostic result cleared.', 'viazen-mailersend-smtp' ) ),
		);

		if ( ! isset( $notices[ $notice ] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-%1$s"><p>%2$s</p></div>',
			esc_attr( $notices[ $notice ][0] ),
			esc_html( $notices[ $notice ][1] )
		);
	}

	/**
	 * Returns settings merged with safe defaults.
	 *
	 * @return array{smtp_username:string, smtp_password:string, from_email:string, from_name:string}
	 */
	private static function get_settings(): array {
		$stored  = get_option( self::OPTION_SETTINGS, array() );
		$stored  = is_array( $stored ) ? $stored : array();
		$default = self::get_default_settings();

		return array(
			'smtp_username' => isset( $stored['smtp_username'] ) && is_string( $stored['smtp_username'] ) ? $stored['smtp_username'] : $default['smtp_username'],
			'smtp_password' => isset( $stored['smtp_password'] ) && is_string( $stored['smtp_password'] ) ? $stored['smtp_password'] : $default['smtp_password'],
			'from_email'    => isset( $stored['from_email'] ) && is_string( $stored['from_email'] ) ? $stored['from_email'] : $default['from_email'],
			'from_name'     => isset( $stored['from_name'] ) && is_string( $stored['from_name'] ) ? $stored['from_name'] : $default['from_name'],
		);
	}

	/**
	 * Returns initial settings without credentials.
	 *
	 * @return array{smtp_username:string, smtp_password:string, from_email:string, from_name:string}
	 */
	private static function get_default_settings(): array {
		$admin_email = get_option( 'admin_email', '' );
		$admin_email = is_string( $admin_email ) ? sanitize_email( $admin_email ) : '';

		return array(
			'smtp_username' => '',
			'smtp_password' => '',
			'from_email'    => is_email( $admin_email ) ? $admin_email : '',
			'from_name'     => sanitize_text_field( (string) get_bloginfo( 'name' ) ),
		);
	}

	/**
	 * Limits stored metadata without requiring a multibyte extension.
	 *
	 * @param string $value    Text to limit.
	 * @param int    $length   Maximum byte length.
	 * @param bool   $add_mark Whether to add an ellipsis when truncated.
	 * @return string
	 */
	private static function limit_text( string $value, int $length, bool $add_mark = true ): string {
		if ( strlen( $value ) <= $length ) {
			return $value;
		}

		return substr( $value, 0, $length ) . ( $add_mark ? '…' : '' );
	}
}

register_activation_hook( __FILE__, array( Plugin::class, 'activate' ) );
Plugin::register_hooks();
