#!/bin/sh
# Builds build/vaccinations-<version>.tar.gz – the app as Nextcloud expects it
# in its apps directory: PHP code, data, translations and the built frontend,
# without sources, tests or development files.
# Usage: npm ci && npm run build && bin/package.sh
set -eu

cd "$(dirname "$0")/.."
version=$(sed -n 's:.*<version>\(.*\)</version>.*:\1:p' appinfo/info.xml)
[ -f js/vaccinations-main.mjs ] || { echo "Frontend not built – run 'npm run build' first" >&2; exit 1; }

rm -rf build
mkdir -p build/vaccinations
cp -r appinfo lib templates img l10n js css data LICENSE build/vaccinations/
# source maps are not needed at runtime
find build/vaccinations/js -name '*.map' -delete

tar -czf "build/vaccinations-$version.tar.gz" -C build vaccinations
echo "build/vaccinations-$version.tar.gz"
