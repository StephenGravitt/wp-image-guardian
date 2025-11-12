<?php

if (!defined('ABSPATH')) {
    exit;
}

class WP_Image_Guardian_Admin {
    
    private $oauth;
    private $api;
    private $database;
    
    public function __construct() {
        $this->oauth = new WP_Image_Guardian_OAuth();
        $this->api = new WP_Image_Guardian_API();
        $this->database = new WP_Image_Guardian_Database();
    }
    
    public function init() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
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
        
        add_submenu_page(
            'upload.php',
            __('Image Guardian Settings', 'wp-image-guardian'),
            __('Image Guardian', 'wp-image-guardian'),
            'manage_options',
            'wp-image-guardian-settings',
            [$this, 'settings_page']
        );
    }
    
    public function register_settings() {
        register_setting('wp_image_guardian_settings', 'wp_image_guardian_settings', [
            'sanitize_callback' => [$this, 'sanitize_settings']
        ]);
    }
    
    public function sanitize_settings($input) {
        $sanitized = [];
        
        $sanitized['api_base_url'] = esc_url_raw($input['api_base_url'] ?? '');
        $sanitized['oauth_client_id'] = sanitize_text_field($input['oauth_client_id'] ?? '');
        $sanitized['oauth_client_secret'] = sanitize_text_field($input['oauth_client_secret'] ?? '');
        $sanitized['tinyeye_api_key'] = sanitize_text_field($input['tinyeye_api_key'] ?? '');
        $sanitized['subscription_plan'] = sanitize_text_field($input['subscription_plan'] ?? 'free');
        
        return $sanitized;
    }
    
    public function admin_notices() {
        if (isset($_GET['page']) && $_GET['page'] === 'wp-image-guardian') {
            if (isset($_GET['oauth']) && $_GET['oauth'] === 'success') {
                echo '<div class="notice notice-success"><p>' . 
                     __('Successfully connected to Image Guardian!', 'wp-image-guardian') . 
                     '</p></div>';
            } elseif (isset($_GET['oauth']) && $_GET['oauth'] === 'error') {
                $message = sanitize_text_field($_GET['message'] ?? 'OAuth authentication failed');
                echo '<div class="notice notice-error"><p>' . 
                     sprintf(__('OAuth Error: %s', 'wp-image-guardian'), $message) . 
                     '</p></div>';
            }
        }
    }
    
    public function admin_page() {
        $oauth_connected = $this->oauth->is_connected();
        $user_info = $this->oauth->get_user_info();
        $account_status = $this->api->get_account_status();
        $usage_stats = $this->api->get_usage_stats();
        $risk_stats = $this->database->get_risk_stats();
        $recent_checks = $this->database->get_recent_checks(10);
        
        include WP_IMAGE_GUARDIAN_PLUGIN_DIR . 'templates/admin-dashboard.php';
    }
    
    public function settings_page() {
        $settings = get_option('wp_image_guardian_settings', []);
        $oauth_connected = $this->oauth->is_connected();
        $user_info = $this->oauth->get_user_info();
        
        if (isset($_POST['submit'])) {
            $this->handle_settings_save();
        }
        
        include WP_IMAGE_GUARDIAN_PLUGIN_DIR . 'templates/admin-settings.php';
    }
    
    private function handle_settings_save() {
        if (!wp_verify_nonce($_POST['wp_image_guardian_nonce'], 'wp_image_guardian_settings')) {
            wp_die(__('Security check failed', 'wp-image-guardian'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'wp-image-guardian'));
        }
        
        $settings = [
            'api_base_url' => esc_url_raw($_POST['api_base_url']),
            'oauth_client_id' => sanitize_text_field($_POST['oauth_client_id']),
            'oauth_client_secret' => sanitize_text_field($_POST['oauth_client_secret']),
            'tinyeye_api_key' => sanitize_text_field($_POST['tinyeye_api_key']),
            'subscription_plan' => sanitize_text_field($_POST['subscription_plan']),
        ];
        
        update_option('wp_image_guardian_settings', $settings);
        
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success"><p>' . 
                 __('Settings saved successfully!', 'wp-image-guardian') . 
                 '</p></div>';
        });
    }
    
    public function get_connection_status() {
        $oauth_connected = $this->oauth->is_connected();
        $user_info = $this->oauth->get_user_info();
        $account_status = $this->api->get_account_status();
        
        return [
            'oauth_connected' => $oauth_connected,
            'user_info' => $user_info,
            'account_status' => $account_status,
        ];
    }
    
    public function get_dashboard_stats() {
        $risk_stats = $this->database->get_risk_stats();
        $recent_checks = $this->database->get_recent_checks(10);
        $usage_stats = $this->api->get_usage_stats();
        
        return [
            'risk_stats' => $risk_stats,
            'recent_checks' => $recent_checks,
            'usage_stats' => $usage_stats,
        ];
    }
}
