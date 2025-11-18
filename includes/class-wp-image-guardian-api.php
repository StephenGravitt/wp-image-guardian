<?php

if (!defined('ABSPATH')) {
    exit;
}

class WP_Image_Guardian_API {
    
    private $oauth;
    private $api_base_url;
    
    public function __construct() {
        $this->oauth = new WP_Image_Guardian_OAuth();
        $this->api_base_url = WP_IMAGE_GUARDIAN_API_BASE_URL;
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
        
        // TinyEye API key should be set automatically from API during OAuth
        // If not set, the API service should handle it or return an error
        if (empty($tinyeye_api_key)) {
            return [
                'success' => false,
                'message' => __('TinyEye API key not configured. Please ensure your Image Guardian account has a TinyEye API key configured.', 'wp-image-guardian')
            ];
        }
        
        // Apply filter to allow modification of check parameters
        $check_params = apply_filters('wp_image_guardian_check_image', [
            'image_url' => $image_url,
            'tinyeye_api_key' => $tinyeye_api_key,
        ], $image_url);
        
        $response = wp_remote_post($this->api_base_url . '/api/v1/plugin/search', [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
                'Origin' => home_url(), // Required for domain validation (per integration guide)
            ],
            'body' => json_encode($check_params),
            'timeout' => 60, // Longer timeout for image search
        ]);
        
        // Handle 401 Unauthorized - try refreshing token once
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code === 401) {
            $refreshed = $this->oauth->refresh_token();
            if ($refreshed['success']) {
                $access_token = $this->oauth->get_access_token();
                // Retry request with new token
                $response = wp_remote_post($this->api_base_url . '/api/v1/plugin/search', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $access_token,
                        'Content-Type' => 'application/json',
                        'Origin' => home_url(),
                    ],
                    'body' => json_encode($check_params),
                    'timeout' => 60,
                ]);
                $status_code = wp_remote_retrieve_response_code($response);
            }
        }
        
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
        
        $response = wp_remote_get($this->api_base_url . '/api/v1/plugin/status', [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
                'Origin' => home_url(), // Required for domain validation (per integration guide)
            ],
            'timeout' => 30,
        ]);
        
        // Handle 401 Unauthorized - try refreshing token once
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code === 401) {
            $refreshed = $this->oauth->refresh_token();
            if ($refreshed['success']) {
                $access_token = $this->oauth->get_access_token();
                // Retry request with new token
                $response = wp_remote_get($this->api_base_url . '/api/v1/plugin/status', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $access_token,
                        'Content-Type' => 'application/json',
                        'Origin' => home_url(),
                    ],
                    'timeout' => 30,
                ]);
                $status_code = wp_remote_retrieve_response_code($response);
            }
        }
        
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
        
        $response = wp_remote_get($this->api_base_url . '/api/v1/search/usage/stats', [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
                'Origin' => home_url(), // Required for domain validation (per integration guide)
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
        
        $response = wp_remote_get($this->api_base_url . '/api/v1/search/history?limit=' . $limit, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
                'Origin' => home_url(), // Required for domain validation (per integration guide)
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
        
        // Extract domain only (no protocol, no www) as per integration guide
        $site_url = get_site_url();
        $parsed = parse_url($site_url);
        $domain = $parsed['host'] ?? '';
        // Remove www. prefix if present
        $domain = preg_replace('/^www\./', '', $domain);
        
        $response = wp_remote_post($this->api_base_url . '/api/v1/domains', [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
                'Origin' => home_url(), // Required for domain validation (per integration guide)
            ],
            'body' => json_encode([
                'domain' => $domain,
            ]),
            'timeout' => 30,
        ]);
        
        // Handle 401 Unauthorized - try refreshing token once
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code === 401) {
            $refreshed = $this->oauth->refresh_token();
            if ($refreshed['success']) {
                $access_token = $this->oauth->get_access_token();
                // Retry request with new token
                $response = wp_remote_post($this->api_base_url . '/api/v1/domains', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $access_token,
                        'Content-Type' => 'application/json',
                        'Origin' => home_url(),
                    ],
                    'body' => json_encode([
                        'domain' => $domain,
                    ]),
                    'timeout' => 30,
                ]);
                $status_code = wp_remote_retrieve_response_code($response);
            }
        }
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message()
            ];
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['success']) && $data['success']) {
            // Create verification file as per integration guide
            $verification_token = $data['data']['verification_token'] ?? '';
            $verification_file = $data['data']['verification_file'] ?? '';
            
            if ($verification_token && $verification_file) {
                $file_path = ABSPATH . $verification_file;
                $file_created = file_put_contents($file_path, $verification_token);
                
                if ($file_created !== false) {
                    // Set file permissions (read-only as per guide)
                    chmod($file_path, 0644);
                    
                    // Automatically verify domain (per integration guide example)
                    $domain_id = $data['data']['id'] ?? null;
                    if ($domain_id) {
                        $verify_response = wp_remote_post($this->api_base_url . '/api/v1/domains/' . $domain_id . '/verify', [
                            'headers' => [
                                'Authorization' => 'Bearer ' . $access_token,
                                'Content-Type' => 'application/json',
                                'Origin' => home_url(),
                            ],
                            'timeout' => 30,
                        ]);
                        
                        // Check verification result
                        if (!is_wp_error($verify_response)) {
                            $verify_body = wp_remote_retrieve_body($verify_response);
                            $verify_data = json_decode($verify_body, true);
                            if (isset($verify_data['success']) && $verify_data['success']) {
                                update_option('wp_image_guardian_domain_approved', true);
                            }
                        }
                    }
                }
            }
            
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
