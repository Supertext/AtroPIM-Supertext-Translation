#!/bin/sh
# Copies the PHP Supertext Plugin Health (version reports to the Supertext Commando Center, the API
# key check result and the ping) from the Commando Center's GitHub repository into
# lib/supertext-plugin-health, next to the SDK's copy, with its namespace changed from
# Supertext\PluginHealth to SupertextTranslation\PluginHealth. The module ships this copy, so
# sites install nothing besides the module, and another package that requires
# supertext/plugin-health itself can never clash with it.
#
#   scripts/vendor-plugin-health.sh [commit-or-branch]   (default: main)
#   PLUGIN_HEALTH_DIR=/path/to/clone scripts/vendor-plugin-health.sh   (use a local clone as it is)
set -eu
REF="${1:-main}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

if [ -n "${PLUGIN_HEALTH_DIR:-}" ]; then
    SRC="$PLUGIN_HEALTH_DIR"
else
    git clone --quiet https://github.com/Supertext/Commando-Center-Supertext-Translation "$TMP/center"
    git -C "$TMP/center" checkout --quiet "$REF"
    SRC="$TMP/center"
fi
COMMIT="$(git -C "$SRC" rev-parse HEAD)"
VERSION="$(sed -n "s/.*PLUGIN_HEALTH_VERSION = '\\([^']*\\)'.*/\\1/p" "$SRC/plugin-health/php/src/PluginHealth.php")"

DEST="$ROOT/lib/supertext-plugin-health"
rm -rf "$DEST"
mkdir -p "$DEST"
cp -R "$SRC/plugin-health/php/src" "$DEST/src"
cp "$SRC/LICENSE" "$DEST/LICENSE"
find "$DEST/src" -name '*.php' -exec sed -i 's/Supertext\\PluginHealth\\/SupertextTranslation\\PluginHealth\\/g; s/Supertext\\PluginHealth;/SupertextTranslation\\PluginHealth;/g' {} +
printf '%s\n' "$COMMIT" > "$DEST/COMMIT"
printf '%s\n' "$VERSION" > "$DEST/VERSION"
cat > "$DEST/README.md" <<EOF
# Supertext Plugin Health (bundled copy)

PHP part of [Supertext Plugin Health](https://github.com/Supertext/Commando-Center-Supertext-Translation/tree/main/plugin-health),
version $VERSION, commit \`$COMMIT\`, namespace \`SupertextTranslation\\PluginHealth\` instead of \`Supertext\\PluginHealth\`.

Don't edit these files: fix it in Commando-Center-Supertext-Translation/plugin-health, then run
\`scripts/vendor-plugin-health.sh\`.
EOF
if grep -rq 'Supertext\\PluginHealth' "$DEST/src"; then
    echo "vendor-plugin-health: unprefixed Supertext\\PluginHealth reference left in $DEST/src" >&2
    exit 1
fi
echo "lib/supertext-plugin-health: Supertext Plugin Health (PHP) $VERSION from commit $COMMIT"
