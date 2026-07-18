#!/usr/bin/env bash
set -euo pipefail

project_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
plugin_file="${project_root}/viazen-mailersend-smtp.php"
readme_file="${project_root}/readme.txt"
changelog_file="${project_root}/CHANGELOG.md"

plugin_version="$(sed -n 's/^ \* Version:[[:space:]]*//p' "${plugin_file}")"
stable_tag="$(sed -n 's/^Stable tag:[[:space:]]*//p' "${readme_file}")"

if [[ -z "${plugin_version}" || "${plugin_version}" != "${stable_tag}" ]]; then
	printf 'Plugin Version and readme Stable tag do not match.\n' >&2
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

printf 'Version, standalone-content, and attribution checks passed.\n'
