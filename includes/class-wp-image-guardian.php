<?php

if (!defined('ABSPATH')) {
    exit;
}

class WP_Image_Guardian {
    
    private $api;
    private $admin;
    private $media;
    private $database;
    private $premium;
    private $bulk_check;
    
    public function __construct() {
        $this->api = new WP_Image_Guardian_API();
        $this->database = new WP_Image_Guardian_Database();
        $this->media = new WP_Image_Guardian_Media();
        $this->admin = new WP_Image_Guardian_Admin();
        $this->premium = new WP_Image_Guardian_Premium();
        $this->bulk_check = new WP_Image_Guardian_Bulk_Check();
    }
    
    public function init() {
        // Initialize all components
        $this->api->init();
        $this->admin->init();
        $this->media->init();
        $this->premium->init();
        $this->bulk_check->init();
        
        // Load text domain
        add_action('init', [$this, 'load_textdomain']);
        
        // Enqueue scripts and styles
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);
        
        // AJAX handlers
        add_action('wp_ajax_wp_image_guardian_check_image', [$this, 'ajax_check_image']);
        add_action('wp_ajax_wp_image_guardian_get_results', [$this, 'ajax_get_results']);
        add_action('wp_ajax_wp_image_guardian_mark_safe', [$this, 'ajax_mark_safe']);
        add_action('wp_ajax_wp_image_guardian_mark_unsafe', [$this, 'ajax_mark_unsafe']);
        add_action('wp_ajax_wp_image_guardian_get_remaining_searches', [$this, 'ajax_get_remaining_searches']);
        add_action('wp_ajax_wp_image_guardian_bulk_check', [$this, 'ajax_bulk_check']);
        add_action('wp_ajax_wp_image_guardian_auto_check', [$this, 'ajax_auto_check']);
        
        // Scheduled events for premium features
        add_action('wp_image_guardian_check_new_uploads', [$this, 'check_new_uploads']);
        add_action('wp_image_guardian_check_single_upload', [$this, 'check_single_upload']);
        
