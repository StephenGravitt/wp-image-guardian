<?php

if (!defined('ABSPATH')) {
    exit;
}

class WP_Image_Guardian_Premium {
    
    private $api;
    private $database;
    
    public function __construct() {
        $this->api = new WP_Image_Guardian_API();
        $this->database = new WP_Image_Guardian_Database();
    }
    
    public function init() {
        add_action('admin_menu', [$this, 'add_premium_menu']);
        // Note: wp_image_guardian_bulk_check is handled in main class
        add_action('wp_ajax_wp_image_guardian_auto_check_toggle', [$this, 'handle_auto_check_toggle']);
    }
    
    public function add_premium_menu() {
        if (!$this->is_premium_user()) {
            return;
        }
        
        add_submenu_page(
            'upload.php',
            __('Bulk Check Images', 'wp-image-guardian'),
            __('Bulk Check', 'wp-image-guardian'),
            'upload_files',
            'wp-image-guardian-bulk',
            [$this, 'bulk_check_page']
        );
        
        add_submenu_page(
            'upload.php',
            __('Auto Check Settings', 'wp-image-guardian'),
            __('Auto Check', 'wp-image-guardian'),
            'manage_options',
            'wp-image-guardian-auto',
            [$this, 'auto_check_page']
        );
    }
    
    public function is_premium_user() {
        $account_status = $this->api->get_account_status();
        
        if (!$account_status['success']) {
            return false;
        }
        
        $data = $account_status['data'];
        $subscription_status = $data['account']['subscription_status'] ?? 'inactive';
        
        return in_array($subscription_status, ['active', 'premium_monthly', 'premium_yearly']);
    }
    
    public function get_premium_features() {
        return [
            'bulk_check' => [
                'name' => __('Bulk Image Check', 'wp-image-guardian'),
                'description' => __('Check multiple images at once', 'wp-image-guardian'),
                'available' => $this->is_premium_user(),
            ],
            'auto_check' => [
                'name' => __('Auto Check New Uploads', 'wp-image-guardian'),
                'description' => __('Automatically check new image uploads', 'wp-image-guardian'),
                'available' => $this->is_premium_user(),
            ],
            'advanced_reporting' => [
                'name' => __('Advanced Reporting', 'wp-image-guardian'),
                'description' => __('Detailed reports and analytics', 'wp-image-guardian'),
                'available' => $this->is_premium_user(),
            ],
            'unlimited_checks' => [
                'name' => __('Unlimited Checks', 'wp-image-guardian'),
                'description' => __('No limits on image checks', 'wp-image-guardian'),
                'available' => $this->is_premium_user(),
            ],
        ];
    }
    
    public function bulk_check_page() {
        if (!$this->is_premium_user()) {
            $this->show_upgrade_notice();
            return;
        }
        
        $unchecked_images = $this->database->get_unchecked_images(168); // Last week
        $checked_images = $this->database->get_checked_images(50);
        
        include WP_IMAGE_GUARDIAN_PLUGIN_DIR . 'templates/bulk-check-page.php';
    }
    
