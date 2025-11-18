<?php
/**
 * Plugin Name: WP Image Guardian
 * Plugin URI: https://imageguardian.com
 * Description: Protect your WordPress site from copyright issues with TinyEye reverse image search integration.
 * Version: 1.0.0
 * Author: Image Guardian
 * Author URI: https://imageguardian.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-image-guardian
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WP_IMAGE_GUARDIAN_VERSION', '1.0.0');
define('WP_IMAGE_GUARDIAN_PLUGIN_FILE', __FILE__);
define('WP_IMAGE_GUARDIAN_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WP_IMAGE_GUARDIAN_PLUGIN_URL', plugin_dir_url(__FILE__));
// API base URL - must be set to your Image Guardian API service
// Default is Lando local development URL (http://image-guardian.lndo.site)
// To override, define this constant in wp-config.php before the plugin loads
// Note: If your Laravel API routes are under /api prefix, you may need to adjust
// the endpoint paths in the API class, or set this to include /api if all routes are there
if (!defined('WP_IMAGE_GUARDIAN_API_BASE_URL')) {
    define('WP_IMAGE_GUARDIAN_API_BASE_URL', 'https://image-guardian.lndo.site');
}

// Include required files
require_once WP_IMAGE_GUARDIAN_PLUGIN_DIR . 'includes/class-wp-image-guardian.php';
require_once WP_IMAGE_GUARDIAN_PLUGIN_DIR . 'includes/class-wp-image-guardian-oauth.php';
require_once WP_IMAGE_GUARDIAN_PLUGIN_DIR . 'includes/class-wp-image-guardian-api.php';
require_once WP_IMAGE_GUARDIAN_PLUGIN_DIR . 'includes/class-wp-image-guardian-admin.php';
require_once WP_IMAGE_GUARDIAN_PLUGIN_DIR . 'includes/class-wp-image-guardian-media.php';
require_once WP_IMAGE_GUARDIAN_PLUGIN_DIR . 'includes/class-wp-image-guardian-database.php';
require_once WP_IMAGE_GUARDIAN_PLUGIN_DIR . 'includes/class-wp-image-guardian-premium.php';

// Initialize the plugin
function wp_image_guardian_init() {
    $plugin = new WP_Image_Guardian();
    $plugin->init();
}

// Hook into WordPress
add_action('plugins_loaded', 'wp_image_guardian_init');

// Activation hook
register_activation_hook(__FILE__, 'wp_image_guardian_activate');
function wp_image_guardian_activate() {
    // Ensure classes are loaded
    if (!class_exists('WP_Image_Guardian_Database')) {
        require_once WP_IMAGE_GUARDIAN_PLUGIN_DIR . 'includes/class-wp-image-guardian-database.php';
    }
    
    $database = new WP_Image_Guardian_Database();
    $database->create_tables();
    
    // Set default options
    add_option('wp_image_guardian_version', WP_IMAGE_GUARDIAN_VERSION);
    add_option('wp_image_guardian_settings', [
        'oauth_client_id' => '',
        'oauth_client_secret' => '',
        'tinyeye_api_key' => '',
        'subscription_plan' => 'free',
        'domain_approved' => false,
    ]);
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'wp_image_guardian_deactivate');
function wp_image_guardian_deactivate() {
    // Clean up scheduled events
    wp_clear_scheduled_hook('wp_image_guardian_check_new_uploads');
}

// Uninstall hook
register_uninstall_hook(__FILE__, 'wp_image_guardian_uninstall');
function wp_image_guardian_uninstall() {
    // Ensure classes are loaded
    if (!class_exists('WP_Image_Guardian_Database')) {
        require_once WP_IMAGE_GUARDIAN_PLUGIN_DIR . 'includes/class-wp-image-guardian-database.php';
    }
    
    $database = new WP_Image_Guardian_Database();
    $database->drop_tables();
    
    // Remove options
    delete_option('wp_image_guardian_version');
    delete_option('wp_image_guardian_settings');
    delete_option('wp_image_guardian_oauth_tokens');
    delete_option('wp_image_guardian_user_info');
    delete_option('wp_image_guardian_domain_approved');
    delete_option('wp_image_guardian_auto_check');
}
