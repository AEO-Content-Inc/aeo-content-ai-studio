#!/bin/bash
# Build installable WordPress plugin ZIP.
# Usage: ./build-zip.sh
# Output: aeo-content-ai-studio.zip (ready for WP admin upload or WordPress.org)

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PLUGIN_DIR="$SCRIPT_DIR"
BUILD_DIR=$(mktemp -d)
DEST="$BUILD_DIR/aeo-content-ai-studio"

echo "Building AEO Content AI Studio plugin ZIP..."

# Copy plugin files to temp directory.
mkdir -p "$DEST/includes/modules" "$DEST/admin/views" "$DEST/admin/css" "$DEST/languages"

cp "$PLUGIN_DIR/aeo-content-ai-studio.php" "$DEST/"
cp "$PLUGIN_DIR/uninstall.php" "$DEST/"
cp "$PLUGIN_DIR/readme.txt" "$DEST/"
cp "$PLUGIN_DIR/license.txt" "$DEST/"
cp "$PLUGIN_DIR/includes/"*.php "$DEST/includes/"
cp "$PLUGIN_DIR/includes/modules/"*.php "$DEST/includes/modules/"
cp "$PLUGIN_DIR/admin/views/"*.php "$DEST/admin/views/"
cp "$PLUGIN_DIR/admin/css/"*.css "$DEST/admin/css/"

# Include .pot file if it exists.
if ls "$PLUGIN_DIR/languages/"*.pot 1>/dev/null 2>&1; then
    cp "$PLUGIN_DIR/languages/"*.pot "$DEST/languages/"
fi

# Create ZIP.
cd "$BUILD_DIR"
zip -r "$PLUGIN_DIR/aeo-content-ai-studio.zip" aeo-content-ai-studio/

# Cleanup.
rm -rf "$BUILD_DIR"

echo ""
echo "Done: aeo-content-ai-studio.zip"
echo "Upload via WordPress Admin > Plugins > Add New > Upload Plugin"
