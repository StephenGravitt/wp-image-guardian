<?php

if (!defined('ABSPATH')) {
    exit;
}

class WP_Image_Guardian_Bulk_Check {
    
    private $api;
    private $database;
    private $tinyeye_api;
    private $rate_limit_seconds = 0.5;
    
    public function __construct() {
        $this->api = new WP_Image_Guardian_API();
        $this->database = new WP_Image_Guardian_Database();
        $this->tinyeye_api = new WP_Image_Guardian_TinyEye_API();
    }
    
    public function init() {
        // AJAX handlers
        add_action('wp_ajax_wp_image_guardian_start_bulk_check', [$this, 'ajax_start_bulk_check']);
        add_action('wp_ajax_wp_image_guardian_cancel_bulk_check', [$this, 'ajax_cancel_bulk_check']);
        add_action('wp_ajax_wp_image_guardian_get_bulk_progress', [$this, 'ajax_get_bulk_progress']);
        
        // Action hook for processing single image
        add_action('wp_image_guardian_process_single_image', [$this, 'process_single_image'], 10, 1);
    }
    
    /**
     * Start bulk check process
     */
    public function ajax_start_bulk_check() {
        // Verify security
        $error = WP_Image_Guardian_Helpers::verify_ajax_request();
        if ($error) {
            wp_send_json_error($error['message']);
            return;
        }
        
        // Check if API key is set
        $api_key = $this->tinyeye_api->get_api_key();
        if (empty($api_key)) {
            wp_send_json_error(__('API key not configured', 'wp-image-guardian'));
            return;
        }
        
        // Check remaining searches
        $remaining = $this->api->get_remaining_searches();
        if (!$remaining['success'] || ($remaining['remaining_searches'] ?? 0) <= 0) {
            wp_send_json_error(__('No remaining searches available', 'wp-image-guardian'));
            return;
        }
        
        // Get unchecked images
        $unchecked_images = $this->get_unchecked_attachment_ids();
        
        if (empty($unchecked_images)) {
            wp_send_json_error(__('No unchecked images found', 'wp-image-guardian'));
            return;
        }
        
        // Limit to remaining searches
        $remaining_count = $remaining['remaining_searches'];
        $images_to_check = array_slice($unchecked_images, 0, $remaining_count);
        
        // Initialize bulk check status
        update_option('wp_image_guardian_bulk_check_status', 'running');
        update_option('wp_image_guardian_bulk_check_progress', [
            'total' => count($images_to_check),
            'current' => 0,
            'completed' => 0,
            'failed' => 0,
        ]);
        
        // Reset queue/state and schedule first item
        wp_clear_scheduled_hook('wp_image_guardian_process_single_image');
        update_option('wp_image_guardian_bulk_queue', array_values($images_to_check));
        update_option('wp_image_guardian_bulk_last_run', 0);
        $this->schedule_next_image();
        
        wp_send_json_success([
            'message' => __('Bulk check started', 'wp-image-guardian'),
            'total' => count($images_to_check),
        ]);
    }
    
    /**
     * Cancel bulk check process
     */
    public function ajax_cancel_bulk_check() {
        // Verify security
        $error = WP_Image_Guardian_Helpers::verify_ajax_request();
        if ($error) {
            wp_send_json_error($error['message']);
            return;
        }
        
        $this->cancel_bulk_check();
        
        wp_send_json_success(__('Bulk check cancelled', 'wp-image-guardian'));
    }
    
    /**
     * Get bulk check progress
     */
    public function ajax_get_bulk_progress() {
        // Verify security
        $error = WP_Image_Guardian_Helpers::verify_ajax_request();
        if ($error) {
            wp_send_json_error($error['message']);
            return;
        }
        
        $progress = $this->get_progress();
        
        wp_send_json_success($progress);
    }
    
