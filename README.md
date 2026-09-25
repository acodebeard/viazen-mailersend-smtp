# SMTP Connector for MailerSend

An independent WordPress plugin that routes every normal `wp_mail()` message
through MailerSend SMTP. That includes WordPress core messages, Contact Form 7,
and the plugin's own test message.

The plugin is intentionally focused: it configures the PHPMailer copy bundled
with WordPress and does not include an API client, SDK, mail library, telemetry,
advertising, or a general-purpose mail log. It can also store a Cloudflare
Turnstile site key and secret key for compatible forms without implementing or
calling Turnstile itself.

## Features

- Authenticated STARTTLS through `smtp.mailersend.net` on port 2525, MailerSend's supported alternative for hosts that block port 587.
- A single mail path for core, plugin, form, and test messages.
- A quick SMTP authentication check that sends no email and stores only valid or not valid.
- Configured From email and name override unsafe sender values.
- Reply-To, CC, BCC, HTML content, message bodies, and attachments remain intact.
- The saved SMTP password is never rendered back into admin HTML.
- One sanitized diagnostic result is retained, with no message body or attachment data.
- Administrators are warned when another known mail-routing plugin is active.
- Optional Turnstile credentials are exposed through narrow WordPress filters.
- The saved Turnstile secret is never rendered back into admin HTML.
- Deactivation preserves settings; uninstall removes this plugin's options.

## Requirements

- WordPress 6.5 or later
- PHP 8.1 or later
- A MailerSend account with working SMTP credentials
- A sender domain verified in MailerSend

## Install

For a published version, download `smtp-connector-for-mailersend.zip` from the
matching GitHub Release. In WordPress Admin, open **Plugins > Add New Plugin >
Upload Plugin**, upload the ZIP, and activate it.

Existing manual installations can use the same upload screen and select
**Replace current with uploaded**. The GitHub package retains the legacy
`viazen-mailersend-smtp` folder inside the ZIP for this in-place upgrade path.
The distribution filename, public WordPress.org slug, and translation text
domain are `smtp-connector-for-mailersend`. Internal option names remain
unchanged, so a normal replacement preserves saved credentials. Do not delete
the existing plugin before replacing it because uninstall intentionally removes
its options.

Then open **Settings > SMTP Connector for MailerSend**, enter the SMTP credentials and a
verified sender, save, run **Check credentials**, and use **Send Test Email**
before testing forms.

Compatible custom forms can read the optional Turnstile credentials through
the `viazen_mailersend_smtp_turnstile_site_key` and
`viazen_mailersend_smtp_turnstile_secret_key` filters. Both keys must be saved
before a form should enable Turnstile.

For Contact Form 7, use a verified-domain address in From and put the visitor's
address in Reply-To:

```text
From:
Website Name <forms@example.com>

Reply-To:
[your-email]
```

Only one SMTP or mail-routing plugin should be active at a time.

## Server-managed production-only SMTP (opt-in)

Existing installations remain settings-based unless
`VIAZEN_MAILERSEND_SMTP_MANAGED` is the Boolean `true`. To require managed mode,
define that constant in an MU-plugin or server configuration before regular
plugins load. Put the remaining values in private server configuration outside
Git, database backups, and the public document root, loaded by `wp-config.php`.

The configuration contract is:

| Constant | Required managed value |
| --- | --- |
| `VIAZEN_MAILERSEND_SMTP_MANAGED` | Boolean `true` |
| `WP_ENVIRONMENT_TYPE` | Explicit string `production`, or the same environment variable when the constant is absent |
| `VIAZEN_MAILERSEND_SMTP_ALLOW_SEND` | Boolean `true`; strings such as `"true"` and integer `1` do not enable mail |
| `VIAZEN_MAILERSEND_SMTP_USERNAME` | Nonempty SMTP username |
| `VIAZEN_MAILERSEND_SMTP_PASSWORD` | Nonempty SMTP password |
| `VIAZEN_MAILERSEND_SMTP_FROM_EMAIL` | Valid verified sender email |
| `VIAZEN_MAILERSEND_SMTP_FROM_NAME` | Nonempty sender name |

An absent, blank, malformed, or non-production environment blocks SMTP.
An explicit environment constant takes precedence over the environment
variable. `IS_DDEV_PROJECT=true` always blocks managed SMTP, even with production
configuration. Credential and sender values must be strings without control
characters. Do not set the opt-in or credentials on local/staging hosts.

