# Building the Plugin

This repository includes both local build scripts and GitHub Actions workflows for automated builds.

## Local Build Script

### Quick Start

The easiest way to build the plugin locally:

```bash
./build-plugin.sh
```

The script will:
- Extract the version from the plugin file
- Prompt you for the output directory (defaults to current directory)
- Warn if files will be overwritten
- Let you choose minimal, full, or both zip files
- Validate the plugin structure
- Generate checksums

### Build Options

1. **Minimal Build** (Recommended for WordPress)
   - Plugin files only
   - Excludes documentation
   - Smaller file size
   - Ready for WordPress installation

2. **Full Build**
   - Includes README.md and documentation
   - Larger file size
   - Good for distribution

3. **Both**
   - Creates both minimal and full versions

### Example Usage

```bash
# Build in current directory (default)
./build-plugin.sh

# Build in Downloads folder
./build-plugin.sh
# Then enter: ~/Downloads

# Build in custom location
./build-plugin.sh
# Then enter: /path/to/output
```

## GitHub Actions Workflows

### 1. Release Build (`release.yml`)
**Triggers:** When a GitHub release is created or published

Automatically builds and attaches zip files to releases:
- Extracts version from the release tag (removes 'v' prefix if present)
- Creates minimal and full zip files
- Attaches them to the release automatically
- Generates checksums for verification

**Usage:**
1. Create a new release in GitHub (with tag like `v1.0.0` or `1.0.0`)
2. The workflow automatically runs and attaches the zip files

### 2. Manual Build (`manual-build.yml`)
**Triggers:** Manual trigger from Actions tab

Allows on-demand builds:
- Can specify custom version number
- Optionally create a GitHub release
- Useful for testing or creating custom builds

### 3. Reusable Build (`build-plugin.yml`)
**Status:** Available for future use as a reusable workflow

A reusable workflow that can be called by other workflows to keep code DRY.

## Downloading Builds

### From GitHub Releases

1. Go to **Releases** in the repository
2. Find the release you want
3. Download the zip file from the release assets:
   - `wp-image-guardian-{version}.zip` (minimal)
   - `wp-image-guardian-{version}-full.zip` (with docs)
   - `checksums.txt` (for verification)

### From GitHub Actions Artifacts

1. Go to the **Actions** tab in GitHub
2. Select a completed workflow run
3. Scroll down to **Artifacts**
4. Download the zip file(s)

## Build Artifacts

Each build produces:

- **Minimal Build** (`wp-image-guardian-{version}.zip`): 
  - Plugin files only
  - Excludes: BUILD.md, INSTALLATION.md, BUILD.txt
  - Ready for WordPress installation
  
- **Full Build** (`wp-image-guardian-{version}-full.zip`): 
  - Includes all documentation (README.md, etc.)
  - Excludes only: BUILD.txt
  - Good for distribution with documentation

- **Checksums** (`checksums.txt`): 
  - SHA256 and MD5 checksums for verification
  - Use to verify file integrity

## Local Building

To build locally:

```bash
# From the wp-plugin directory
cd /path/to/wp-plugin

# Create build directory
mkdir -p build/wp-image-guardian

# Copy files (excluding build artifacts)
rsync -av --exclude='.git' --exclude='.github' --exclude='build' \
  --exclude='.gitignore' --exclude='BUILD.md' --exclude='INSTALLATION.md' \
  ./ build/wp-image-guardian/

# Create zip
cd build
zip -r wp-image-guardian-1.0.0.zip wp-image-guardian/ \
  -x "*.git*" \
  -x "*.DS_Store" \
  -x "*node_modules/*" \
  -x "*.log" \
  -x "INSTALLATION.md" \
  -x "BUILD.md" \
  -x "BUILD.txt"
```

## Installation

1. Download the zip file from Releases or Artifacts
2. Go to WordPress Admin → Plugins → Add New
3. Click **Upload Plugin**
4. Choose the zip file
5. Click **Install Now**
6. Activate the plugin

## Version Numbering

- **Stable releases**: `1.0.0`, `1.0.1`, etc.
  - Extracted from release tag (e.g., `v1.0.0` → `1.0.0`)
  
- **Nightly builds**: `1.0.0-nightly-20241112-abc1234`
  - Format: `{base-version}-nightly-{date}-{git-hash}`
  - Example: `1.0.0-nightly-20241112-a1b2c3d`

## Workflow Architecture

All workflows share common build logic:
- Find plugin file (handles both root and subdirectory layouts)
- Extract/calculate version number
- Copy plugin files (excluding build artifacts)
- Create zip files (minimal and full)
- Generate checksums

This keeps the code DRY while allowing each workflow to have its specific triggers and outputs.

## Troubleshooting

### Build Fails to Find Plugin File
- Ensure `wp-image-guardian.php` exists in the repository root or `wp-plugin/` subdirectory
- Check file permissions

### Version Not Updating
- For releases: Ensure tag format is `v1.0.0` or `1.0.0`
- For manual builds: Specify version in workflow inputs
- Check plugin header in `wp-image-guardian.php`

### Zip File Not Attached to Release
- Check workflow permissions (needs `contents: write`)
- Verify `GITHUB_TOKEN` is available
- Check workflow logs for errors