    /**
     * Process a single image
     */
    public function process_single_image($attachment_id) {
        // Check if bulk check is still running
        $status = get_option('wp_image_guardian_bulk_check_status');
        if ($status !== 'running') {
            return; // Bulk check was cancelled or completed
        }
        
        // Rate limit (ensure minimum delay between requests)
        $last_run = floatval(get_option('wp_image_guardian_bulk_last_run', 0));
        if ($last_run > 0) {
            $elapsed = microtime(true) - $last_run;
            $wait = $this->rate_limit_seconds - $elapsed;
            if ($wait > 0) {
                usleep((int) ($wait * 1000000));
            }
        }
        
        // Check remaining searches before processing
        $remaining = $this->api->get_remaining_searches();
        if (!$remaining['success'] || ($remaining['remaining_searches'] ?? 0) <= 0) {
            // Auto-cancel if no searches left
            $this->cancel_bulk_check();
            return;
        }
        
        // Verify attachment exists and is an image
        if (!wp_attachment_is_image($attachment_id)) {
            $this->finalize_bulk_item('failed');
            return;
        }
        
        // Check if image format is supported
        $format_check = WP_Image_Guardian_Helpers::is_supported_image_format($attachment_id);
        if (!$format_check['supported']) {
            $this->database->mark_image_reviewed($attachment_id, 'unsupported_format');
            $this->finalize_bulk_item('completed'); // Count skipped as completed
            return;
        }
        
        $url_result = WP_Image_Guardian_Helpers::get_attachment_image_url($attachment_id);
        if (!$url_result['success']) {
            $this->finalize_bulk_item('failed');
            return;
        }
        
        // Perform check
        $result = $this->api->check_image($url_result['url']);
        
        if (WP_Image_Guardian_Helpers::process_image_check($attachment_id, $result, $this->database)) {
            $this->finalize_bulk_item('completed');
        } else {
            $this->finalize_bulk_item('failed');
        }
    }
    
    /**
     * Get unchecked attachment IDs
     */
    private function get_unchecked_attachment_ids() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'image_guardian_checks';
        
        $ids = $wpdb->get_col(
            "SELECT DISTINCT p.ID
             FROM {$wpdb->posts} p
             LEFT JOIN {$table_name} ig ON p.ID = ig.attachment_id
             WHERE p.post_type = 'attachment'
             AND p.post_mime_type LIKE 'image/%'
             AND ig.id IS NULL
             ORDER BY p.post_date DESC"
        );
        
        return array_map('absint', $ids);
    }
    
    /**
     * Cancel bulk check
     */
    private function cancel_bulk_check() {
        update_option('wp_image_guardian_bulk_check_status', 'cancelled');
        
        // Clear scheduled events
        wp_clear_scheduled_hook('wp_image_guardian_process_single_image');
        delete_option('wp_image_guardian_bulk_queue');
        delete_option('wp_image_guardian_bulk_last_run');
    }
    
    /**
     * Get progress
     */
    public function get_progress() {
        $status = get_option('wp_image_guardian_bulk_check_status', 'idle');
        $progress = get_option('wp_image_guardian_bulk_check_progress', [
            'total' => 0,
            'current' => 0,
            'completed' => 0,
            'failed' => 0,
        ]);
        
        $queue = get_option('wp_image_guardian_bulk_queue', []);
        $remaining = is_array($queue) ? count($queue) : 0;
        if (wp_next_scheduled('wp_image_guardian_process_single_image')) {
            $remaining += 1;
        }
        
        $progress['status'] = $status;
        $progress['remaining'] = max(0, $remaining);
        $progress['current'] = min($progress['total'], $progress['completed'] + $progress['failed']);
        
        return $progress;
    }
    
    /**
     * Update progress
     */
    private function update_progress($type) {
        $progress = get_option('wp_image_guardian_bulk_check_progress', [
            'total' => 0,
            'current' => 0,
            'completed' => 0,
            'failed' => 0,
        ]);
        
        if ($type === 'completed') {
            $progress['completed']++;
        } elseif ($type === 'failed') {
            $progress['failed']++;
        }
        
        $progress['current'] = $progress['completed'] + $progress['failed'];
        
        update_option('wp_image_guardian_bulk_check_progress', $progress);
    }
    
    /**
     * Update progress + schedule next item with rate limiting
     */
    private function finalize_bulk_item($result_type) {
        $this->update_progress($result_type);
        update_option('wp_image_guardian_bulk_last_run', microtime(true));
        
        if (get_option('wp_image_guardian_bulk_check_status') === 'running') {
            $this->schedule_next_image();
        }
    }
    
    /**
     * Schedule the next image in the queue
     */
    private function schedule_next_image() {
        if (get_option('wp_image_guardian_bulk_check_status') !== 'running') {
            return;
        }
        
        $queue = get_option('wp_image_guardian_bulk_queue', []);
        if (empty($queue)) {
            update_option('wp_image_guardian_bulk_check_status', 'completed');
            delete_option('wp_image_guardian_bulk_queue');
            delete_option('wp_image_guardian_bulk_last_run');
            return;
        }
        
        $next_id = array_shift($queue);
        update_option('wp_image_guardian_bulk_queue', $queue);
        
        // Schedule immediately; rate limiting handled inside process_single_image
        wp_schedule_single_event(time(), 'wp_image_guardian_process_single_image', [$next_id]);
    }
}

