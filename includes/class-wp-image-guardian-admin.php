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
    }
    
    public function register_settings() {
        register_setting('wp_image_guardian_settings', 'wp_image_guardian_settings', [
            'sanitize_callback' => [$this, 'sanitize_settings']
        ]);
    }
    
    public function sanitize_settings($input) {
        $sanitized = [];
        
        // OAuth Client ID - alphanumeric and hyphens, max 255 chars
        $oauth_client_id = isset($input['oauth_client_id']) ? sanitize_text_field($input['oauth_client_id']) : '';
        $oauth_client_id = preg_replace('/[^a-zA-Z0-9\-_]/', '', $oauth_client_id);
        $sanitized['oauth_client_id'] = substr($oauth_client_id, 0, 255);
        
        // OAuth Client Secret - alphanumeric and special chars, max 255 chars
        $oauth_client_secret = isset($input['oauth_client_secret']) ? sanitize_text_field($input['oauth_client_secret']) : '';
        $sanitized['oauth_client_secret'] = substr($oauth_client_secret, 0, 255);
        
        // TinyEye API Key - alphanumeric and hyphens, max 255 chars
        $tinyeye_api_key = isset($input['tinyeye_api_key']) ? sanitize_text_field($input['tinyeye_api_key']) : '';
        $tinyeye_api_key = preg_replace('/[^a-zA-Z0-9\-_]/', '', $tinyeye_api_key);
        $sanitized['tinyeye_api_key'] = substr($tinyeye_api_key, 0, 255);
        
        // Subscription plan - whitelist validation
        $allowed_plans = ['free', 'premium_monthly', 'premium_yearly'];
        $subscription_plan = isset($input['subscription_plan']) ? sanitize_text_field($input['subscription_plan']) : 'free';
        $sanitized['subscription_plan'] = in_array($subscription_plan, $allowed_plans, true) ? $subscription_plan : 'free';
        
        return $sanitized;
    }
    
    public function admin_notices() {
        $oauth_connected = $this->oauth->is_connected();
        
        // Show notice on all admin pages if not connected (except on Image Guardian page itself)
        if (!$oauth_connected && (!isset($_GET['page']) || $_GET['page'] !== 'wp-image-guardian')) {
            $image_guardian_url = admin_url('upload.php?page=wp-image-guardian');
            echo '<div class="notice notice-warning is-dismissible"><p>' . 
                 sprintf(
                     esc_html__('Image Guardian is not connected. %s to connect your account.', 'wp-image-guardian'),
                     '<a href="' . esc_url($image_guardian_url) . '">' . esc_html__('Click here', 'wp-image-guardian') . '</a>'
                 ) . 
                 '</p></div>';
            return;
        }
        
        // Show notices only on our admin page
        if (!isset($_GET['page']) || $_GET['page'] !== 'wp-image-guardian') {
            return;
        }
        
        // Validate and sanitize GET parameters
        $oauth_status = isset($_GET['oauth']) ? sanitize_text_field($_GET['oauth']) : '';
        
        // OAuth success/error messages
        if ($oauth_status === 'success') {
            echo '<div class="notice notice-success is-dismissible"><p>' . 
                 esc_html__('Successfully connected to Image Guardian!', 'wp-image-guardian') . 
                 '</p></div>';
        } elseif ($oauth_status === 'error') {
            $message = isset($_GET['message']) ? sanitize_text_field(wp_unslash($_GET['message'])) : __('OAuth authentication failed', 'wp-image-guardian');
            echo '<div class="notice notice-error is-dismissible"><p>' . 
                 esc_html(sprintf(__('OAuth Error: %s', 'wp-image-guardian'), $message)) . 
                 '</p></div>';
        }
    }
    
    public function admin_page() {
        // Check user capabilities
        if (!current_user_can('upload_files')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wp-image-guardian'));
        }
        
        $settings = get_option('wp_image_guardian_settings', []);
        $oauth_connected = $this->oauth->is_connected();
        
        // If not connected, only show connect button
        if (!$oauth_connected) {
            include WP_IMAGE_GUARDIAN_PLUGIN_DIR . 'templates/admin-page.php';
            return;
        }
        
        // If connected, load full data for dashboard
        $user_info = $this->oauth->get_user_info();
        $account_status = $this->api->get_account_status();
        $usage_stats = $this->api->get_usage_stats();
        $risk_stats = $this->database->get_risk_stats();
        $recent_checks = $this->database->get_recent_checks(10);
        
        include WP_IMAGE_GUARDIAN_PLUGIN_DIR . 'templates/admin-page.php';
    }
    
    private function handle_settings_save() {
        // Verify nonce
        if (!isset($_POST['wp_image_guardian_nonce']) || !wp_verify_nonce($_POST['wp_image_guardian_nonce'], 'wp_image_guardian_settings')) {
            wp_die(__('Security check failed', 'wp-image-guardian'), __('Error', 'wp-image-guardian'), ['response' => 403]);
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'wp-image-guardian'), __('Error', 'wp-image-guardian'), ['response' => 403]);
        }
        
        // Get existing settings to preserve any fields not in the form
        $existing_settings = get_option('wp_image_guardian_settings', []);
        
        // Sanitize and validate all inputs
        $settings = $existing_settings; // Start with existing settings
        
        // OAuth Client ID
        if (isset($_POST['oauth_client_id'])) {
            $oauth_client_id = sanitize_text_field(wp_unslash($_POST['oauth_client_id']));
            $oauth_client_id = preg_replace('/[^a-zA-Z0-9\-_]/', '', $oauth_client_id);
            $settings['oauth_client_id'] = substr($oauth_client_id, 0, 255);
        }
        
        // OAuth Client Secret - encrypt before storing (per integration guide security best practices)
        if (isset($_POST['oauth_client_secret']) && !empty($_POST['oauth_client_secret'])) {
            $oauth_client_secret = sanitize_text_field(wp_unslash($_POST['oauth_client_secret']));
            // Encrypt the secret before storing
            if (function_exists('wp_encrypt')) {
                $settings['oauth_client_secret'] = wp_encrypt($oauth_client_secret);
            } else {
                // Fallback if wp_encrypt not available (shouldn't happen in WP 6.0+)
                $settings['oauth_client_secret'] = substr($oauth_client_secret, 0, 255);
            }
        }
        // If empty, keep existing value (user didn't change it)
        
        // TinyEye API Key
        if (isset($_POST['tinyeye_api_key'])) {
            $tinyeye_api_key = sanitize_text_field(wp_unslash($_POST['tinyeye_api_key']));
            $tinyeye_api_key = preg_replace('/[^a-zA-Z0-9\-_]/', '', $tinyeye_api_key);
            $settings['tinyeye_api_key'] = substr($tinyeye_api_key, 0, 255);
        }
        
        // Subscription Plan - whitelist validation
        if (isset($_POST['subscription_plan'])) {
            $allowed_plans = ['free', 'premium_monthly', 'premium_yearly'];
            $subscription_plan = sanitize_text_field(wp_unslash($_POST['subscription_plan']));
            $settings['subscription_plan'] = in_array($subscription_plan, $allowed_plans, true) ? $subscription_plan : 'free';
        }
        
        // Update settings
        $updated = update_option('wp_image_guardian_settings', $settings);
        
        // Redirect to prevent resubmission and show success message
        $redirect_url = admin_url('upload.php?page=wp-image-guardian&settings-updated=true');
        wp_safe_redirect($redirect_url);
        exit;
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
