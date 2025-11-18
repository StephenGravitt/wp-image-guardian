#!/bin/bash

# WP Image Guardian - Local Build Script
# This script builds a WordPress-ready zip file from the plugin

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Get script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$SCRIPT_DIR"
BUILD_DIR="$SCRIPT_DIR/build"

# Find plugin file
if [ -f "$PLUGIN_DIR/wp-image-guardian.php" ]; then
    PLUGIN_FILE="$PLUGIN_DIR/wp-image-guardian.php"
elif [ -f "$PLUGIN_DIR/wp-plugin/wp-image-guardian.php" ]; then
    PLUGIN_FILE="$PLUGIN_DIR/wp-plugin/wp-image-guardian.php"
    PLUGIN_DIR="$PLUGIN_DIR/wp-plugin"
else
    echo -e "${RED}Error: Could not find wp-image-guardian.php${NC}"
    exit 1
fi

# Get version from plugin file
VERSION=$(grep "Version:" "$PLUGIN_FILE" | sed 's/.*Version: *\([0-9.]*\).*/\1/' | tr -d ' ')

if [ -z "$VERSION" ]; then
    echo -e "${RED}Error: Could not extract version from plugin file${NC}"
    exit 1
fi

ZIP_NAME="wp-image-guardian-${VERSION}.zip"
ZIP_NAME_FULL="wp-image-guardian-${VERSION}-full.zip"

echo -e "${GREEN}WP Image Guardian Build Script${NC}"
echo "================================"
echo "Plugin Version: $VERSION"
echo "Plugin Directory: $PLUGIN_DIR"
echo ""

# Prompt for output directory
echo -e "${YELLOW}Where would you like to save the zip file?${NC}"
echo "Press Enter to use current directory: $SCRIPT_DIR"
read -p "Output directory: " OUTPUT_DIR

if [ -z "$OUTPUT_DIR" ]; then
    OUTPUT_DIR="$SCRIPT_DIR"
fi

# Expand ~ and resolve path
OUTPUT_DIR=$(eval echo "$OUTPUT_DIR")
OUTPUT_DIR=$(cd "$OUTPUT_DIR" 2>/dev/null && pwd || echo "$OUTPUT_DIR")

if [ ! -d "$OUTPUT_DIR" ]; then
    echo -e "${RED}Error: Directory does not exist: $OUTPUT_DIR${NC}"
    exit 1
fi

OUTPUT_FILE="$OUTPUT_DIR/$ZIP_NAME"
OUTPUT_FILE_FULL="$OUTPUT_DIR/$ZIP_NAME_FULL"

# Check if files exist and warn
if [ -f "$OUTPUT_FILE" ]; then
    echo -e "${YELLOW}Warning: $ZIP_NAME already exists in $OUTPUT_DIR${NC}"
    read -p "Overwrite? (y/N): " OVERWRITE
    if [[ ! "$OVERWRITE" =~ ^[Yy]$ ]]; then
        echo "Build cancelled."
        exit 0
    fi
    rm -f "$OUTPUT_FILE"
fi

if [ -f "$OUTPUT_FILE_FULL" ]; then
    echo -e "${YELLOW}Warning: $ZIP_NAME_FULL already exists in $OUTPUT_DIR${NC}"
    read -p "Overwrite? (y/N): " OVERWRITE
    if [[ ! "$OVERWRITE" =~ ^[Yy]$ ]]; then
        echo "Skipping full build."
        BUILD_FULL=false
    else
        rm -f "$OUTPUT_FILE_FULL"
        BUILD_FULL=true
    fi
else
    BUILD_FULL=true
fi

# Ask which version to build
echo ""
echo "Which version would you like to build?"
echo "1) Minimal (without documentation) - Recommended for WordPress"
echo "2) Full (with documentation)"
echo "3) Both"
read -p "Choice [1-3] (default: 1): " BUILD_CHOICE

BUILD_MINIMAL=false
BUILD_FULL_CHOICE=false

case "$BUILD_CHOICE" in
    2)
        BUILD_FULL_CHOICE=true
        ;;
    3)
        BUILD_MINIMAL=true
        BUILD_FULL_CHOICE=true
        ;;
    *)
        BUILD_MINIMAL=true
        ;;
esac

# Create build directory
echo ""
echo "Creating build directory..."
rm -rf "$BUILD_DIR"
mkdir -p "$BUILD_DIR/wp-image-guardian"

# Copy plugin files
echo "Copying plugin files..."
rsync -av --exclude='.git' \
      --exclude='.github' \
      --exclude='build' \
      --exclude='.gitignore' \
      --exclude='BUILD.md' \
      --exclude='INSTALLATION.md' \
      --exclude='TROUBLESHOOTING.md' \
      --exclude='*.zip' \
      "$PLUGIN_DIR/" "$BUILD_DIR/wp-image-guardian/"

