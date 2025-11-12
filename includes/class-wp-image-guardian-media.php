<?php

if (!defined('ABSPATH')) {
    exit;
}

class WP_Image_Guardian_Media {
    
    private $database;
    private $api;
    
    public function __construct() {
        $this->database = new WP_Image_Guardian_Database();
        $this->api = new WP_Image_Guardian_API();
    }
    
    public function init() {
        add_filter('attachment_fields_to_edit', [$this, 'add_image_guardian_fields'], 10, 2);
        add_filter('attachment_fields_to_save', [$this, 'save_image_guardian_fields'], 10, 2);
        add_action('admin_footer', [$this, 'add_media_modal']);
        add_action('wp_ajax_wp_image_guardian_get_modal_content', [$this, 'get_modal_content']);
    }
    
    public function add_image_guardian_fields($fields, $post) {
        if (!wp_attachment_is_image($post->ID)) {
            return $fields;
        }
        
        $check_status = $this->database->get_image_status($post->ID);
        $risk_level = $check_status ? $check_status->risk_level : 'unknown';
        $user_decision = $check_status ? $check_status->user_decision : null;
        
        $status_html = $this->get_status_html($risk_level, $user_decision, $post->ID);
        
        $fields['image_guardian_status'] = [
            'label' => __('Image Guardian Status', 'wp-image-guardian'),
            'input' => 'html',
            'html' => $status_html,
        ];
        
        if ($check_status && $check_status->status === 'completed') {
            $fields['image_guardian_results'] = [
                'label' => __('TinyEye Results', 'wp-image-guardian'),
                'input' => 'html',
                'html' => $this->get_results_html($post->ID, $check_status),
            ];
        }
        
        return $fields;
    }
    
    public function save_image_guardian_fields($post, $attachment) {
        // Handle any save operations if needed
        return $post;
    }
    
    private function get_status_html($risk_level, $user_decision, $attachment_id) {
        $status_class = $this->get_status_class($risk_level, $user_decision);
        $status_text = $this->get_status_text($risk_level, $user_decision);
        $status_icon = $this->get_status_icon($risk_level, $user_decision);
        
        $html = '<div class="wp-image-guardian-status ' . $status_class . '">';
        $html .= '<span class="status-icon">' . $status_icon . '</span>';
        $html .= '<span class="status-text">' . $status_text . '</span>';
        
        if ($risk_level !== 'unknown' && !$user_decision) {
            $html .= '<div class="status-actions">';
            $html .= '<button type="button" class="button button-small mark-safe" data-attachment-id="' . $attachment_id . '">';
            $html .= __('Mark Safe', 'wp-image-guardian');
            $html .= '</button>';
            $html .= '<button type="button" class="button button-small mark-unsafe" data-attachment-id="' . $attachment_id . '">';
            $html .= __('Mark Unsafe', 'wp-image-guardian');
            $html .= '</button>';
            $html .= '</div>';
        }
        
        if ($risk_level !== 'unknown') {
            $html .= '<div class="status-actions">';
            $html .= '<button type="button" class="button button-small view-results" data-attachment-id="' . $attachment_id . '">';
            $html .= __('View Results', 'wp-image-guardian');
            $html .= '</button>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    private function get_results_html($attachment_id, $check_status) {
        $results_data = json_decode($check_status->results_data, true);
        $total_results = $results_data['total_results'] ?? 0;
        
        $html = '<div class="wp-image-guardian-results">';
        $html .= '<p><strong>' . sprintf(__('Found %d similar images', 'wp-image-guardian'), $total_results) . '</strong></p>';
        
        if ($total_results > 0) {
            $html .= '<button type="button" class="button view-detailed-results" data-attachment-id="' . $attachment_id . '">';
            $html .= __('View Detailed Results', 'wp-image-guardian');
            $html .= '</button>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    private function get_status_class($risk_level, $user_decision) {
        if ($user_decision) {
            return $user_decision === 'safe' ? 'status-safe' : 'status-unsafe';
        }
        
        switch ($risk_level) {
            case 'safe':
                return 'status-safe';
            case 'warning':
                return 'status-warning';
            case 'danger':
                return 'status-danger';
            default:
                return 'status-unknown';
        }
    }
    
    private function get_status_text($risk_level, $user_decision) {
        if ($user_decision) {
            return $user_decision === 'safe' ? 
                __('Marked as Safe', 'wp-image-guardian') : 
                __('Marked as Unsafe', 'wp-image-guardian');
        }
        
        switch ($risk_level) {
            case 'safe':
                return __('Safe - No matches found', 'wp-image-guardian');
            case 'warning':
                return __('Warning - Few matches found', 'wp-image-guardian');
            case 'danger':
                return __('Danger - Many matches found', 'wp-image-guardian');
            default:
                return __('Not checked', 'wp-image-guardian');
        }
    }
    
    private function get_status_icon($risk_level, $user_decision) {
        if ($user_decision) {
            return $user_decision === 'safe' ? '✅' : '❌';
        }
        
        switch ($risk_level) {
            case 'safe':
                return '🟢';
            case 'warning':
                return '🟡';
            case 'danger':
                return '🔴';
            default:
                return '⚪';
        }
    }
    
    public function add_media_modal() {
        $screen = get_current_screen();
        if ($screen && $screen->id === 'attachment') {
            include WP_IMAGE_GUARDIAN_PLUGIN_DIR . 'templates/media-modal.php';
        }
    }
    
    public function get_modal_content() {
        check_ajax_referer('wp_image_guardian_nonce', 'nonce');
        
        if (!current_user_can('upload_files')) {
            wp_die(__('Insufficient permissions', 'wp-image-guardian'));
        }
        
        $attachment_id = intval($_POST['attachment_id']);
        $results = $this->database->get_image_results($attachment_id);
        
        if (!$results) {
            wp_send_json_error(__('No results found', 'wp-image-guardian'));
        }
        
        $results_data = json_decode($results->results_data, true);
        
        ob_start();
        include WP_IMAGE_GUARDIAN_PLUGIN_DIR . 'templates/modal-content.php';
        $content = ob_get_clean();
        
        wp_send_json_success(['content' => $content]);
    }
    
    public function get_attachment_status($attachment_id) {
        return $this->database->get_image_status($attachment_id);
    }
    
    public function get_attachment_results($attachment_id) {
        return $this->database->get_image_results($attachment_id);
    }
    
    public function check_image($attachment_id) {
        $image_url = wp_get_attachment_url($attachment_id);
        
        if (!$image_url) {
            return [
                'success' => false,
                'message' => __('Invalid image URL', 'wp-image-guardian')
            ];
        }
        
        $result = $this->api->check_image($image_url);
        
        if ($result['success']) {
            $this->database->store_image_check($attachment_id, $result['data']);
            return $result;
        }
        
        return $result;
    }
    
    public function mark_safe($attachment_id) {
        return $this->database->mark_image_safe($attachment_id);
    }
    
    public function mark_unsafe($attachment_id) {
        return $this->database->mark_image_unsafe($attachment_id);
    }
}
