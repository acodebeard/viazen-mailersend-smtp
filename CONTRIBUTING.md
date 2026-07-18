# Contributing

Contributions should keep the plugin small, focused, and compatible with the
normal WordPress `wp_mail()` path.

Before opening a pull request:

1. Do not include SMTP credentials or real message content in code, fixtures,
   screenshots, commits, or GitHub Issues.
2. Run `composer install` and `composer check`.
3. Build the package with `scripts/build-release.sh`.
4. For mail-path changes, run `scripts/test-sandbox.sh` against a disposable
   local WordPress installation.
5. Describe the behavior change and the checks performed.

Do not add an API client, third-party mail library, telemetry, advertising, or
arbitrary code-injection features without first discussing a change in scope.
