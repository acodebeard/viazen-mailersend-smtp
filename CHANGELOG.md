# Changelog

All notable changes to Viazen MailerSend SMTP are documented here.

## Unreleased

- Raised PHPStan analysis from level 6 to level 10 without suppressions or a baseline.
- Hardened request, option, and diagnostic handling against unexpected value types.
- Show the saved SMTP username and provide an accessible password-replacement disclosure without exposing the saved password.

## [1.0.1] - 2026-07-17

- Added Contact Form 7 sender and Reply-To guidance.
- Added conservative warnings for conflicting SMTP plugins.
- Added categorized diagnostics with accurate successful-handoff wording.
- Added a guarded WordPress uninstall routine.
- Clarified that the project is independent from MailerSend, Inc.

## [1.0.0] - 2026-07-17

- Added authenticated MailerSend SMTP transport for all `wp_mail()` messages.
- Added sender overrides, secure settings, test mail, and latest-result diagnostics.
