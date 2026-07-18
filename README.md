# SMTP Connector for MailerSend

An independent WordPress plugin that routes every normal `wp_mail()` message
through MailerSend SMTP. That includes WordPress core messages, Contact Form 7,
and the plugin's own test message.

The plugin is intentionally focused: it configures the PHPMailer copy bundled
with WordPress and does not include an API client, SDK, mail library, telemetry,
advertising, or a general-purpose mail log.

## Features

- Authenticated STARTTLS through `smtp.mailersend.net` on port 587.
- A single mail path for core, plugin, form, and test messages.
- Configured From email and name override unsafe sender values.
- Reply-To, CC, BCC, HTML content, message bodies, and attachments remain intact.
- The saved SMTP password is never rendered back into admin HTML.
- One sanitized diagnostic result is retained, with no message body or attachment data.
- Administrators are warned when another known mail-routing plugin is active.
- Deactivation preserves settings; uninstall removes this plugin's options.

## Requirements

- WordPress 6.5 or later
- PHP 8.1 or later
- A MailerSend account with working SMTP credentials
- A sender domain verified in MailerSend

## Install

For a published version, download `viazen-mailersend-smtp.zip` from the matching
GitHub Release. In WordPress Admin, open **Plugins > Add New Plugin > Upload
Plugin**, upload the ZIP, and activate it.

Then open **Settings > SMTP Connector for MailerSend**, enter the SMTP credentials and a
verified sender, save, and use **Send Test Email** before testing forms.

For Contact Form 7, use a verified-domain address in From and put the visitor's
address in Reply-To:

```text
From:
Website Name <forms@example.com>

Reply-To:
[your-email]
```

Only one SMTP or mail-routing plugin should be active at a time.

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
composer install
composer check
scripts/build-release.sh
```

PHPStan runs at level 10 as part of `composer check`; the project does not use
a PHPStan baseline or ignored findings.

The installable archive is written to `dist/viazen-mailersend-smtp.zip` and is
not committed to the source repository.

The destructive lifecycle and integration suite is intended only for a local
WordPress sandbox:

```bash
WP_PATH=/opt/lampp/htdocs/sandbox scripts/test-sandbox.sh
```

It installs the ZIP normally, without a symlink, and verifies Plugin Check,
PHPMailer configuration, headers, diagnostics, deactivation, and uninstall.

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
