<?php

if (!defined('ABSPATH')) {
    exit;
}

class WP_Image_Guardian_API {
    
    private $tinyeye_api;
    
    public function __construct() {
        $this->tinyeye_api = new WP_Image_Guardian_TinyEye_API();
    }
    
    public function init() {
        // Initialize API functionality
    }
    
    /**
     * Check image using TinyEye API
     */
    public function check_image($image_url) {
        $api_key = $this->tinyeye_api->get_api_key();
        
        if (empty($api_key)) {
            return [
                'success' => false,
                'message' => __('TinyEye API key not configured. Please enter your API key in the settings.', 'wp-image-guardian')
            ];
        }
        
        // Apply filter to allow modification of check parameters
        $check_params = apply_filters('wp_image_guardian_check_image', [
            'image_url' => $image_url,
            'api_key' => $api_key,
        ], $image_url);
        
        // Perform search
        $result = $this->tinyeye_api->search_image($check_params['image_url'], $check_params['api_key']);
        
        if ($result['success']) {
            // Normalize response to match expected format
            return [
                'success' => true,
                'data' => [
                    'search_id' => $result['search_id'] ?? null,
                    'total_results' => $result['total_results'] ?? 0,
                    'results' => $result['results'] ?? [],
                    'matches' => $result['matches'] ?? [],
                    'match_percentage' => $result['match_percentage'] ?? 0,
                    'raw_response' => $result['raw_response'] ?? $result,
                ]
            ];
        }
        
        return $result;
    }
    
    /**
     * Get remaining searches
     */
    public function get_remaining_searches() {
        return $this->tinyeye_api->get_remaining_searches();
    }
    
    /**
     * Validate API key
     */
    public function validate_api_key($api_key = null) {
        return $this->tinyeye_api->validate_api_key($api_key);
    }
}
