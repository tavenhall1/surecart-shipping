#!/bin/bash
# Build script for WordPress plugin release

# Configuration
PLUGIN_SLUG="surecart-shippo"
VERSION="1.0.0"
BUILD_DIR="build"
RELEASE_DIR="$BUILD_DIR/$PLUGIN_SLUG"

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}Building WordPress plugin release for $PLUGIN_SLUG v$VERSION${NC}"

# Clean previous build
rm -rf $BUILD_DIR
mkdir -p $RELEASE_DIR

# Copy plugin files
echo -e "${GREEN}Copying plugin files...${NC}"

# Copy main plugin file
cp surecart-shippo.php $RELEASE_DIR/

# Copy README
cp README.md $RELEASE_DIR/

# Copy directories
cp -r includes $RELEASE_DIR/
cp -r assets $RELEASE_DIR/

# Create languages directory if it doesn't exist
mkdir -p $RELEASE_DIR/languages

# Remove any unwanted files
find $RELEASE_DIR -name ".DS_Store" -delete 2>/dev/null || true
find $RELEASE_DIR -name "*.log" -delete 2>/dev/null || true
find $RELEASE_DIR -name ".gitkeep" -delete 2>/dev/null || true

echo -e "${GREEN}Files copied successfully${NC}"

# Create zip file
echo -e "${GREEN}Creating zip archive...${NC}"
cd $BUILD_DIR
zip -r -q "../${PLUGIN_SLUG}-${VERSION}.zip" $PLUGIN_SLUG/
cd ..

# Get file info
FILE_SIZE=$(du -h "${PLUGIN_SLUG}-${VERSION}.zip" 2>/dev/null | cut -f1 || echo "N/A")
FILE_COUNT=$(find $RELEASE_DIR -type f 2>/dev/null | wc -l || echo "N/A")

# Cleanup
echo -e "${GREEN}Cleaning up...${NC}"
rm -rf $BUILD_DIR

echo ""
echo -e "${GREEN}✓ Release created: ${PLUGIN_SLUG}-${VERSION}.zip${NC}"
echo -e "${BLUE}File size: ${FILE_SIZE}${NC}"
echo -e "${BLUE}Files included: ${FILE_COUNT}${NC}"
echo ""
echo "Ready to upload to WordPress!"
echo ""
echo "To verify the structure:"
echo "  unzip -l ${PLUGIN_SLUG}-${VERSION}.zip | head -20"