# Update version in plugin file (in case it changed)
sed -i "s/Version: .*/Version: $VERSION/" "$BUILD_DIR/wp-image-guardian/wp-image-guardian.php"

# Validate plugin structure
echo "Validating plugin structure..."
if [ ! -f "$BUILD_DIR/wp-image-guardian/wp-image-guardian.php" ]; then
    echo -e "${RED}Error: Plugin file not found in build directory!${NC}"
    exit 1
fi

if ! grep -q "Plugin Name:" "$BUILD_DIR/wp-image-guardian/wp-image-guardian.php"; then
    echo -e "${RED}Error: Invalid plugin header!${NC}"
    exit 1
fi

echo -e "${GREEN}Plugin structure validated${NC}"

# Build minimal zip
if [ "$BUILD_MINIMAL" = true ]; then
    echo ""
    echo "Building minimal zip (without documentation)..."
    cd "$BUILD_DIR"
    
    # Remove documentation files
    rm -f wp-image-guardian/README.md
    rm -f wp-image-guardian/BUILD.md
    rm -f wp-image-guardian/INSTALLATION.md
    rm -f wp-image-guardian/TROUBLESHOOTING.md
    
    zip -r "$OUTPUT_FILE" wp-image-guardian/ \
        -x "*.git*" \
        -x "*.DS_Store" \
        -x "*node_modules/*" \
        -x "*.log" \
        -x "*.md" \
        -x "INSTALLATION.md" \
        -x "BUILD.md" \
        -x "TROUBLESHOOTING.md" \
        -x "BUILD.txt" > /dev/null
    
    # Verify zip
    if ! unzip -l "$OUTPUT_FILE" | grep -q "wp-image-guardian/wp-image-guardian.php"; then
        echo -e "${RED}Error: Plugin file not found in zip!${NC}"
        exit 1
    fi
    
    ZIP_SIZE=$(du -h "$OUTPUT_FILE" | cut -f1)
    echo -e "${GREEN}✓ Minimal zip created: $OUTPUT_FILE ($ZIP_SIZE)${NC}"
fi

# Build full zip
if [ "$BUILD_FULL_CHOICE" = true ] && [ "$BUILD_FULL" = true ]; then
    echo ""
    echo "Building full zip (with documentation)..."
    cd "$BUILD_DIR"
    
    # Remove only build-specific files, keep README.md
    rm -f wp-image-guardian/BUILD.md
    rm -f wp-image-guardian/INSTALLATION.md
    rm -f wp-image-guardian/TROUBLESHOOTING.md
    
    zip -r "$OUTPUT_FILE_FULL" wp-image-guardian/ \
        -x "*.git*" \
        -x "*.DS_Store" \
        -x "*node_modules/*" \
        -x "*.log" \
        -x "BUILD.txt" \
        -x "BUILD.md" \
        -x "INSTALLATION.md" \
        -x "TROUBLESHOOTING.md" > /dev/null
    
    # Verify zip
    if ! unzip -l "$OUTPUT_FILE_FULL" | grep -q "wp-image-guardian/wp-image-guardian.php"; then
        echo -e "${RED}Error: Plugin file not found in zip!${NC}"
        exit 1
    fi
    
    ZIP_SIZE_FULL=$(du -h "$OUTPUT_FILE_FULL" | cut -f1)
    echo -e "${GREEN}✓ Full zip created: $OUTPUT_FILE_FULL ($ZIP_SIZE_FULL)${NC}"
fi

# Cleanup
echo ""
echo "Cleaning up build directory..."
rm -rf "$BUILD_DIR"

# Generate checksums
echo ""
echo "Generating checksums..."
cd "$OUTPUT_DIR"
if [ "$BUILD_MINIMAL" = true ] && [ -f "$OUTPUT_FILE" ]; then
    sha256sum "$(basename "$OUTPUT_FILE")" >> checksums.txt 2>/dev/null || true
    md5sum "$(basename "$OUTPUT_FILE")" >> checksums.txt 2>/dev/null || true
fi
if [ "$BUILD_FULL_CHOICE" = true ] && [ -f "$OUTPUT_FILE_FULL" ]; then
    sha256sum "$(basename "$OUTPUT_FILE_FULL")" >> checksums.txt 2>/dev/null || true
    md5sum "$(basename "$OUTPUT_FILE_FULL")" >> checksums.txt 2>/dev/null || true
fi

echo ""
echo -e "${GREEN}Build completed successfully!${NC}"
echo ""
if [ "$BUILD_MINIMAL" = true ]; then
    echo "Minimal zip: $OUTPUT_FILE"
fi
if [ "$BUILD_FULL_CHOICE" = true ] && [ "$BUILD_FULL" = true ]; then
    echo "Full zip: $OUTPUT_FILE_FULL"
fi
echo ""
echo "You can now upload the zip file to WordPress:"
echo "  WordPress Admin → Plugins → Add New → Upload Plugin"


