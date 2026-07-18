#!/usr/bin/env bash
set -euo pipefail

project_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
plugin_slug="viazen-mailersend-smtp"
dist_dir="${project_root}/dist"
output_path="${1:-${dist_dir}/${plugin_slug}.zip}"
stage_dir="$(mktemp -d)"
source_date_epoch="${SOURCE_DATE_EPOCH:-$(git -C "${project_root}" log -1 --format=%ct 2>/dev/null || printf '315532800')}"

export TZ=UTC

cleanup() {
	rm -rf "${stage_dir}"
}
trap cleanup EXIT

mkdir -p "${stage_dir}/${plugin_slug}/assets/css" "$(dirname "${output_path}")"

for file in "${plugin_slug}.php" uninstall.php readme.txt LICENSE; do
	if [[ ! -f "${project_root}/${file}" ]]; then
		printf 'Required release file is missing: %s\n' "${file}" >&2
		exit 1
	fi
	cp "${project_root}/${file}" "${stage_dir}/${plugin_slug}/${file}"
done

cp "${project_root}/assets/css/admin-settings.css" "${stage_dir}/${plugin_slug}/assets/css/admin-settings.css"

find "${stage_dir}" -exec touch -h -d "@${source_date_epoch}" {} +

rm -f "${output_path}"
(
	cd "${stage_dir}"
	zip -q -X -9 "${output_path}" \
		"${plugin_slug}/" \
		"${plugin_slug}/assets/" \
		"${plugin_slug}/assets/css/" \
		"${plugin_slug}/assets/css/admin-settings.css" \
		"${plugin_slug}/LICENSE" \
		"${plugin_slug}/readme.txt" \
		"${plugin_slug}/uninstall.php" \
		"${plugin_slug}/${plugin_slug}.php"
)

unzip -q -t "${output_path}"

for entry in \
	"${plugin_slug}/${plugin_slug}.php" \
	"${plugin_slug}/uninstall.php" \
	"${plugin_slug}/readme.txt" \
	"${plugin_slug}/LICENSE" \
	"${plugin_slug}/assets/css/admin-settings.css"; do
	if ! unzip -Z1 "${output_path}" | grep -Fqx "${entry}"; then
		printf 'Release archive is missing: %s\n' "${entry}" >&2
		exit 1
	fi
done

if unzip -Z1 "${output_path}" | grep -Eq '(^|/)(vendor|tests|tools|scripts|\.github)(/|$)|composer\.(json|lock)$'; then
	printf 'Release archive contains development-only files.\n' >&2
	exit 1
fi

printf 'Built %s\n' "${output_path}"
