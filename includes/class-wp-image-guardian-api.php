<?php

if (!defined('ABSPATH')) {
    exit;
}

class WP_Image_Guardian_API {
    
    private $oauth;
    private $api_base_url;
    
    public function __construct() {
        $this->oauth = new WP_Image_Guardian_OAuth();
        $settings = get_option('wp_image_guardian_settings', []);
        $this->api_base_url = $settings['api_base_url'] ?? WP_IMAGE_GUARDIAN_API_BASE_URL;
    }
    
    public function init() {
        // Initialize API functionality
    }
    
    public function check_image($image_url) {
        if (!$this->oauth->is_connected()) {
            return [
                'success' => false,
                'message' => __('Not connected to Image Guardian. Please authenticate first.', 'wp-image-guardian')
            ];
        }
        
        $access_token = $this->oauth->get_access_token();
        if (!$access_token) {
            return [
                'success' => false,
                'message' => __('Invalid access token. Please re-authenticate.', 'wp-image-guardian')
            ];
        }
        
        $settings = get_option('wp_image_guardian_settings', []);
        $tinyeye_api_key = $settings['tinyeye_api_key'] ?? '';
        
        if (empty($tinyeye_api_key)) {
            return [
                'success' => false,
                'message' => __('TinyEye API key not configured. Please add your TinyEye API key in settings.', 'wp-image-guardian')
            ];
        }
        
        $response = wp_remote_post($this->api_base_url . '/plugin/search', [
            'headers' => [
                'X-API-Key' => $this->get_api_key(),
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'image_url' => $image_url,
                'tinyeye_api_key' => $tinyeye_api_key,
            ]),
            'timeout' => 60, // Longer timeout for image search
        ]);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message()
            ];
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['success']) && $data['success']) {
            return [
                'success' => true,
                'data' => $data['data']
            ];
        }
        
        return [
            'success' => false,
            'message' => $data['message'] ?? __('Image check failed', 'wp-image-guardian')
        ];
    }
    
    public function get_account_status() {
        if (!$this->oauth->is_connected()) {
            return [
                'success' => false,
                'message' => __('Not connected to Image Guardian', 'wp-image-guardian')
            ];
        }
        
        $access_token = $this->oauth->get_access_token();
        if (!$access_token) {
            return [
                'success' => false,
                'message' => __('Invalid access token', 'wp-image-guardian')
            ];
        }
        
        $response = wp_remote_get($this->api_base_url . '/plugin/status', [
            'headers' => [
                'X-API-Key' => $this->get_api_key(),
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
        ]);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message()
            ];
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['success']) && $data['success']) {
            return [
                'success' => true,
                'data' => $data['data']
            ];
        }
        
        return [
            'success' => false,
            'message' => $data['message'] ?? __('Failed to get account status', 'wp-image-guardian')
        ];
    }
    
    public function get_usage_stats() {
        if (!$this->oauth->is_connected()) {
            return [
                'success' => false,
                'message' => __('Not connected to Image Guardian', 'wp-image-guardian')
            ];
        }
        
        $access_token = $this->oauth->get_access_token();
        if (!$access_token) {
            return [
                'success' => false,
                'message' => __('Invalid access token', 'wp-image-guardian')
            ];
        }
        
        $response = wp_remote_get($this->api_base_url . '/search/usage/stats', [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
        ]);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message()
            ];
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['success']) && $data['success']) {
            return [
                'success' => true,
                'data' => $data['data']
            ];
        }
        
        return [
            'success' => false,
            'message' => $data['message'] ?? __('Failed to get usage stats', 'wp-image-guardian')
        ];
    }
    
    public function get_search_history($limit = 50) {
        if (!$this->oauth->is_connected()) {
            return [
                'success' => false,
                'message' => __('Not connected to Image Guardian', 'wp-image-guardian')
            ];
        }
        
        $access_token = $this->oauth->get_access_token();
        if (!$access_token) {
            return [
                'success' => false,
                'message' => __('Invalid access token', 'wp-image-guardian')
            ];
        }
        
        $response = wp_remote_get($this->api_base_url . '/search/history?limit=' . $limit, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
        ]);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message()
            ];
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['success']) && $data['success']) {
            return [
                'success' => true,
                'data' => $data['data']
            ];
        }
        
        return [
            'success' => false,
            'message' => $data['message'] ?? __('Failed to get search history', 'wp-image-guardian')
        ];
    }
    
    private function get_api_key() {
        // For now, we'll use a placeholder. In a real implementation,
        // this would be generated when the user connects their account
        $api_key = get_option('wp_image_guardian_api_key');
        
        if (!$api_key) {
            // Generate a new API key for this site
            $api_key = $this->generate_api_key();
            update_option('wp_image_guardian_api_key', $api_key);
        }
        
        return $api_key;
    }
    
    private function generate_api_key() {
        return 'wp_' . wp_generate_password(40, false);
    }
    
    public function register_domain() {
        if (!$this->oauth->is_connected()) {
            return [
                'success' => false,
                'message' => __('Not connected to Image Guardian', 'wp-image-guardian')
            ];
        }
        
        $access_token = $this->oauth->get_access_token();
        if (!$access_token) {
            return [
                'success' => false,
                'message' => __('Invalid access token', 'wp-image-guardian')
            ];
        }
        
        $domain = get_site_url();
        
        $response = wp_remote_post($this->api_base_url . '/domains', [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'domain' => $domain,
                'site_name' => get_bloginfo('name'),
            ]),
            'timeout' => 30,
        ]);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message()
            ];
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['success']) && $data['success']) {
            update_option('wp_image_guardian_domain_approved', true);
            return [
                'success' => true,
                'data' => $data['data']
            ];
        }
        
        return [
            'success' => false,
            'message' => $data['message'] ?? __('Failed to register domain', 'wp-image-guardian')
        ];
    }
}
