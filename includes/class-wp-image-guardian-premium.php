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
        // Note: Bulk check and auto check are now on the main admin page
        add_action('wp_ajax_wp_image_guardian_auto_check_toggle', [$this, 'handle_auto_check_toggle']);
    }
    
    public function is_premium_user() {
        // In standalone version, all features are available
        // This method is kept for backward compatibility
        return true;
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
    
    public function handle_auto_check_toggle() {
        // Verify security (requires manage_options)
        $error = WP_Image_Guardian_Helpers::verify_ajax_request('manage_options');
        if ($error) {
            wp_send_json_error($error['message']);
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
    
    
    public function get_usage_limits() {
        $remaining = $this->api->get_remaining_searches();
        
        if (!$remaining['success']) {
            return [
                'used' => 0,
                'limit' => 0,
                'remaining' => 0,
                'plan' => 'standalone'
            ];
        }
        
        return [
            'used' => 0, // Not tracked in standalone version
            'limit' => 0, // Not tracked in standalone version
            'remaining' => $remaining['remaining_searches'] ?? 0,
            'plan' => 'standalone'
        ];
    }
    
    public function can_check_image() {
        $limits = $this->get_usage_limits();
        return $limits['remaining'] > 0;
    }
    
    public function get_upgrade_url() {
        return '#';
    }
}
