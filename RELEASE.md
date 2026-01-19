# Release Instructions

## Quick Start

### Method 1: Using Build Script (Recommended)

```bash
cd /home/user/surecart-shipping
./build.sh
```

This creates a clean `surecart-shippo-1.0.0.zip` ready for WordPress.

**Excluded from release:**
- Development files (composer.json, .gitignore)
- Documentation for developers (CLAUDE.md)
- Git files
- Build tools

**Included in release:**
- Plugin core files
- All includes/ directory
- All assets/ directory
- README.md (user documentation)

### Method 2: Manual Zip (Include Everything)

```bash
cd /home/user
zip -r surecart-shippo-1.0.0.zip surecart-shipping/ \
  -x "*.git*" \
  -x "*build*" \
  -x "*.DS_Store"
```

### Method 3: GitHub Release

If using GitHub releases, just create a release tag and GitHub will automatically create a zip of the entire repository.

## Installation by Users

Users can install via:

1. **WordPress Admin**
   - Go to Plugins → Add New → Upload Plugin
   - Choose the zip file
   - Click "Install Now"
   - Activate the plugin

2. **Manual Installation**
   - Unzip the file
   - Upload `surecart-shippo/` folder to `wp-content/plugins/`
   - Activate via WordPress admin

3. **WP-CLI**
   ```bash
   wp plugin install surecart-shippo-1.0.0.zip --activate
   ```

## File Structure Check

The zip should contain:
```
surecart-shippo.zip
└── surecart-shippo/
    ├── surecart-shippo.php    (main plugin file - REQUIRED)
    ├── README.md
    ├── includes/
    │   ├── Autoloader.php
    │   ├── Core/
    │   ├── Packaging/
    │   ├── Services/
    │   ├── Admin/
    │   ├── Frontend/
    │   ├── Integration/
    │   └── Diagnostics/
    ├── assets/
    │   ├── css/
    │   └── js/
    └── languages/
```

## Verification

After creating the zip, verify it's correct:

```bash
# Check the structure
unzip -l surecart-shippo-1.0.0.zip | head -20

# Should show:
# surecart-shippo/surecart-shippo.php
# surecart-shippo/README.md
# surecart-shippo/includes/...
# etc.
```

## Version Updates

When releasing a new version:

1. Update version in `surecart-shippo.php` header
2. Update version in `build.sh` (VERSION variable)
3. Update changelog in README.md
4. Run build script
5. Test the generated zip in a WordPress install
6. Tag the release in Git

## WordPress.org Submission (Optional)

If submitting to WordPress.org plugin directory:

1. The zip structure is already compatible
2. Ensure all code follows WordPress Coding Standards
3. Include proper headers and licensing
4. Test with Plugin Check plugin
5. Submit via WordPress.org SVN repository

## Distribution Channels

**Direct Distribution:**
- Upload zip to your website
- Share via GitHub releases
- Distribute via private repository

**WordPress.org:**
- Free hosting and updates
- Automatic update system
- Plugin directory listing

**Premium Distribution:**
- Freemius, WooCommerce, etc.
- License key management
- Automatic updates
