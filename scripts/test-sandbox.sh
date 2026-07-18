#!/usr/bin/env bash
set -euo pipefail

project_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
plugin_slug="viazen-mailersend-smtp"
wp_bin="${WP_BIN:-wp}"
wp_path="${WP_PATH:-/opt/lampp/htdocs/sandbox}"
wp_user="${WP_USER:-}"
archive="${project_root}/dist/${plugin_slug}.zip"

if ! command -v "${wp_bin}" >/dev/null 2>&1; then
	printf 'WP-CLI was not found: %s\n' "${wp_bin}" >&2
	exit 1
fi

if [[ ! -f "${wp_path}/wp-config.php" ]]; then
	printf 'WordPress sandbox was not found: %s\n' "${wp_path}" >&2
	exit 1
fi

wp=( "${wp_bin}" "--path=${wp_path}" )

if [[ -z "${wp_user}" ]]; then
	wp_user="$("${wp[@]}" user list --role=administrator --field=ID | sed -n '1p')"
fi

if [[ -z "${wp_user}" ]]; then
	printf 'No administrator was found in the sandbox.\n' >&2
	exit 1
fi

restore_clean_plugin() {
	"${wp[@]}" plugin install "${archive}" --force --activate >/dev/null 2>&1 || true
	"${wp[@]}" eval-file "${project_root}/tests/wp-clean-state.php" >/dev/null 2>&1 || true
}

run_plugin_check() {
	local output
	output="$("${wp[@]}" plugin check "${plugin_slug}" --mode=new --format=strict-table 2>&1)"
	printf '%s\n' "${output}"
	if grep -Eq '(^|[[:space:]])ERROR([[:space:]]|$)' <<< "${output}"; then
		printf 'WordPress Plugin Check reported an error.\n' >&2
		exit 1
	fi
}

trap restore_clean_plugin EXIT

"${project_root}/scripts/build-release.sh"
"${wp[@]}" plugin install "${archive}" --force --activate

plugin_dir="$("${wp[@]}" eval 'echo WP_PLUGIN_DIR;')/${plugin_slug}"
if [[ -L "${plugin_dir}" ]]; then
	printf 'Sandbox install unexpectedly created a symlink.\n' >&2
	exit 1
fi

"${wp[@]}" eval-file "${project_root}/tests/wp-integration.php" "--user=${wp_user}"

if ! "${wp[@]}" plugin is-installed plugin-check; then
	"${wp[@]}" plugin install plugin-check --activate
else
	"${wp[@]}" plugin activate plugin-check >/dev/null 2>&1 || true
fi

run_plugin_check

"${wp[@]}" eval-file "${project_root}/tests/wp-lifecycle-setup.php"
"${wp[@]}" plugin deactivate "${plugin_slug}"
"${wp[@]}" eval-file "${project_root}/tests/wp-lifecycle-preserved.php"
"${wp[@]}" plugin activate "${plugin_slug}"
"${wp[@]}" plugin deactivate "${plugin_slug}"
"${wp[@]}" plugin uninstall "${plugin_slug}"
"${wp[@]}" eval-file "${project_root}/tests/wp-lifecycle-removed.php"

restore_clean_plugin

run_plugin_check
"${wp[@]}" plugin get "${plugin_slug}" --fields=name,status,version --format=table

for file in "${plugin_slug}.php" uninstall.php readme.txt LICENSE; do
	cmp "${project_root}/${file}" "${plugin_dir}/${file}"
done

trap - EXIT
printf 'Sandbox integration, lifecycle, Plugin Check, and ZIP installation passed.\n'
