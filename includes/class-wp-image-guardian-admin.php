<?php

if (!defined('ABSPATH')) {
    exit;
}

class WP_Image_Guardian_Admin {
    
    private $api;
    private $database;
    private $tinyeye_api;
    
    public function __construct() {
        $this->api = new WP_Image_Guardian_API();
        $this->database = new WP_Image_Guardian_Database();
        $this->tinyeye_api = new WP_Image_Guardian_TinyEye_API();
    }
    
    public function init() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_init', [$this, 'handle_settings_save']);
        add_action('admin_notices', [$this, 'admin_notices']);
    }
    
    public function add_admin_menu() {
        add_media_page(
            __('Image Guardian', 'wp-image-guardian'),
            __('Image Guardian', 'wp-image-guardian'),
            'upload_files',
            'wp-image-guardian',
            [$this, 'admin_page']
        );
    }
    
    public function register_settings() {
        register_setting('wp_image_guardian_settings', 'wp_image_guardian_settings', [
            'sanitize_callback' => [$this, 'sanitize_settings']
        ]);
    }
    
    public function sanitize_settings($input) {
        $sanitized = [];
        
        // TinyEye API Key - preserve all characters (can contain =, ^, etc.)
        if (isset($input['tinyeye_api_key'])) {
            $sanitized['tinyeye_api_key'] = WP_Image_Guardian_Helpers::sanitize_api_key($input['tinyeye_api_key']);
        }
        
        return $sanitized;
    }
    
    public function admin_notices() {
        // Show notices only on our admin page
        if (!isset($_GET['page']) || $_GET['page'] !== 'wp-image-guardian') {
            return;
        }
        
        // Settings updated notice
        if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
            echo '<div class="notice notice-success is-dismissible"><p>' . 
                 esc_html__('Settings saved successfully!', 'wp-image-guardian') . 
                 '</p></div>';
        }
        
        // API key validation notice
        if (isset($_GET['api_key_validated'])) {
            if ($_GET['api_key_validated'] === 'true') {
                echo '<div class="notice notice-success is-dismissible"><p>' . 
                     esc_html__('API key validated successfully!', 'wp-image-guardian') . 
                     '</p></div>';
            } else {
                $message = isset($_GET['message']) ? sanitize_text_field(wp_unslash($_GET['message'])) : __('API key validation failed', 'wp-image-guardian');
                echo '<div class="notice notice-error is-dismissible"><p>' . 
                     esc_html(sprintf(__('API Key Error: %s', 'wp-image-guardian'), $message)) . 
                     '</p></div>';
            }
        }
    }
    
    public function admin_page() {
        // Check user capabilities
        if (!current_user_can('upload_files')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wp-image-guardian'));
        }
        
        $settings = get_option('wp_image_guardian_settings', []);
        $api_key = $this->tinyeye_api->get_api_key();
        $is_testing_key = $this->tinyeye_api->is_testing_api_key();
        $masked_api_key = $this->tinyeye_api->mask_api_key();
        $has_constant_key = defined('WP_IMAGE_GUARDIAN_TINEYE_API_KEY');
        
        // Get media summary stats
        $total_media = $this->database->get_total_media_count();
        $checked_media = $this->database->get_checked_media_count();
        $risk_breakdown = $this->database->get_risk_breakdown();
        $unchecked_count = $this->database->get_unchecked_media_count();
        
        // Get remaining searches if API key is set
        $remaining_searches = null;
        if (!empty($api_key)) {
            $remaining_result = $this->api->get_remaining_searches();
            if ($remaining_result['success']) {
                $remaining_searches = $remaining_result['remaining_searches'];
            }
        }
        
        // Get recent checks
        $recent_checks = $this->database->get_recent_checks(10);
        
        include WP_IMAGE_GUARDIAN_PLUGIN_DIR . 'templates/admin-page.php';
    }
    
    public function handle_settings_save() {
        // Only process on our settings page
        if (!isset($_GET['page']) || $_GET['page'] !== 'wp-image-guardian') {
            return;
        }
        
        // Only process if form was submitted
        if (!isset($_POST['wp_image_guardian_save_settings']) || !isset($_POST['wp_image_guardian_nonce'])) {
            return;
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['wp_image_guardian_nonce'], 'wp_image_guardian_settings')) {
            wp_die(__('Security check failed', 'wp-image-guardian'), __('Error', 'wp-image-guardian'), ['response' => 403]);
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'wp-image-guardian'), __('Error', 'wp-image-guardian'), ['response' => 403]);
        }
        
        // Get existing settings
        $settings = get_option('wp_image_guardian_settings', []);
        
        // Handle API key
        if (isset($_POST['tinyeye_api_key'])) {
            $tinyeye_api_key = WP_Image_Guardian_Helpers::sanitize_api_key(wp_unslash($_POST['tinyeye_api_key']));
            
            // Validate API key if provided
            if (!empty($tinyeye_api_key)) {
                $validation = $this->tinyeye_api->validate_api_key($tinyeye_api_key);
                
                if ($validation['success']) {
                    $settings['tinyeye_api_key'] = $tinyeye_api_key;
                    update_option('wp_image_guardian_settings', $settings);
                    
                    // Redirect with success message
                    $redirect_url = add_query_arg([
                        'settings-updated' => 'true',
                        'api_key_validated' => 'true'
                    ], admin_url('upload.php?page=wp-image-guardian'));
                    wp_safe_redirect($redirect_url);
                    exit;
                } else {
                    // Redirect with error message
                    $error_message = urlencode($validation['message'] ?? __('Invalid API key', 'wp-image-guardian'));
                    $redirect_url = add_query_arg([
                        'api_key_validated' => 'false',
                        'message' => $error_message
                    ], admin_url('upload.php?page=wp-image-guardian'));
                    wp_safe_redirect($redirect_url);
                    exit;
                }
            } else {
                // Empty API key - clear it
                $settings['tinyeye_api_key'] = '';
                update_option('wp_image_guardian_settings', $settings);
                
                $redirect_url = add_query_arg([
                    'settings-updated' => 'true'
                ], admin_url('upload.php?page=wp-image-guardian'));
                wp_safe_redirect($redirect_url);
                exit;
            }
        }
        
        // If no API key field, just redirect
        $redirect_url = add_query_arg([
            'settings-updated' => 'true'
        ], admin_url('upload.php?page=wp-image-guardian'));
        wp_safe_redirect($redirect_url);
        exit;
    }
}