        // Hook into media uploads for premium users
        add_action('add_attachment', [$this, 'handle_new_upload']);
    }
    
    public function load_textdomain() {
        load_plugin_textdomain('wp-image-guardian', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    public function enqueue_admin_scripts($hook) {
        // Load on media pages, attachment edit page, and our settings page
        if (strpos($hook, 'upload.php') !== false || 
            strpos($hook, 'media.php') !== false || 
            strpos($hook, 'attachment') !== false ||
            strpos($hook, 'wp-image-guardian') !== false ||
            $hook === 'post.php') {
            
            wp_enqueue_script(
                'wp-image-guardian-admin',
                WP_IMAGE_GUARDIAN_PLUGIN_URL . 'assets/js/admin.js',
                ['jquery'],
                WP_IMAGE_GUARDIAN_VERSION,
                true
            );
            
            wp_enqueue_style(
                'wp-image-guardian-admin',
                WP_IMAGE_GUARDIAN_PLUGIN_URL . 'assets/css/admin.css',
                [],
                WP_IMAGE_GUARDIAN_VERSION
            );
            
            // Localize script
            wp_localize_script('wp-image-guardian-admin', 'wpImageGuardian', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wp_image_guardian_nonce'),
                'isPremium' => $this->premium->is_premium_user(),
                'strings' => [
                    'checking' => __('Checking image...', 'wp-image-guardian'),
                    'checked' => __('Checked', 'wp-image-guardian'),
                    'error' => __('Error checking image', 'wp-image-guardian'),
                    'safe' => __('Image is safe', 'wp-image-guardian'),
                    'unsafe' => __('Image may have copyright issues', 'wp-image-guardian'),
                    'unknown' => __('Image status unknown', 'wp-image-guardian'),
                ]
            ]);
        }
    }
    
    public function enqueue_frontend_scripts() {
        // Only enqueue if needed for frontend features
    }
    
    public function ajax_check_image() {
        // Verify security
        $error = WP_Image_Guardian_Helpers::verify_ajax_request();
        if ($error) {
            wp_send_json_error($error['message']);
            return;
        }
        
        // Validate attachment ID
        $validation = WP_Image_Guardian_Helpers::validate_attachment_id(true);
        if (!$validation['success']) {
            wp_send_json_error($validation['message']);
            return;
        }
        
        $attachment_id = $validation['attachment_id'];
        
        // Check if image format is supported
        $format_check = WP_Image_Guardian_Helpers::is_supported_image_format($attachment_id);
        if (!$format_check['supported']) {
            // Mark as reviewed so it's excluded from bulk checks
            $this->database->mark_image_reviewed($attachment_id, 'unsupported_format');
            wp_send_json_success([
                'message' => sprintf(
                    __('Unsupported image format (%s). Marked as checked (inconclusive).', 'wp-image-guardian'),
                    $format_check['format']
                ),
                'risk_level' => 'unknown',
                'user_decision' => null,
                'total_results' => 0,
            ]);
            return;
        }
        
        // Get image URL
        $url_result = WP_Image_Guardian_Helpers::get_attachment_image_url($attachment_id);
        if (!$url_result['success']) {
            wp_send_json_error($url_result['message']);
            return;
        }
        
        $result = $this->api->check_image($url_result['url']);
        
        if ($result['success']) {
            WP_Image_Guardian_Helpers::process_image_check($attachment_id, $result, $this->database);
            wp_send_json_success($result['data']);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    public function ajax_get_results() {
        // Verify security
        $error = WP_Image_Guardian_Helpers::verify_ajax_request();
        if ($error) {
            wp_send_json_error($error['message']);
            return;
        }
        
        // Validate attachment ID
        $validation = WP_Image_Guardian_Helpers::validate_attachment_id();
        if (!$validation['success']) {
            wp_send_json_error($validation['message']);
            return;
        }
        
        $results = $this->database->get_image_results($validation['attachment_id']);
        wp_send_json_success($results);
    }
    
    public function ajax_mark_safe() {
        // Verify security
        $error = WP_Image_Guardian_Helpers::verify_ajax_request();
        if ($error) {
            wp_send_json_error($error['message']);
            return;
        }
        
        // Validate attachment ID
        $validation = WP_Image_Guardian_Helpers::validate_attachment_id();
        if (!$validation['success']) {
            wp_send_json_error($validation['message']);
            return;
        }
        
        $this->database->mark_image_safe($validation['attachment_id']);
        
        // Fire action hook
        do_action('wp_image_guardian_image_marked_safe', $validation['attachment_id']);
        
        wp_send_json_success(__('Image marked as safe', 'wp-image-guardian'));
    }
    
    public function ajax_mark_unsafe() {
        // Verify security
        $error = WP_Image_Guardian_Helpers::verify_ajax_request();
        if ($error) {
            wp_send_json_error($error['message']);
            return;
        }
        
        // Validate attachment ID
        $validation = WP_Image_Guardian_Helpers::validate_attachment_id();
        if (!$validation['success']) {
            wp_send_json_error($validation['message']);
            return;
        }
        
        $this->database->mark_image_unsafe($validation['attachment_id']);
        
        // Fire action hook
        do_action('wp_image_guardian_image_marked_unsafe', $validation['attachment_id']);
        
        wp_send_json_success(__('Image marked as unsafe', 'wp-image-guardian'));
    }
    
    public function ajax_get_remaining_searches() {
        // Verify security
        $error = WP_Image_Guardian_Helpers::verify_ajax_request();
        if ($error) {
            wp_send_json_error($error['message']);
            return;
        }
        
        $remaining = $this->api->get_remaining_searches();
        
        if ($remaining['success']) {
            wp_send_json_success([
                'remaining_searches' => $remaining['remaining_searches']
            ]);
        } else {
            $error_message = $remaining['message'] ?? __('Failed to get remaining searches', 'wp-image-guardian');
            wp_send_json_error($error_message);
        }
    }
    
    public function ajax_bulk_check() {
        // Verify security
        $error = WP_Image_Guardian_Helpers::verify_ajax_request();
        if ($error) {
            wp_send_json_error($error['message']);
            return;
        }
        
        // Check premium status
        if (!$this->premium->is_premium_user()) {
            wp_send_json_error(__('Premium feature - upgrade required', 'wp-image-guardian'));
            return;
        }
        
        // Validate and sanitize attachment IDs
        if (!isset($_POST['attachment_ids']) || !is_array($_POST['attachment_ids'])) {
            wp_send_json_error(__('Invalid attachment IDs', 'wp-image-guardian'));
            return;
        }
        
        // Sanitize and limit attachment IDs (prevent DoS)
        $attachment_ids = array_map('absint', $_POST['attachment_ids']);
        $attachment_ids = array_filter($attachment_ids, function($id) {
            return $id > 0 && get_post($id) !== null;
        });
        
        // Limit to 50 attachments per request to prevent timeout/DoS
        $attachment_ids = array_slice($attachment_ids, 0, 50);
        
        if (empty($attachment_ids)) {
            wp_send_json_error(__('No valid attachments found', 'wp-image-guardian'));
            return;
        }
        
        $results = [];
        
        foreach ($attachment_ids as $attachment_id) {
            // Verify it's an image
            if (!wp_attachment_is_image($attachment_id)) {
                $results[] = ['id' => $attachment_id, 'status' => 'error', 'message' => __('Not an image', 'wp-image-guardian')];
                continue;
            }
            
            // Check if image format is supported
            $format_check = WP_Image_Guardian_Helpers::is_supported_image_format($attachment_id);
            if (!$format_check['supported']) {
                // Mark as reviewed so it's excluded from future bulk checks
                $this->database->mark_image_reviewed($attachment_id, 'unsupported_format');
                $results[] = ['id' => $attachment_id, 'status' => 'skipped', 'message' => sprintf(__('Unsupported format: %s', 'wp-image-guardian'), $format_check['format'])];
                continue;
            }
            
            $url_result = WP_Image_Guardian_Helpers::get_attachment_image_url($attachment_id);
            if ($url_result['success']) {
                $result = $this->api->check_image($url_result['url']);
                if ($result['success']) {
                    WP_Image_Guardian_Helpers::process_image_check($attachment_id, $result, $this->database);
                    $results[] = ['id' => $attachment_id, 'status' => 'checked'];
                } else {
                    $error_message = $result['message'] ?? __('Check failed', 'wp-image-guardian');
                    $results[] = ['id' => $attachment_id, 'status' => 'error', 'message' => $error_message];
                }
            } else {
                $results[] = ['id' => $attachment_id, 'status' => 'error', 'message' => $url_result['message']];
            }
        }
        
        wp_send_json_success($results);
    }
    
    public function ajax_auto_check() {
        // Verify security (requires manage_options)
        $error = WP_Image_Guardian_Helpers::verify_ajax_request('manage_options');
        if ($error) {
            wp_send_json_error($error['message']);
            return;
        }
        
        // Check premium status
        if (!$this->premium->is_premium_user()) {
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
    
    public function check_single_upload($attachment_id) {
        if (!$this->premium->is_premium_user()) {
            delete_post_meta($attachment_id, '_wp_image_guardian_queued');
            delete_post_meta($attachment_id, '_wp_image_guardian_queued_at');
            return;
        }
        
        $auto_check = get_option('wp_image_guardian_auto_check', false);
        if (!$auto_check) {
            delete_post_meta($attachment_id, '_wp_image_guardian_queued');
            delete_post_meta($attachment_id, '_wp_image_guardian_queued_at');
            return;
        }
        
        // Check if already checked (manual check may have been triggered)
        $already_checked = get_post_meta($attachment_id, '_wp_image_guardian_checked', true);
        if ($already_checked) {
            // Clear queued flag since it's already checked
            delete_post_meta($attachment_id, '_wp_image_guardian_queued');
            delete_post_meta($attachment_id, '_wp_image_guardian_queued_at');
            return;
        }
        
        // Check if image format is supported
        $format_check = WP_Image_Guardian_Helpers::is_supported_image_format($attachment_id);
        if (!$format_check['supported']) {
            // Mark as reviewed so it's excluded from future bulk checks
            $this->database->mark_image_reviewed($attachment_id, 'unsupported_format');
            // Clear queued flag
            delete_post_meta($attachment_id, '_wp_image_guardian_queued');
            delete_post_meta($attachment_id, '_wp_image_guardian_queued_at');
            return;
        }
        
        $url_result = WP_Image_Guardian_Helpers::get_attachment_image_url($attachment_id);
        if ($url_result['success']) {
            $result = $this->api->check_image($url_result['url']);
            WP_Image_Guardian_Helpers::process_image_check($attachment_id, $result, $this->database);
        }
        
        // Clear queued flag after check completes
        delete_post_meta($attachment_id, '_wp_image_guardian_queued');
        delete_post_meta($attachment_id, '_wp_image_guardian_queued_at');
    }
    
    public function check_new_uploads() {
        if (!$this->premium->is_premium_user()) {
            return;
        }
        
        $auto_check = get_option('wp_image_guardian_auto_check', false);
        if (!$auto_check) {
            return;
        }
        
        // Get unchecked images from the last hour
        $unchecked_images = $this->database->get_unchecked_images(1);
        
        foreach ($unchecked_images as $image) {
            // Check if image format is supported
            $format_check = WP_Image_Guardian_Helpers::is_supported_image_format($image->ID);
            if (!$format_check['supported']) {
                // Mark as reviewed so it's excluded from future bulk checks
                $this->database->mark_image_reviewed($image->ID, 'unsupported_format');
                continue;
            }
            
            $url_result = WP_Image_Guardian_Helpers::get_attachment_image_url($image->ID);
            if ($url_result['success']) {
                $result = $this->api->check_image($url_result['url']);
                WP_Image_Guardian_Helpers::process_image_check($image->ID, $result, $this->database);
            }
        }
    }
    
    public function handle_new_upload($attachment_id) {
        if (!$this->premium->is_premium_user()) {
            return;
        }
        
        $auto_check = get_option('wp_image_guardian_auto_check', false);
        if (!$auto_check) {
            return;
        }
        
        // Mark as queued for checking
        update_post_meta($attachment_id, '_wp_image_guardian_queued', time());
        update_post_meta($attachment_id, '_wp_image_guardian_queued_at', current_time('mysql'));
        
        // Schedule immediate check for new uploads
        wp_schedule_single_event(time() + 30, 'wp_image_guardian_check_single_upload', [$attachment_id]);
    }
}
