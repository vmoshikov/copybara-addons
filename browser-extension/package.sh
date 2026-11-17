#!/bin/bash
# Сборка ZIP для публикации расширения в Chrome Web Store, Firefox Add-ons, Edge Add-ons

set -e

EXT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$EXT_DIR"

VERSION=$(grep -o '"version": "[^"]*"' manifest.json | cut -d'"' -f4)
OUTPUT="copybara-extension-v${VERSION}.zip"

echo "Building $OUTPUT (version $VERSION)..."

# Создаём временную папку
TMP=$(mktemp -d)
trap "rm -rf $TMP" EXIT

# Копируем нужные файлы (сохраняем структуру icons/)
cp manifest.json popup.html popup.css popup.js logo.svg "$TMP/"
mkdir -p "$TMP/icons"
cp icons/16.png icons/48.png icons/128.png "$TMP/icons/"

# Создаём ZIP (без store/, .git, README и т.п.)
cd "$TMP"
zip -r "$EXT_DIR/$OUTPUT" . -x "*.DS_Store"
cd "$EXT_DIR"

echo "Done: $OUTPUT"
ls -la "$OUTPUT"
