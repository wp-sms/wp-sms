#!/bin/bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
FIXTURE_DIR="$(mktemp -d "${TMPDIR:-/tmp}/wp-sms-build-zip-test-XXXXXX")"
CHECKOUT_DIR="$FIXTURE_DIR/custom-checkout"

cleanup() {
    rm -rf "$FIXTURE_DIR"
}
trap cleanup EXIT

mkdir -p "$CHECKOUT_DIR/bin" "$CHECKOUT_DIR/public/js"
cp "$ROOT_DIR/bin/build-zip.sh" "$CHECKOUT_DIR/bin/build-zip.sh"
cp "$ROOT_DIR/.distignore" "$ROOT_DIR/composer.json" "$ROOT_DIR/package.json" "$ROOT_DIR/wp-sms.php" "$CHECKOUT_DIR/"
cp "$ROOT_DIR/public/js/frontend.min.js" "$CHECKOUT_DIR/public/js/frontend.min.js"

# Keep this regression focused on archive naming and layout, not dependency installs/builds.
composer() { return 0; }
pnpm() { return 0; }
wp() { return 0; }
git() { printf 'aria/issue-525\n'; }
export -f composer pnpm wp git

bash "$CHECKOUT_DIR/bin/build-zip.sh" >/dev/null

ARCHIVE="$CHECKOUT_DIR/dist/wp-sms-7.2.7-aria-issue-525.zip"
if [ ! -f "$ARCHIVE" ]; then
    printf 'Expected archive was not created: %s\n' "$ARCHIVE" >&2
    exit 1
fi

ENTRIES="$(unzip -Z1 "$ARCHIVE")"

case "$ENTRIES" in
    *$'wp-sms/\n'*|wp-sms/) ;;
    *)
        printf 'Archive does not use the canonical wp-sms root directory:\n%s\n' "$ENTRIES" >&2
        exit 1
        ;;
esac

case "$ENTRIES" in
    *$'wp-sms/wp-sms.php\n'*|*wp-sms/wp-sms.php) ;;
    *)
        printf 'Archive does not contain the canonical plugin entry file:\n%s\n' "$ENTRIES" >&2
        exit 1
        ;;
esac

case "$ENTRIES" in
    *$'wp-sms/public/js/frontend.min.js\n'*|*wp-sms/public/js/frontend.min.js) ;;
    *)
        printf 'Archive does not contain the generated frontend bundle:\n%s\n' "$ENTRIES" >&2
        exit 1
        ;;
esac

if ! unzip -p "$ARCHIVE" wp-sms/public/js/frontend.min.js | grep -Eq 'wpsms-subscribe__message--error[^;]{0,200}\.find\("span"\)\.html\('; then
    printf 'Packaged frontend bundle does not render sanitized subscription errors as HTML.\n' >&2
    exit 1
fi

if [[ "$ENTRIES" == *"custom-checkout/"* ]]; then
    printf 'Archive leaked the checkout directory name:\n%s\n' "$ENTRIES" >&2
    exit 1
fi

printf 'build-zip archive layout: PASS\n'
