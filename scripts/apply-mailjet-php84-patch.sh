#!/usr/bin/env sh
set -e
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PATCH="$ROOT/patches/mailjet-client-php84-constructor.patch"
TARGET="$ROOT/vendor/mailjet/mailjet-apiv3-php"
CLIENT="$TARGET/src/Mailjet/Client.php"

[ -f "$PATCH" ] || exit 0
[ -f "$CLIENT" ] || exit 0

# Already patched for PHP 8.4 implicit nullable deprecation
if grep -q 'function __construct(string $key, ?string $secret = null' "$CLIENT" 2>/dev/null; then
  exit 0
fi

patch -p1 --no-backup-if-mismatch --silent -d "$TARGET" < "$PATCH"
