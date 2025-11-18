<?php

if (!defined('ABSPATH')) {
    exit;
}

class WP_Image_Guardian {
    
    private $oauth;
    private $api;
    private $admin;
    private $media;
    private $database;
    private $premium;
    
    public function __construct() {
        $this->oauth = new WP_Image_Guardian_OAuth();
        $this->api = new WP_Image_Guardian_API();
        $this->admin = new WP_Image_Guardian_Admin();
        $this->media = new WP_Image_Guardian_Media();
        $this->database = new WP_Image_Guardian_Database();
        $this->premium = new WP_Image_Guardian_Premium();
    }
    
    public function init() {
        // Initialize all components
        $this->oauth->init();
        $this->api->init();
        $this->admin->init();
        $this->media->init();
        $this->premium->init();
        
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
        add_action('wp_ajax_wp_image_guardian_oauth_callback', [$this, 'ajax_oauth_callback']);
        add_action('wp_ajax_wp_image_guardian_disconnect', [$this, 'ajax_disconnect']);
        add_action('wp_ajax_wp_image_guardian_get_usage_stats', [$this, 'ajax_get_usage_stats']);
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
        // Only load on media pages and our settings page
        if (strpos($hook, 'upload.php') !== false || 
            strpos($hook, 'media.php') !== false || 
            strpos($hook, 'wp-image-guardian') !== false) {
            
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
                'apiBaseUrl' => WP_IMAGE_GUARDIAN_API_BASE_URL,
                'isPremium' => $this->premium->is_premium_user(),
                'strings' => [
                    'checking' => __('Checking image...', 'wp-image-guardian'),
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
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wp_image_guardian_nonce')) {
            wp_send_json_error(__('Security check failed', 'wp-image-guardian'));
            return;
        }
        
        // Check capabilities
        if (!current_user_can('upload_files')) {
            wp_send_json_error(__('Insufficient permissions', 'wp-image-guardian'));
            return;
        }
        
        // Validate and sanitize attachment ID
        if (!isset($_POST['attachment_id'])) {
            wp_send_json_error(__('Missing attachment ID', 'wp-image-guardian'));
            return;
        }
        
        $attachment_id = absint($_POST['attachment_id']);
        if ($attachment_id <= 0) {
            wp_send_json_error(__('Invalid attachment ID', 'wp-image-guardian'));
            return;
        }
        
        // Verify attachment exists and is an image
        if (!wp_attachment_is_image($attachment_id)) {
            wp_send_json_error(__('Invalid image attachment', 'wp-image-guardian'));
            return;
        }
        
        $image_url = wp_get_attachment_url($attachment_id);
        if (!$image_url) {
            wp_send_json_error(__('Invalid image URL', 'wp-image-guardian'));
            return;
        }
        
        $result = $this->api->check_image($image_url);
        
        if ($result['success']) {
            // Store the result in database
            $this->database->store_image_check($attachment_id, $result['data']);
            
            // Fire action hook
            do_action('wp_image_guardian_image_checked', $attachment_id, $result['data']);
            
            wp_send_json_success($result['data']);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    public function ajax_get_results() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wp_image_guardian_nonce')) {
            wp_send_json_error(__('Security check failed', 'wp-image-guardian'));
            return;
        }
        
        // Check capabilities
        if (!current_user_can('upload_files')) {
            wp_send_json_error(__('Insufficient permissions', 'wp-image-guardian'));
            return;
        }
        
        // Validate and sanitize attachment ID
        if (!isset($_POST['attachment_id'])) {
            wp_send_json_error(__('Missing attachment ID', 'wp-image-guardian'));
            return;
        }
        
        $attachment_id = absint($_POST['attachment_id']);
        if ($attachment_id <= 0) {
            wp_send_json_error(__('Invalid attachment ID', 'wp-image-guardian'));
            return;
        }
        
        // Verify attachment exists
        if (!get_post($attachment_id)) {
            wp_send_json_error(__('Attachment not found', 'wp-image-guardian'));
            return;
        }
        
        $results = $this->database->get_image_results($attachment_id);
        
        wp_send_json_success($results);
    }
    
    public function ajax_mark_safe() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wp_image_guardian_nonce')) {
            wp_send_json_error(__('Security check failed', 'wp-image-guardian'));
            return;
        }
        
        // Check capabilities
        if (!current_user_can('upload_files')) {
            wp_send_json_error(__('Insufficient permissions', 'wp-image-guardian'));
            return;
        }
        
        // Validate and sanitize attachment ID
        if (!isset($_POST['attachment_id'])) {
            wp_send_json_error(__('Missing attachment ID', 'wp-image-guardian'));
            return;
        }
        
        $attachment_id = absint($_POST['attachment_id']);
        if ($attachment_id <= 0) {
            wp_send_json_error(__('Invalid attachment ID', 'wp-image-guardian'));
            return;
        }
        
        // Verify attachment exists
        if (!get_post($attachment_id)) {
            wp_send_json_error(__('Attachment not found', 'wp-image-guardian'));
            return;
        }
        
        $this->database->mark_image_safe($attachment_id);
        
        // Fire action hook
        do_action('wp_image_guardian_image_marked_safe', $attachment_id);
        
