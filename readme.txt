=== SMTP Connector for MailerSend ===
Contributors: acodebeard
Donate link: https://paypal.me/acodebeard
Tags: smtp, email, mailersend, contact form 7
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Routes all WordPress wp_mail() messages through authenticated MailerSend SMTP.

== Description ==

SMTP Connector for MailerSend is a deliberately small, independent SMTP transport.
It configures the PHPMailer copy already included with WordPress and does not
use the MailerSend API, external libraries, telemetry, advertising, or remote
services other than the configured SMTP connection used to send mail.

All messages sent through wp_mail(), including Contact Form 7 mail, use:

* smtp.mailersend.net
* Port 587
* STARTTLS
* Authenticated SMTP
* A 20-second connection timeout

The configured From email and From name override values supplied by other
plugins. Reply-To, CC, BCC, HTML content type, message content, and attachments
are not changed.

This is an independent community project maintained by acodebeard. It is not
the official MailerSend WordPress plugin and is not affiliated with, endorsed
by, sponsored by, or supported by MailerSend, Inc. MailerSend is a product and
trademark of MailerSend, Inc.; the name is used only to identify compatibility.

== Installation ==

1. Upload `viazen-mailersend-smtp.zip` through Plugins > Add New Plugin > Upload Plugin.
2. Activate SMTP Connector for MailerSend.
3. Open Settings > SMTP Connector for MailerSend.
4. Enter the MailerSend SMTP username and password.
5. Enter a verified From email and the desired From name, then save.
6. Send a test email from the same settings page.

For Contact Form 7, use a sender on the MailerSend-verified domain and keep the
visitor address in Reply-To. For example:

`From: Website Name <forms@example.com>`

`Reply-To: [your-email]`

Keep DNS records required by MailerSend and by your existing email provider while those services remain in use.
This plugin does not add, edit, remove, or validate DNS records.
Keep other SMTP plugins, including the official MailerSend WordPress plugin, deactivated so they cannot configure the same PHPMailer instance.

Running multiple SMTP plugins can cause more than one plugin to configure the
same PHPMailer instance, producing order-dependent and unpredictable results.

The saved SMTP username remains visible and editable. The saved password never
enters the settings-page HTML; a fixed six-character mask shows that it exists,
and a small disclosure reveals a blank replacement field when needed. Leaving
the replacement field blank preserves the saved password.

The credential check connects to MailerSend and authenticates without sending
an email. It stores only valid or not valid. It never stores or displays the
SMTP response or an error transcript.

== Hooks used ==

* `phpmailer_init` configures WordPress PHPMailer for MailerSend SMTP.
* `wp_mail_from` forces the configured From email.
* `wp_mail_from_name` forces the configured From name.
* `wp_mail_failed` stores the latest redacted failure result.
* `wp_mail_succeeded` stores the latest success result.
* `admin_menu` adds Settings > SMTP Connector for MailerSend.
* `admin_init` registers fields through the WordPress Settings API.
* `admin_enqueue_scripts` loads the small stylesheet only on this plugin's settings page.
* `admin_notices` warns administrators when a known mail-routing plugin is also active.
* `admin_post_viazen_mailersend_smtp_send_test` securely handles test email requests.
* `admin_post_viazen_mailersend_smtp_check_credentials` securely checks saved SMTP credentials without sending email.
* `admin_post_viazen_mailersend_smtp_clear_diagnostic` securely clears the latest result.
* `admin_post_viazen_mailersend_smtp_dismiss_donation` securely saves a user's dismissal of the settings-page support link.

== Manual testing ==

= SMTP credential check =

1. Save valid MailerSend SMTP credentials.
2. Select Check credentials and confirm the status changes to Valid.
3. Save an intentionally incorrect password, check again, and confirm the status changes to Not valid.
4. Confirm the page source and stored option contain no SMTP response, username, or password.
5. Restore and recheck the valid credentials.

= Plugin test email =

1. Save valid MailerSend SMTP credentials and a verified sender.
2. Enter a recipient under Send Test Email and select Send Test Email.
3. Confirm the success notice, delivery, sender, and latest diagnostic result.

= Standard wp_mail() =

1. Trigger a normal WordPress message such as a password-reset email.
2. Confirm delivery through MailerSend and confirm the configured From address and name.

= Contact Form 7 =

1. Configure a Contact Form 7 form with the plugin's configured From address.
2. Submit the public form.
3. Confirm the message content is unchanged and delivery uses MailerSend SMTP.

= Reply-To behavior =

1. Configure Contact Form 7 to place the visitor email in Reply-To, not From.
2. Submit the form and reply to the received message.
3. Confirm the reply targets the visitor while From remains the configured sender.

= HTML email =

1. Send an HTML message through wp_mail() with `Content-Type: text/html; charset=UTF-8`.
2. Confirm it renders as HTML and the configured sender remains in use.

= Attachment sending =

1. Send a small test attachment using the wp_mail() attachments argument or Contact Form 7.
2. Confirm the attachment arrives intact and is not recorded in diagnostics.

= Failed SMTP authentication =

1. Temporarily save an intentionally incorrect SMTP password.
2. Send a test email and confirm a failure notice appears.
3. Confirm the latest diagnostic shows only status, date/time, recipient, subject,
   and a redacted error message. Inspect the page source and confirm no SMTP
   username or password appears.
4. Restore the valid password and send another successful test.

= Invalid From address =

1. Try to save an invalid From email.
2. Confirm WordPress rejects it with a validation message and preserves the last valid address.

= Mail-plugin conflict warning =

1. In a non-production test site, temporarily activate another SMTP plugin while this plugin remains active.
2. Confirm administrators see a warning explaining that both plugins can configure PHPMailer.
3. Deactivate the official plugin before sending mail.

= Deactivation and uninstall =

1. Deactivate and reactivate this plugin, then confirm its settings remain.
2. Delete the plugin through the WordPress Plugins screen.
3. Confirm this plugin's settings and latest diagnostic are removed. DNS records are not changed.

== Diagnostics and privacy ==

Only the most recent result is stored: success or failure, a diagnostic category,
timestamp, recipient, subject, and a redacted error message when available.
Failure categories distinguish SMTP authentication, SMTP connection, invalid
From address, transport rejection, and a general wp_mail() failure as clearly
as the available WordPress error permits. A success means WordPress handed the
message to the configured transport; it does not prove final inbox delivery. Message bodies, mail
headers, credentials, attachments, attachment contents, and PHPMailer debug
transcripts are never stored by this plugin.

Use Clear diagnostic result on the settings page to delete the stored result.
Deactivation preserves plugin settings. Deleting the plugin through WordPress
removes its settings, credential-check status, and latest diagnostic result.

== Changelog ==

= 1.0.1 =

* Added Contact Form 7 and DNS guidance.
* Added conservative warnings for conflicting SMTP plugins.
* Added clearer diagnostic categories and accurate successful-handoff wording.

= 1.0.0 =

* Initial release.
