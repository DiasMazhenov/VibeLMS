#!/usr/bin/env bash

set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
package_version="${VIBELMS_PACKAGE_VERSION:-0.0.01}"
work_dir="$(mktemp -d)"
package_dir="${work_dir}/vibelms"
output_dir="${repo_root}/dist"
output_file="${output_dir}/vibelms-${package_version}.zip"

cleanup() {
	rm -rf "$work_dir"
}
trap cleanup EXIT

mkdir -p "$package_dir" "$output_dir"
git -C "$repo_root" archive --format=tar HEAD | tar -x -C "$package_dir"

composer install \
	--working-dir="$package_dir" \
	--no-dev \
	--no-scripts \
	--no-interaction \
	--prefer-dist \
	--no-progress

rm -rf \
	"$package_dir/.github" \
	"$package_dir/.source" \
	"$package_dir/.wordpress-org" \
	"$package_dir/tests" \
	"$package_dir/docs" \
	"$package_dir/.changelogs" \
	"$package_dir/context.md" \
	"$package_dir/AGENTS.md" \
	"$package_dir/CLAUDE.md" \
	"$package_dir/composer.json" \
	"$package_dir/package.json" \
	"$package_dir/package-lock.json" \
	"$package_dir/phpcs.xml" \
	"$package_dir/phpmd.xml" \
	"$package_dir/phpunit.xml.dist" \
	"$package_dir/webpack.config.js" \
	"$package_dir/gulpfile.js" \
	"$package_dir/lerna.json" \
	"$package_dir/packages"

rm -f "$output_file"
( cd "$work_dir" && zip -qr "$output_file" vibelms )

printf 'Built %s (%s)\n' "$output_file" "$(du -h "$output_file" | awk '{print $1}')"