Managed mode blocks both normal `wp_mail()` calls and direct connector
credential checks. A denied normal send returns Boolean `false` and records a
fixed policy diagnostic without message metadata; it never pretends delivery
succeeded. The admin page explains blocked policy separately from invalid
credentials, and server-managed fields are read-only. Imported credential-check
results are not shown as proof that the current server's credentials are valid.

Private values are resolved only for transport, not merged into stored options.
A managed settings save ignores submitted SMTP credentials and clears imported
SMTP username/password values from the saved settings, while preserving unrelated
Turnstile options. Enabling managed mode alone does not erase old secrets already
in the database or backups; protect those artifacts accordingly. No migration,
activation-time cleanup, cron deletion, or modification of the private server
configuration occurs.

### Host integration and limits

`Viazen\MailerSendSmtp\Plugin::managed_transport_allowed(): bool` reports whether
this connector may contact SMTP; in unmanaged mode it returns `true` to retain
legacy behavior. `Plugin::guard_wp_mail($pre)` is registered on `pre_wp_mail` at
`PHP_INT_MAX`, and `Plugin::configure_phpmailer` retains its existing hook.

A host MU guard should require managed mode and fail closed when the connector
is absent/incompatible. This plugin cannot protect mail while deactivated.
For an explicitly isolated local Mailpit integration, the MU guard may remove
the connector's `guard_wp_mail` and `configure_phpmailer` hooks and configure
the local capture transport itself. Keep direct credential checks blocked and
clearly distinguish local capture from external delivery.

Cron and CLI sends through `wp_mail()` use the same runtime policy. Do not rely
only on disabling scheduling: imported jobs may still run. This is an
application guard for WordPress mail and this connector, not an operating-system
egress firewall. Other plugins that bypass `wp_mail()`, direct sockets, HTTP mail
APIs, or native PHP `mail()` require separate controls.

## Diagnostics

The settings page stores and displays only the latest WordPress mail result:

- successful handoff to the configured transport;
- SMTP authentication failure;
- SMTP connection failure;
- invalid From address;
- transport rejection; or
- general `wp_mail()` failure.

A successful `wp_mail()` result does not prove final inbox delivery. MailerSend
and recipient services can still defer, filter, bounce, or reject a message
later.

## Development

```bash
composer install --no-interaction --prefer-dist --no-scripts --no-plugins
composer check
```

PHPStan runs at level 10 as part of `composer check`; the project does not use
a PHPStan baseline or ignored findings.

`composer check` also builds and verifies the installable archive at
`dist/smtp-connector-for-mailersend.zip`; do not run a second build against the
same output path. The archive is not committed to the source repository.

Packaging uses an explicit release-file list and refuses to overwrite an
existing archive. For another build, choose a new filename:

```bash
scripts/build-release.sh dist/smtp-connector-for-mailersend-review.zip
```

The script retains its uniquely named temporary build directory for inspection
on success or failure and prints its location. Set `MAILERSEND_BUILD_TMPDIR`
to an existing dedicated directory to control where these build files go.
Otherwise it uses `TMPDIR` or the system temporary directory. Local build
directories are not automatically removed; CI runner disposal handles CI files.

The destructive lifecycle and integration suite is intended only for a local
WordPress sandbox:

```bash
WP_PATH=/opt/lampp/htdocs/sandbox scripts/test-sandbox.sh
```

It installs the ZIP normally, without a symlink, and verifies Plugin Check,
in-place credential preservation, PHPMailer configuration, headers,
diagnostics, deactivation, and uninstall.

## Independent project and trademarks

This project is independently developed and maintained by
[@acodebeard](https://github.com/acodebeard). It is not the official MailerSend
WordPress plugin and is not affiliated with, endorsed by, sponsored by, or
supported by MailerSend, Inc.

MailerSend is a product of MailerSend, Inc. The MailerSend name is used only to
identify the service with which this plugin interoperates. No MailerSend logo or
brand artwork is included.

For MailerSend accounts, DNS authentication, service availability, billing, or
delivery support, contact MailerSend. For this plugin's behavior, use this
repository's GitHub Issues.

## Security

Please report vulnerabilities privately as described in
[SECURITY.md](SECURITY.md). Do not include SMTP credentials in reports.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
