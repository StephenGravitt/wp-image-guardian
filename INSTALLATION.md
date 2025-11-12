# WP Image Guardian - Installation Guide

## Plugin Location Requirements

For WordPress to detect this plugin, it **MUST** be located in:
```
/wp-content/plugins/wp-image-guardian/
```

## Current Location
The plugin is currently located at:
```
/var/www/html/sites/image-guardian/wp-plugin/
```

## Installation Steps

### Option 1: Move Plugin to WordPress Installation
1. Locate your WordPress installation's `wp-content/plugins/` directory
2. Copy or move the entire `wp-plugin` folder to `wp-content/plugins/wp-image-guardian/`
3. Ensure the main plugin file is at: `wp-content/plugins/wp-image-guardian/wp-image-guardian.php`
4. Go to WordPress Admin → Plugins
5. The plugin should now appear in the list

### Option 2: Create Symbolic Link (Development)
If you want to keep the plugin in its current location:
```bash
# From your WordPress root directory
ln -s /var/www/html/sites/image-guardian/wp-plugin /path/to/wordpress/wp-content/plugins/wp-image-guardian
```

## Verification Checklist

- [ ] Plugin is in `wp-content/plugins/wp-image-guardian/` directory
- [ ] Main file is named `wp-image-guardian.php` (matches directory name)
- [ ] File permissions allow WordPress to read the files (644 for files, 755 for directories)
- [ ] No PHP syntax errors (verified with `php -l wp-image-guardian.php`)
- [ ] Plugin header is correct (first 20 lines contain proper WordPress plugin header)

## Troubleshooting

### Plugin Not Showing in WordPress Admin

1. **Check File Permissions:**
   ```bash
   chmod 644 wp-image-guardian.php
   chmod 644 includes/*.php
   chmod 755 includes/
   chmod 755 assets/
   ```

2. **Check for PHP Errors:**
   ```bash
   php -l wp-image-guardian.php
   ```

3. **Check WordPress Debug Log:**
   - Enable `WP_DEBUG` in `wp-config.php`
   - Check `wp-content/debug.log` for errors

4. **Verify Plugin Header:**
   - The header must start with `<?php` on line 1
   - `Plugin Name:` must be on line 3
   - No BOM (Byte Order Mark) at the start of the file

5. **Clear WordPress Cache:**
   - Some caching plugins may need to be cleared
   - Try deactivating other plugins temporarily

## File Structure

The plugin should have this structure:
```
wp-image-guardian/
├── wp-image-guardian.php  (Main plugin file - REQUIRED)
├── includes/
│   ├── class-wp-image-guardian.php
│   ├── class-wp-image-guardian-admin.php
│   ├── class-wp-image-guardian-api.php
│   ├── class-wp-image-guardian-database.php
│   ├── class-wp-image-guardian-media.php
│   ├── class-wp-image-guardian-oauth.php
│   └── class-wp-image-guardian-premium.php
├── assets/
│   ├── css/
│   │   └── admin.css
│   └── js/
│       └── admin.js
├── templates/
│   ├── admin-dashboard.php
│   ├── admin-settings.php
│   ├── auto-check-page.php
│   ├── bulk-check-page.php
│   ├── media-modal.php
│   └── modal-content.php
└── README.md
```

## Next Steps After Installation

1. Activate the plugin in WordPress Admin → Plugins
2. Go to Media → Image Guardian Settings
3. Configure your API credentials
4. Connect via OAuth
5. Add your TinyEye API key

