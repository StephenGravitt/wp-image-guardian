<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Helper class for common functionality
 */
class WP_Image_Guardian_Helpers {
    
    /**
     * Allowed risk levels
     */
    const ALLOWED_RISK_LEVELS = ['high', 'medium', 'low', 'safe', 'warning', 'danger', 'unknown'];
    
    /**
     * Verify AJAX request security
     * 
     * @param string $capability Required capability (default: 'upload_files')
     * @param string $nonce_action Nonce action name (default: 'wp_image_guardian_nonce')
     * @return array|false Returns false on success, or error array on failure
     */
    public static function verify_ajax_request($capability = 'upload_files', $nonce_action = 'wp_image_guardian_nonce') {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], $nonce_action)) {
            return [
                'success' => false,
                'message' => __('Security check failed', 'wp-image-guardian')
            ];
        }
        
        // Check capabilities
        if (!current_user_can($capability)) {
            return [
                'success' => false,
                'message' => __('Insufficient permissions', 'wp-image-guardian')
            ];
        }
        
        return false; // No error
    }
    
    /**
     * Validate and sanitize attachment ID from POST request
     * 
     * @param bool $require_image Whether to require the attachment to be an image
     * @return array Returns ['success' => bool, 'attachment_id' => int|false, 'message' => string]
     */
    public static function validate_attachment_id($require_image = false) {
        if (!isset($_POST['attachment_id'])) {
            return [
                'success' => false,
                'attachment_id' => false,
                'message' => __('Missing attachment ID', 'wp-image-guardian')
            ];
        }
        
        $attachment_id = absint($_POST['attachment_id']);
        
        if ($attachment_id <= 0) {
            return [
                'success' => false,
                'attachment_id' => false,
                'message' => __('Invalid attachment ID', 'wp-image-guardian')
            ];
        }
        
        // Verify attachment exists
        $post = get_post($attachment_id);
        if (!$post) {
            return [
                'success' => false,
                'attachment_id' => false,
                'message' => __('Attachment not found', 'wp-image-guardian')
            ];
        }
        
        // Check if it's an image if required
        if ($require_image && !wp_attachment_is_image($attachment_id)) {
            return [
                'success' => false,
                'attachment_id' => false,
                'message' => __('Invalid image attachment', 'wp-image-guardian')
            ];
        }
        
        return [
            'success' => true,
            'attachment_id' => $attachment_id,
            'message' => ''
        ];
    }
    
    /**
     * Validate risk level
     * 
     * @param string $risk_level Risk level to validate
     * @param bool $allow_empty Whether to allow empty string
     * @return bool
     */
    public static function is_valid_risk_level($risk_level, $allow_empty = false) {
        if ($allow_empty && $risk_level === '') {
            return true;
        }
        
        return in_array($risk_level, self::ALLOWED_RISK_LEVELS, true);
    }
    
    /**
     * Sanitize API key (preserves special characters)
     * 
     * @param string $api_key API key to sanitize
     * @return string Sanitized API key
     */
    public static function sanitize_api_key($api_key) {
        $api_key = sanitize_text_field($api_key);
        // Only trim whitespace, don't strip special characters
        $api_key = trim($api_key);
        return substr($api_key, 0, 255);
    }
    
    /**
     * Get image URL for attachment
     * 
     * @param int $attachment_id Attachment ID
     * @return array Returns ['success' => bool, 'url' => string|false, 'message' => string]
     */
    public static function get_attachment_image_url($attachment_id) {
        $image_url = wp_get_attachment_url($attachment_id);
        
        if (!$image_url) {
            return [
                'success' => false,
                'url' => false,
                'message' => __('Invalid image URL', 'wp-image-guardian')
            ];
        }
        
        return [
            'success' => true,
            'url' => $image_url,
            'message' => ''
        ];
    }
    
    /**
     * Convert image URL for TinyEye API
     * Replaces local/development URLs with production URL if constant is set
     * 
     * @param string $image_url Original image URL
     * @return string Converted URL for TinyEye
     */
    public static function convert_url_for_tinyeye($image_url) {
        // Check if site URL override constant is defined
        if (!defined('WP_IMAGE_GUARDIAN_SITE_URL_OVERRIDE')) {
            return $image_url;
        }
        
        $override_url = WP_IMAGE_GUARDIAN_SITE_URL_OVERRIDE;
        
        // Ensure override URL ends with /
        $override_url = rtrim($override_url, '/') . '/';
        
        // Get current site URL
        $current_site_url = site_url();
        $current_site_url = rtrim($current_site_url, '/') . '/';
        
        // If URLs are the same, no need to replace
        if ($current_site_url === $override_url) {
            return $image_url;
        }
        
        // Replace current site URL with override URL
        $converted_url = str_replace($current_site_url, $override_url, $image_url);
        
        // Also handle URLs without trailing slash
        $current_site_url_no_slash = rtrim($current_site_url, '/');
        $override_url_no_slash = rtrim($override_url, '/');
        $converted_url = str_replace($current_site_url_no_slash, $override_url_no_slash, $converted_url);
        
        return $converted_url;
    }
    
    /**
     * Map old risk levels to new ones
     * 
     * @param string $risk_level Risk level to map
     * @return string Mapped risk level
     */
    public static function map_risk_level($risk_level) {
        $mapping = [
            'safe' => 'low',
            'warning' => 'medium',
            'danger' => 'high',
        ];
        
        return $mapping[$risk_level] ?? $risk_level;
    }
    
    /**
     * Get risk level class for display
     * 
     * @param string $risk_level Risk level
     * @return string CSS class
     */
    public static function get_risk_level_class($risk_level) {
        $mapped = self::map_risk_level($risk_level);
        
        $classes = [
            'high' => 'danger',
            'medium' => 'warning',
            'low' => 'safe',
        ];
        
        return $classes[$mapped] ?? 'unknown';
    }
    
    /**
     * Send JSON error response with consistent format
     * 
     * @param string $message Error message
     * @param mixed $data Additional data
     */
    public static function send_json_error($message, $data = null) {
        if ($data !== null) {
            wp_send_json_error(['message' => $message, 'data' => $data]);
        } else {
            wp_send_json_error($message);
        }
    }
    
    /**
     * Send JSON success response with consistent format
     * 
     * @param mixed $data Response data
     * @param string $message Optional success message
     */
    public static function send_json_success($data, $message = '') {
        if ($message) {
            wp_send_json_success(['data' => $data, 'message' => $message]);
        } else {
            wp_send_json_success($data);
        }
    }
    
    /**
     * Process and store image check result
     * 
     * @param int $attachment_id Attachment ID
     * @param array $result API result
     * @param object $database Database instance
     * @return bool Success status
     */
    public static function process_image_check($attachment_id, $result, $database) {
        if (!$result['success']) {
            if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('[WP Image Guardian - process_image_check] Result not successful for attachment: ' . $attachment_id);
            }
            return false;
        }
        
        // Debug: Log what we're about to store
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[WP Image Guardian - process_image_check] Processing check for attachment: ' . $attachment_id);
            error_log('[WP Image Guardian - process_image_check] Data structure: ' . print_r([
                'data_type' => gettype($result['data']),
                'data_keys' => is_array($result['data']) ? array_keys($result['data']) : 'not array',
                'has_matches' => !empty($result['data']['matches']),
                'has_results' => !empty($result['data']['results']),
                'total_results' => $result['data']['total_results'] ?? 'not set',
            ], true));
        }
        
        // Store the result in database
        $stored = $database->store_image_check($attachment_id, $result['data']);
        
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[WP Image Guardian - process_image_check] Store result: ' . ($stored ? 'success (ID: ' . $stored . ')' : 'FAILED'));
        }
        
        if ($stored) {
            // Clear queued flag if it was set
            delete_post_meta($attachment_id, '_wp_image_guardian_queued');
            delete_post_meta($attachment_id, '_wp_image_guardian_queued_at');
            
            // Fire action hook
            do_action('wp_image_guardian_image_checked', $attachment_id, $result['data']);
            return true;
        }
        
        return false;
    }
    
    /**
     * Get status icon for risk level
     * 
     * @param string $risk_level Risk level
     * @param string|null $user_decision User decision (safe/unsafe)
     * @return string Icon emoji
     */
    public static function get_status_icon($risk_level, $user_decision = null) {
        if ($user_decision) {
            return $user_decision === 'safe' ? '✅' : '❌';
        }
        
        $mapped = self::map_risk_level($risk_level);
        
        switch ($mapped) {
            case 'high': return '🔴';
            case 'medium': return '🟡';
            case 'low': return '🟢';
            default: return '⚪';
        }
    }
    
    /**
     * Get status text for risk level
     * 
     * @param string $risk_level Risk level
     * @param string|null $user_decision User decision (safe/unsafe)
     * @return string Status text
     */
    public static function get_status_text($risk_level, $user_decision = null) {
        if ($user_decision) {
            return $user_decision === 'safe' ? 
                __('Marked as Safe', 'wp-image-guardian') : 
                __('Marked as Unsafe', 'wp-image-guardian');
        }
        
        $mapped = self::map_risk_level($risk_level);
        
        switch ($mapped) {
            case 'high': return __('High Risk', 'wp-image-guardian');
            case 'medium': return __('Medium Risk', 'wp-image-guardian');
            case 'low': return __('Low Risk', 'wp-image-guardian');
            default: return __('Not checked', 'wp-image-guardian');
        }
    }
    
    /**
     * Check if image format is supported by TinyEye
     * Supported formats: JPEG, PNG, WebP, GIF, BMP, AVIF, or TIFF
     * 
     * @param int $attachment_id Attachment ID
     * @return array Returns ['supported' => bool, 'mime_type' => string, 'format' => string]
     */
    public static function is_supported_image_format($attachment_id) {
        $mime_type = get_post_mime_type($attachment_id);
        
        if (!$mime_type) {
            // Try to get from file
            $file_path = get_attached_file($attachment_id);
            if ($file_path && file_exists($file_path)) {
                $file_info = wp_check_filetype($file_path);
                $mime_type = $file_info['type'] ?? '';
            }
        }
        
        $supported_mimes = [
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/webp',
            'image/gif',
            'image/bmp',
            'image/x-ms-bmp',
            'image/avif',
            'image/tiff',
            'image/tif',
            'image/heic',  // iPhone/iOS images
            'image/heif',  // High Efficiency Image Format
        ];
        
        $supported = in_array(strtolower($mime_type), $supported_mimes, true);
        
        // Extract format from mime type
        $format = 'unknown';
        if ($mime_type) {
            $parts = explode('/', $mime_type);
            $format = strtoupper($parts[1] ?? 'unknown');
            // Normalize some formats
            if ($format === 'JPG') {
                $format = 'JPEG';
            } elseif ($format === 'X-MS-BMP') {
                $format = 'BMP';
            } elseif ($format === 'TIF') {
                $format = 'TIFF';
            }
        }
        
        return [
            'supported' => $supported,
            'mime_type' => $mime_type,
            'format' => $format,
        ];
    }
}