        wp_send_json_success(__('Image marked as safe', 'wp-image-guardian'));
    }
    
    public function ajax_mark_unsafe() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wp_image_guardian_nonce')) {
            wp_send_json_error(__('Security check failed', 'wp-image-guardian'));
            return;
        }
        
        // Check capabilities
        if (!current_user_can('upload_files')) {
            wp_send_json_error(__('Insufficient permissions', 'wp-image-guardian'));
            return;
        }
        
        // Validate and sanitize attachment ID
        if (!isset($_POST['attachment_id'])) {
            wp_send_json_error(__('Missing attachment ID', 'wp-image-guardian'));
            return;
        }
        
        $attachment_id = absint($_POST['attachment_id']);
        if ($attachment_id <= 0) {
            wp_send_json_error(__('Invalid attachment ID', 'wp-image-guardian'));
            return;
        }
        
        // Verify attachment exists
        if (!get_post($attachment_id)) {
            wp_send_json_error(__('Attachment not found', 'wp-image-guardian'));
            return;
        }
        
        $this->database->mark_image_unsafe($attachment_id);
        
        // Fire action hook
        do_action('wp_image_guardian_image_marked_unsafe', $attachment_id);
        
        wp_send_json_success(__('Image marked as unsafe', 'wp-image-guardian'));
    }
    
    public function ajax_oauth_callback() {
        // Validate and sanitize OAuth callback parameters
        if (!isset($_GET['code']) || !isset($_GET['state'])) {
            wp_safe_redirect(admin_url('upload.php?page=wp-image-guardian&tab=settings&oauth=error&message=' . urlencode(__('Missing OAuth parameters', 'wp-image-guardian'))));
            exit;
        }
        
        $code = sanitize_text_field(wp_unslash($_GET['code']));
        $state = sanitize_text_field(wp_unslash($_GET['state']));
        
        // Validate code and state format (alphanumeric and common OAuth characters)
        if (!preg_match('/^[a-zA-Z0-9\-_\.]+$/', $code) || !preg_match('/^[a-zA-Z0-9\-_\.]+$/', $state)) {
            wp_safe_redirect(admin_url('upload.php?page=wp-image-guardian&tab=settings&oauth=error&message=' . urlencode(__('Invalid OAuth parameters', 'wp-image-guardian'))));
            exit;
        }
        
        $result = $this->oauth->handle_callback($code, $state);
        
        if ($result['success']) {
            wp_safe_redirect(admin_url('upload.php?page=wp-image-guardian&tab=dashboard&oauth=success'));
        } else {
            $error_message = isset($result['message']) ? sanitize_text_field($result['message']) : __('OAuth authentication failed', 'wp-image-guardian');
            wp_safe_redirect(admin_url('upload.php?page=wp-image-guardian&tab=settings&oauth=error&message=' . urlencode($error_message)));
        }
        exit;
    }
    
    public function ajax_disconnect() {
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
        
        $this->oauth->disconnect();
        
        wp_send_json_success(__('Disconnected from Image Guardian', 'wp-image-guardian'));
    }
    
    public function ajax_get_usage_stats() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wp_image_guardian_nonce')) {
            wp_send_json_error(__('Security check failed', 'wp-image-guardian'));
            return;
        }
        
        // Check capabilities
        if (!current_user_can('upload_files')) {
            wp_send_json_error(__('Insufficient permissions', 'wp-image-guardian'));
            return;
        }
        
        $usage_stats = $this->api->get_usage_stats();
        
        if ($usage_stats['success']) {
            wp_send_json_success($usage_stats['data']);
        } else {
            $error_message = isset($usage_stats['message']) ? sanitize_text_field($usage_stats['message']) : __('Failed to get usage stats', 'wp-image-guardian');
            wp_send_json_error($error_message);
        }
    }
    
    public function ajax_bulk_check() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wp_image_guardian_nonce')) {
            wp_send_json_error(__('Security check failed', 'wp-image-guardian'));
            return;
        }
        
        // Check capabilities
        if (!current_user_can('upload_files')) {
            wp_send_json_error(__('Insufficient permissions', 'wp-image-guardian'));
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
            
            $image_url = wp_get_attachment_url($attachment_id);
            if ($image_url) {
                $result = $this->api->check_image($image_url);
                if ($result['success']) {
                    $this->database->store_image_check($attachment_id, $result['data']);
                    // Fire action hook
                    do_action('wp_image_guardian_image_checked', $attachment_id, $result['data']);
                    $results[] = ['id' => $attachment_id, 'status' => 'checked'];
                } else {
                    $error_message = isset($result['message']) ? sanitize_text_field($result['message']) : __('Check failed', 'wp-image-guardian');
                    $results[] = ['id' => $attachment_id, 'status' => 'error', 'message' => $error_message];
                }
            } else {
                $results[] = ['id' => $attachment_id, 'status' => 'error', 'message' => __('Invalid image URL', 'wp-image-guardian')];
            }
        }
        
        wp_send_json_success($results);
    }
    
    public function ajax_auto_check() {
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
            return;
        }
        
        $auto_check = get_option('wp_image_guardian_auto_check', false);
        if (!$auto_check) {
            return;
        }
        
        $image_url = wp_get_attachment_url($attachment_id);
        if ($image_url) {
            $result = $this->api->check_image($image_url);
            if ($result['success']) {
                $this->database->store_image_check($attachment_id, $result['data']);
                // Fire action hook
                do_action('wp_image_guardian_image_checked', $attachment_id, $result['data']);
            }
        }
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
            $image_url = wp_get_attachment_url($image->ID);
            if ($image_url) {
                $result = $this->api->check_image($image_url);
                if ($result['success']) {
                    $this->database->store_image_check($image->ID, $result['data']);
                    // Fire action hook
                    do_action('wp_image_guardian_image_checked', $image->ID, $result['data']);
                }
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
        
        // Schedule immediate check for new uploads
        wp_schedule_single_event(time() + 30, 'wp_image_guardian_check_single_upload', [$attachment_id]);
    }
}
