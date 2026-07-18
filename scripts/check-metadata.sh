#!/usr/bin/env bash
set -euo pipefail

project_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
plugin_file="${project_root}/viazen-mailersend-smtp.php"
readme_file="${project_root}/readme.txt"
changelog_file="${project_root}/CHANGELOG.md"
admin_css_file="${project_root}/assets/css/admin-settings.css"
published_title='SMTP Connector for MailerSend'
published_slug='smtp-connector-for-mailersend'

plugin_version="$(sed -n 's/^ \* Version:[[:space:]]*//p' "${plugin_file}")"
stable_tag="$(sed -n 's/^Stable tag:[[:space:]]*//p' "${readme_file}")"
plugin_title="$(sed -n 's/^ \* Plugin Name:[[:space:]]*//p' "${plugin_file}")"
text_domain="$(sed -n 's/^ \* Text Domain:[[:space:]]*//p' "${plugin_file}")"

if [[ -z "${plugin_version}" || "${plugin_version}" != "${stable_tag}" ]]; then
	printf 'Plugin Version and readme Stable tag do not match.\n' >&2
	exit 1
fi

if [[ "${plugin_title}" != "${published_title}" ]] || \
	! grep -Fqx "=== ${published_title} ===" "${readme_file}" || \
	! grep -Fqx "# ${published_title}" "${project_root}/README.md"; then
	printf 'The publishable plugin title is missing or inconsistent.\n' >&2
	exit 1
fi

if [[ "${text_domain}" != "${published_slug}" ]] || \
	grep -Fq ", 'viazen-mailersend-smtp' )" "${plugin_file}"; then
	printf 'The translation text domain does not match the WordPress.org slug.\n' >&2
	exit 1
fi

if grep -RIFq 'Viazen MailerSend SMTP' \
	--exclude=check-metadata.sh --exclude-dir=.git --exclude-dir=dist --exclude-dir=vendor "${project_root}"; then
	printf 'The retired display title is still present.\n' >&2
	exit 1
fi

if ! grep -Fqx "## [${plugin_version}] - 2026-07-17" "${changelog_file}"; then
	printf 'CHANGELOG.md does not contain the current version.\n' >&2
	exit 1
fi

if grep -RIEq 'Said & Dunn|saidanddunnmedia\.com|WordPress\.com email-forwarding' \
	--exclude=check-metadata.sh --exclude-dir=.git --exclude-dir=dist --exclude-dir=vendor "${project_root}"; then
	printf 'Client-specific content was found in the standalone project.\n' >&2
	exit 1
fi

if ! grep -Fq 'not affiliated with, endorsed' "${project_root}/README.md" || \
	! grep -Fq 'not affiliated with, endorsed' "${readme_file}"; then
	printf 'Independent-project attribution is missing.\n' >&2
	exit 1
fi

if ! grep -Fqx 'Donate link: https://paypal.me/acodebeard' "${readme_file}" || \
	! grep -Fq "private const DONATE_URL = 'https://paypal.me/acodebeard';" "${plugin_file}"; then
	printf 'The approved donation link is missing or inconsistent.\n' >&2
	exit 1
fi

if ! grep -Fqx $'\tdisplay: inline-block;' "${admin_css_file}" || \
	! grep -Fqx $'\tmargin: 0.25rem 0;' "${admin_css_file}"; then
	printf 'The scoped admin button-link styles are missing.\n' >&2
	exit 1
fi

required_readme_lines=(
	'Keep DNS records required by MailerSend and by your existing email provider while those services remain in use.'
	'This plugin does not add, edit, remove, or validate DNS records.'
	'Keep other SMTP plugins, including the official MailerSend WordPress plugin, deactivated so they cannot configure the same PHPMailer instance.'
)
for required_line in "${required_readme_lines[@]}"; do
	if ! grep -Fqx "${required_line}" "${readme_file}"; then
		printf 'Required MailerSend configuration guidance is missing from readme.txt.\n' >&2
		exit 1
	fi
	if grep -Fq "${required_line}" "${plugin_file}"; then
		printf 'Readme-only MailerSend configuration guidance was found in the admin UI.\n' >&2
		exit 1
	fi
done

printf 'Version, text-domain, standalone-content, attribution, and donation checks passed.\n'
