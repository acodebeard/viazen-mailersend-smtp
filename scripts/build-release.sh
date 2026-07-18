#!/usr/bin/env bash
set -euo pipefail

project_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
plugin_slug="viazen-mailersend-smtp"
dist_dir="${project_root}/dist"
output_path="${1:-${dist_dir}/${plugin_slug}.zip}"
stage_dir="$(mktemp -d)"

cleanup() {
	rm -rf "${stage_dir}"
}
trap cleanup EXIT

mkdir -p "${stage_dir}/${plugin_slug}" "$(dirname "${output_path}")"

for file in "${plugin_slug}.php" uninstall.php readme.txt LICENSE; do
	if [[ ! -f "${project_root}/${file}" ]]; then
		printf 'Required release file is missing: %s\n' "${file}" >&2
		exit 1
	fi
	cp "${project_root}/${file}" "${stage_dir}/${plugin_slug}/${file}"
done

rm -f "${output_path}"
(
	cd "${stage_dir}"
	zip -q -X -r -9 "${output_path}" "${plugin_slug}"
)

unzip -q -t "${output_path}"

for entry in \
	"${plugin_slug}/${plugin_slug}.php" \
	"${plugin_slug}/uninstall.php" \
	"${plugin_slug}/readme.txt" \
	"${plugin_slug}/LICENSE"; do
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
