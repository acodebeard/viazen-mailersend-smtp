#!/usr/bin/env bash
set -euo pipefail

project_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
plugin_slug="viazen-mailersend-smtp"
package_slug="smtp-connector-for-mailersend"
dist_dir="${project_root}/dist"
output_path="${1:-${dist_dir}/${package_slug}.zip}"
build_tmp_root="${MAILERSEND_BUILD_TMPDIR:-${TMPDIR:-/tmp}}"
source_date_epoch="${SOURCE_DATE_EPOCH:-$(git -C "${project_root}" log -1 --format=%ct 2>/dev/null || printf '315532800')}"

export TZ=UTC

# A release contains only these files. Do not walk the working tree or include
# dependencies, test fixtures, or private local configuration.
release_files=(
	"${plugin_slug}.php"
	uninstall.php
	readme.txt
	LICENSE
	assets/css/admin-settings.css
)
package_dirs=(
	"${plugin_slug}"
	"${plugin_slug}/assets"
	"${plugin_slug}/assets/css"
)

if (( $# > 1 )); then
	printf 'Usage: %s [new-output.zip]\n' "${BASH_SOURCE[0]}" >&2
	exit 1
fi

# Resolve relative output paths before changing into the isolated build folder.
if [[ "${output_path}" != /* ]]; then
	output_path="${PWD}/${output_path}"
fi

# Never delete or update an older archive. This also rejects dangling symlinks.
# Choose a new explicit output filename when repeating a build.
if [[ -e "${output_path}" || -L "${output_path}" ]]; then
	printf 'Refusing to overwrite existing release output: %s\n' "${output_path}" >&2
	exit 1
fi

if [[ ! -d "${build_tmp_root}" ]]; then
	printf 'Build temporary directory does not exist: %s\n' "${build_tmp_root}" >&2
	exit 1
fi

for file in "${release_files[@]}"; do
	if [[ ! -f "${project_root}/${file}" || -L "${project_root}/${file}" ]]; then
		printf 'Required release file is missing or is a symlink: %s\n' "${file}" >&2
		exit 1
	fi
done

# Each build gets a fresh directory. Keep it on success and failure for review;
# do not install an automatic deletion trap. Local callers can select a
# dedicated temporary root; CI uses its disposable runner temporary directory.
stage_dir="$(mktemp -d "${build_tmp_root%/}/mailersend-build-XXXXXX")"
printf 'Retaining build files for inspection: %s\n' "${stage_dir}"
mkdir -p "${stage_dir}/${plugin_slug}/assets/css" "$(dirname "${output_path}")"

for file in "${release_files[@]}"; do
	cp --no-clobber "${project_root}/${file}" "${stage_dir}/${plugin_slug}/${file}"
done

# Normalize only the explicit package entries for reproducible permissions and
# timestamps. No recursive filesystem search or bulk command execution.
for dir in "${package_dirs[@]}"; do
	chmod 755 "${stage_dir}/${dir}"
	touch -h -d "@${source_date_epoch}" "${stage_dir}/${dir}"
done
for file in "${release_files[@]}"; do
	chmod 644 "${stage_dir}/${plugin_slug}/${file}"
	touch -h -d "@${source_date_epoch}" "${stage_dir}/${plugin_slug}/${file}"
done

# Recheck after staging rather than silently updating an archive another build
# may have produced in the meantime. Concurrent builds should use unique paths.
if [[ -e "${output_path}" || -L "${output_path}" ]]; then
	printf 'Refusing to overwrite existing release output: %s\n' "${output_path}" >&2
	exit 1
fi

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

for file in "${release_files[@]}"; do
	if ! unzip -Z1 "${output_path}" | grep -Fqx "${plugin_slug}/${file}"; then
		printf 'Release archive is missing: %s\n' "${plugin_slug}/${file}" >&2
		exit 1
	fi
done

if unzip -Z1 "${output_path}" | grep -Eq '(^|/)(vendor|tests|tools|scripts|\.github)(/|$)|composer\.(json|lock)$'; then
	printf 'Release archive contains development-only files.\n' >&2
	exit 1
fi

printf 'Built %s\n' "${output_path}"