    public function auto_check_page() {
        if (!$this->is_premium_user()) {
            $this->show_upgrade_notice();
            return;
        }
        
        $auto_check_enabled = get_option('wp_image_guardian_auto_check', false);
        $recent_auto_checks = $this->database->get_recent_checks(20);
        
        if (isset($_POST['submit'])) {
            $this->handle_auto_check_settings();
        }
        
        include WP_IMAGE_GUARDIAN_PLUGIN_DIR . 'templates/auto-check-page.php';
    }
    
    
    public function handle_auto_check_toggle() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wp_image_guardian_nonce')) {
            wp_send_json_error(__('Security check failed', 'wp-image-guardian'));
            return;
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'wp-image-guardian'));
            return;
        }
        
        // Check premium status
        if (!$this->is_premium_user()) {
            wp_send_json_error(__('Premium feature - upgrade required', 'wp-image-guardian'));
            return;
        }
        
        // Validate and sanitize enabled parameter
        if (!isset($_POST['enabled'])) {
            wp_send_json_error(__('Missing enabled parameter', 'wp-image-guardian'));
            return;
        }
        
        $enabled = sanitize_text_field($_POST['enabled']);
        $enabled = ($enabled === 'true' || $enabled === '1' || $enabled === true);
        
        update_option('wp_image_guardian_auto_check', (bool) $enabled);
        
        if ($enabled) {
            wp_schedule_event(time(), 'hourly', 'wp_image_guardian_check_new_uploads');
        } else {
            wp_clear_scheduled_hook('wp_image_guardian_check_new_uploads');
        }
        
        wp_send_json_success(__('Auto-check settings updated', 'wp-image-guardian'));
    }
    
    private function handle_auto_check_settings() {
        // Verify nonce
        if (!isset($_POST['wp_image_guardian_nonce']) || !wp_verify_nonce($_POST['wp_image_guardian_nonce'], 'wp_image_guardian_auto_check')) {
            wp_die(__('Security check failed', 'wp-image-guardian'), __('Error', 'wp-image-guardian'), ['response' => 403]);
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'wp-image-guardian'), __('Error', 'wp-image-guardian'), ['response' => 403]);
        }
        
        // Validate and sanitize auto_check parameter
        $auto_check = isset($_POST['auto_check']) && ($_POST['auto_check'] === '1' || $_POST['auto_check'] === 'on' || $_POST['auto_check'] === true);
        update_option('wp_image_guardian_auto_check', (bool) $auto_check);
        
        if ($auto_check) {
            wp_schedule_event(time(), 'hourly', 'wp_image_guardian_check_new_uploads');
        } else {
            wp_clear_scheduled_hook('wp_image_guardian_check_new_uploads');
        }
        
        // Redirect to prevent resubmission
        wp_safe_redirect(admin_url('upload.php?page=wp-image-guardian-auto&settings-updated=true'));
        exit;
    }
    
    private function calculate_risk_level($results) {
        $total_results = $results['total_results'] ?? 0;
        
        if ($total_results === 0) {
            return 'safe';
        } elseif ($total_results <= 3) {
            return 'warning';
        } else {
            return 'danger';
        }
    }
    
    private function show_upgrade_notice() {
        echo '<div class="wrap">';
        echo '<h1>' . __('Premium Features', 'wp-image-guardian') . '</h1>';
        echo '<div class="notice notice-warning">';
        echo '<p><strong>' . __('Premium Feature', 'wp-image-guardian') . '</strong></p>';
        echo '<p>' . __('This feature requires a premium subscription. Please upgrade your account to access bulk checking and auto-monitoring features.', 'wp-image-guardian') . '</p>';
        echo '<p><a href="' . admin_url('upload.php?page=wp-image-guardian&tab=dashboard') . '" class="button button-primary">' . __('Upgrade Now', 'wp-image-guardian') . '</a></p>';
        echo '</div>';
        echo '</div>';
    }
    
    public function get_usage_limits() {
        $usage_stats = $this->api->get_usage_stats();
        
        if (!$usage_stats['success']) {
            return [
                'used' => 0,
                'limit' => 1,
                'remaining' => 1,
                'plan' => 'free'
            ];
        }
        
        $data = $usage_stats['data'];
        return [
            'used' => $data['api_requests_used'],
            'limit' => $data['api_requests_limit'],
            'remaining' => $data['remaining_requests'],
            'plan' => $data['subscription_status']
        ];
    }
    
    public function can_check_image() {
        if ($this->is_premium_user()) {
            return true;
        }
        
        $limits = $this->get_usage_limits();
        return $limits['remaining'] > 0;
    }
    
    public function get_upgrade_url() {
        $account_status = $this->api->get_account_status();
        
        if ($account_status['success']) {
            $data = $account_status['data'];
            return $data['billing_url'] ?? '#';
        }
        
        return '#';
    }
}
