#!/usr/bin/env bash
# Build an installable plugin zip: dist/revision-counter-<version>.zip
# The zip contains a single top-level "revision-counter/" folder.
set -euo pipefail

SLUG="revision-counter"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

VERSION="$(sed -n 's/^[ *]*Version:[[:space:]]*//p' "$SLUG.php" | head -n1 | tr -d '[:space:]')"
if [ -z "$VERSION" ]; then
	echo "Could not read Version from $SLUG.php" >&2
	exit 1
fi

if command -v php >/dev/null 2>&1; then
	php -l "$SLUG.php" >/dev/null
fi

# Files that ship in the plugin.
FILES=( "$SLUG.php" "readme.txt" "includes" )

STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT
mkdir -p "$STAGE/$SLUG" "$ROOT/dist"
cp -R "${FILES[@]}" "$STAGE/$SLUG/"

OUT="$ROOT/dist/$SLUG-$VERSION.zip"
rm -f "$OUT"
( cd "$STAGE" && zip -rqX "$OUT" "$SLUG" -x '*.DS_Store' )

echo "Built $OUT"
