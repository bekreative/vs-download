#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SLUG="vs-download"
VERSION="$(grep -m1 "VS_DOWNLOAD_VERSION" "${ROOT}/${SLUG}.php" | sed "s/.*'\([^']*\)'.*/\1/")"
OUT="${ROOT}/dist/${SLUG}-${VERSION}.zip"

mkdir -p "${ROOT}/dist"
rm -f "${OUT}"

cd "${ROOT}"
if command -v composer >/dev/null 2>&1; then
  composer install --no-dev --optimize-autoloader --no-interaction
fi

zip -r "${OUT}" . \
  -x './.git/*' \
  -x './dist/*' \
  -x './.github/*' \
  -x './.phpunit.cache/*' \
  -x './vendor/verysimple/vs-core/vendor/*'

echo "Built ${OUT}"
