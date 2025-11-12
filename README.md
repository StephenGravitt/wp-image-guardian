# WP Image Guardian

A WordPress plugin that integrates with the Image Guardian API service to automatically check uploaded images for copyright issues using TinyEye reverse image search.

## Features

### Free Plan
- **Manual Image Checking**: Check individual images one by one
- **Traffic Light System**: Visual indicators (🟢 Safe, 🟡 Warning, 🔴 Danger)
- **TinyEye Integration**: Uses your own TinyEye API key
- **Results Modal**: Detailed view of similar images found
- **User Decisions**: Mark images as safe or unsafe
- **Basic Reporting**: View checked images and their status

### Premium Plans
- **Bulk Image Checking**: Check multiple images at once
- **Auto Check New Uploads**: Automatically check new image uploads
- **Advanced Reporting**: Detailed analytics and usage statistics
- **Unlimited Checks**: No limits on image checks
- **Priority Support**: Enhanced customer support

## Installation

1. Upload the plugin files to `/wp-content/plugins/wp-image-guardian/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure your Image Guardian API credentials in the settings
4. Connect your account using OAuth authentication
5. Add your TinyEye API key

## Configuration

### Required Settings

1. **Image Guardian API Base URL**: Your Image Guardian service endpoint
2. **OAuth Credentials**: Client ID and Secret from Image Guardian
3. **TinyEye API Key**: Your personal TinyEye API key

### OAuth Setup

1. Go to **Media > Image Guardian Settings**
2. Enter your OAuth Client ID and Secret
3. Click "Connect to Image Guardian"
4. Complete the OAuth authorization flow
5. Your domain will be automatically approved

### TinyEye API Key

1. Get a TinyEye API key from [tineye.com](https://tineye.com)
2. Enter it in the plugin settings
3. The key is used for all reverse image searches

## Usage

### Manual Image Checking

1. Go to **Media > Library**
2. Click on any image to edit it
3. Look for the "Image Guardian Status" section
4. Click "Check Image" to run the search
5. Review the results and mark as safe/unsafe

### Bulk Checking (Premium)

1. Go to **Media > Bulk Check**
2. Select images to check
3. Click "Check Selected Images"
4. Monitor progress and results

### Auto Check (Premium)

1. Go to **Media > Auto Check**
2. Enable automatic checking
3. New uploads will be checked automatically
4. View results in the dashboard

## API Integration

The plugin integrates with the Image Guardian API service:

- **Authentication**: OAuth2 with Bearer tokens
- **Image Checking**: POST to `/api/v1/plugin/search`
- **Account Status**: GET from `/api/v1/plugin/status`
- **Usage Stats**: GET from `/api/v1/search/usage/stats`

## Database Schema

The plugin creates a custom table `wp_image_guardian_checks`:

```sql
CREATE TABLE wp_image_guardian_checks (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    attachment_id bigint(20) unsigned NOT NULL,
    image_url varchar(500) NOT NULL,
    image_hash varchar(64) DEFAULT NULL,
    search_id varchar(100) DEFAULT NULL,
    status varchar(20) DEFAULT 'pending',
    results_count int(11) DEFAULT 0,
    results_data longtext,
    risk_level varchar(20) DEFAULT 'unknown',
    user_decision varchar(20) DEFAULT NULL,
    checked_at datetime DEFAULT CURRENT_TIMESTAMP,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY attachment_id (attachment_id),
    KEY status (status),
    KEY risk_level (risk_level),
    KEY checked_at (checked_at)
);
```

## Hooks and Filters

### Actions

- `wp_image_guardian_image_checked`: Fired when an image is checked
- `wp_image_guardian_image_marked_safe`: Fired when image is marked safe
- `wp_image_guardian_image_marked_unsafe`: Fired when image is marked unsafe

### Filters

- `wp_image_guardian_risk_level`: Modify risk level calculation
- `wp_image_guardian_check_image`: Modify image check parameters
- `wp_image_guardian_results_display`: Modify results display

## Troubleshooting

### Common Issues

1. **OAuth Connection Failed**
   - Verify API credentials are correct
   - Check that the Image Guardian service is running
   - Ensure your domain is approved

2. **TinyEye API Errors**
   - Verify your TinyEye API key is valid
   - Check your TinyEye account has sufficient credits
   - Ensure the image URL is accessible

3. **Auto Check Not Working**
   - Verify premium subscription is active
   - Check WordPress cron is functioning
   - Ensure sufficient API quota

### Debug Mode

Enable WordPress debug mode to see detailed error messages:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Security

- All API keys are stored securely
- OAuth tokens are encrypted
- Image URLs are validated before processing
- User permissions are checked for all operations

## Performance

- Database queries are optimized with proper indexing
- Images are checked asynchronously when possible
- Results are cached to avoid duplicate checks
- Bulk operations are batched for efficiency

## Support

For support and feature requests:

- **Documentation**: [Image Guardian Docs](https://docs.imageguardian.com)
- **Support**: [support@imageguardian.com](mailto:support@imageguardian.com)
- **Issues**: [GitHub Issues](https://github.com/imageguardian/wp-plugin/issues)

## Changelog

### Version 1.0.0
- Initial release
- Manual image checking
- OAuth integration
- Traffic light system
- Results modal
- Premium features (bulk check, auto check)
- WordPress 5.0+ compatibility

## License

This plugin is licensed under the GPL v2 or later.

## Credits

- **TinyEye**: Reverse image search technology
- **Image Guardian**: API service and infrastructure
- **WordPress**: Content management system
