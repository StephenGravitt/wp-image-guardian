<?php

if (!defined('ABSPATH')) {
    exit;
}

class WP_Image_Guardian_TinyEye_API {
    
    private $api_base_url = 'https://api.tineye.com';
    private $testing_api_key = '6mm60lsCNIBqFwOWjJqA80QZHh9BMwc';
    private $testing_api_keys = [
        '6mm60lsCNIBqFwOWjJqA80QZHh9BMwc',
        '6mm60lsCNIBqFwOWjJqA80QZHh9BMwc-ber4u=t^', // Sandbox key format
    ];
    
    public function __construct() {
        // Allow base URL to be filtered
        $this->api_base_url = apply_filters('wp_image_guardian_tinyeye_api_base_url', $this->api_base_url);
    }
    
    /**
     * Log debug information
     */
    private function log_debug($function, $message, $data = []) {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        
        if (!defined('WP_DEBUG_LOG') || !WP_DEBUG_LOG) {
            return;
        }
        
        $log_message = sprintf(
            '[WP Image Guardian - %s] %s',
            $function,
            $message
        );
        
        if (!empty($data)) {
            $log_message .= ' | Data: ' . wp_json_encode($data, JSON_PRETTY_PRINT);
        }
        
        error_log($log_message);
    }
    
    /**
     * Get API key from constant or option
     * Priority: constant > option
     */
    public function get_api_key() {
        // Check for constant first
        if (defined('WP_IMAGE_GUARDIAN_TINEYE_API_KEY')) {
            return WP_IMAGE_GUARDIAN_TINEYE_API_KEY;
        }
        
        // Fall back to option
        $settings = get_option('wp_image_guardian_settings', []);
        return $settings['tinyeye_api_key'] ?? '';
    }
    
    /**
     * Validate API key by calling remaining_searches endpoint
     */
    public function validate_api_key($api_key = null) {
        if ($api_key === null) {
            $api_key = $this->get_api_key();
        }
        
        $this->log_debug('validate_api_key', 'Starting validation', [
            'api_key_length' => strlen($api_key ?? ''),
            'api_key_preview' => $api_key ? (substr($api_key, 0, 10) . '...' . substr($api_key, -4)) : 'empty',
        ]);
        
        if (empty($api_key)) {
            $this->log_debug('validate_api_key', 'API key is empty');
            return [
                'success' => false,
                'message' => __('API key is required', 'wp-image-guardian')
            ];
        }
        
        $result = $this->get_remaining_searches($api_key);
        
        $this->log_debug('validate_api_key', 'Validation result', [
            'success' => $result['success'] ?? false,
            'message' => $result['message'] ?? '',
            'remaining_searches' => $result['remaining_searches'] ?? null,
        ]);
        
        if ($result['success']) {
            return [
                'success' => true,
                'message' => __('API key is valid', 'wp-image-guardian'),
                'remaining_searches' => $result['remaining_searches']
            ];
        }
        
        return $result;
    }
    
    /**
     * Get remaining searches for API key
     */
    public function get_remaining_searches($api_key = null) {
        if ($api_key === null) {
            $api_key = $this->get_api_key();
        }
        
        if (empty($api_key)) {
            $this->log_debug('get_remaining_searches', 'API key is empty');
            return [
                'success' => false,
                'message' => __('API key is required', 'wp-image-guardian')
            ];
        }
        
        // TinyEye API uses x-api-key header (lowercase) per documentation
        // https://services.tineye.com/developers/tineyeapi/
        $endpoint = '/rest/remaining_searches/';
        $url = $this->api_base_url . $endpoint;
        
        $headers = [
            'x-api-key' => $api_key,
            'accept' => 'application/json',
        ];
        
        $this->log_debug('get_remaining_searches', 'Attempting request', [
            'url' => $url,
            'endpoint' => $endpoint,
            'api_key_length' => strlen($api_key),
            'api_key_preview' => substr($api_key, 0, 10) . '...' . substr($api_key, -4),
            'headers' => array_keys($headers),
        ]);
        
        $response = wp_remote_get($url, [
            'headers' => $headers,
            'timeout' => 30,
        ]);
        
        $status_code = null;
        $body = '';
        $response_headers = [];
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $this->log_debug('get_remaining_searches', 'WP_Error', [
                'error' => $error_message,
                'error_code' => $response->get_error_code(),
            ]);
            return [
                'success' => false,
                'message' => $error_message,
            ];
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $response_headers = wp_remote_retrieve_headers($response);
        
        $this->log_debug('get_remaining_searches', 'Response received', [
            'status_code' => $status_code,
            'body' => $body,
            'body_length' => strlen($body),
            'headers' => $response_headers->getAll(),
        ]);
        
        if ($status_code === 200) {
            $data = json_decode($body, true);
            $json_error = json_last_error();
            
            $this->log_debug('get_remaining_searches', 'Parsed response', [
                'json_error' => $json_error !== JSON_ERROR_NONE ? json_last_error_msg() : 'none',
                'parsed_data' => $data,
            ]);
            
            // Handle different response formats
            // TinyEye API returns: {"results": {"total_remaining_searches": 5000, "bundles": [...]}}
            if (isset($data['results']['total_remaining_searches'])) {
                $remaining = intval($data['results']['total_remaining_searches']);
                $this->log_debug('get_remaining_searches', 'Success - found total_remaining_searches', [
                    'remaining' => $remaining,
                ]);
                return [
                    'success' => true,
                    'remaining_searches' => $remaining
                ];
            } elseif (isset($data['results']['bundles'][0]['remaining_searches'])) {
                // Fallback to first bundle if total not available
                $remaining = intval($data['results']['bundles'][0]['remaining_searches']);
                $this->log_debug('get_remaining_searches', 'Success - found remaining_searches in bundle', [
                    'remaining' => $remaining,
                ]);
                return [
                    'success' => true,
                    'remaining_searches' => $remaining
                ];
            } elseif (isset($data['remaining_searches'])) {
                $this->log_debug('get_remaining_searches', 'Success - found remaining_searches', [
                    'remaining' => $data['remaining_searches'],
                ]);
                return [
                    'success' => true,
                    'remaining_searches' => intval($data['remaining_searches'])
                ];
            } elseif (isset($data['remaining'])) {
                $this->log_debug('get_remaining_searches', 'Success - found remaining', [
                    'remaining' => $data['remaining'],
                ]);
                return [
                    'success' => true,
                    'remaining_searches' => intval($data['remaining'])
                ];
            } elseif (is_numeric($body)) {
                // Some APIs return just the number
                $this->log_debug('get_remaining_searches', 'Success - numeric body', [
                    'remaining' => intval($body),
                ]);
                return [
                    'success' => true,
                    'remaining_searches' => intval($body)
                ];
            } else {
                $this->log_debug('get_remaining_searches', 'Unexpected response format', [
                    'body' => $body,
                    'data' => $data,
                ]);
            }
        }
        
        // Handle error responses
        $error_message = __('Failed to get remaining searches', 'wp-image-guardian');
        if ($status_code === 401 || $status_code === 403) {
            $error_message = __('Invalid API key', 'wp-image-guardian');
        } elseif ($status_code === 429) {
            $error_message = __('Rate limit exceeded', 'wp-image-guardian');
        }
        
        $this->log_debug('get_remaining_searches', 'Request failed', [
            'status_code' => $status_code,
            'body' => $body,
            'error_message' => $error_message,
        ]);
        
        return [
            'success' => false,
            'message' => $error_message,
            'status_code' => $status_code,
            'response_body' => $body,
        ];
    }
    
    /**
     * Perform reverse image search
     */
    public function search_image($image_url, $api_key = null) {
        if ($api_key === null) {
            $api_key = $this->get_api_key();
        }
        
        if (empty($api_key)) {
            return [
                'success' => false,
                'message' => __('API key is required', 'wp-image-guardian')
            ];
        }
        
        // Validate image URL
        if (!filter_var($image_url, FILTER_VALIDATE_URL)) {
            return [
                'success' => false,
                'message' => __('Invalid image URL', 'wp-image-guardian')
            ];
        }
        
        // Store original URL for logging
        $original_url = $image_url;
        
        // Convert URL for TinyEye (replace local URL with production URL if override is set)
        $image_url = WP_Image_Guardian_Helpers::convert_url_for_tinyeye($image_url);
        
        // Log URL conversion if it changed
        if ($original_url !== $image_url) {
            $this->log_debug('search_image', 'URL converted for TinyEye', [
                'original_url' => $original_url,
                'converted_url' => $image_url,
            ]);
        }
        
        // Check if we have remaining searches before making request
        $remaining = $this->get_remaining_searches($api_key);
        if (!$remaining['success'] || ($remaining['remaining_searches'] ?? 0) <= 0) {
            return [
                'success' => false,
                'message' => __('No remaining searches available', 'wp-image-guardian'),
                'remaining_searches' => 0
            ];
        }
        
        // TinyEye API uses GET with query parameters per documentation
        // https://services.tineye.com/developers/tineyeapi/
        $url = $this->api_base_url . '/rest/search/';
        
        // Build query parameters
        $query_args = [
            'image_url' => $image_url,
            'tags' => 'stock', // Filter for stock images
            'sort' => 'score', // Sort by match score
        ];
        
        $url = add_query_arg($query_args, $url);
        
        $headers = [
            'x-api-key' => $api_key,
            'accept' => 'application/json',
        ];
        
        $this->log_debug('search_image', 'Attempting search', [
            'url' => $url,
            'image_url' => $image_url,
            'api_key_length' => strlen($api_key),
            'api_key_preview' => substr($api_key, 0, 10) . '...' . substr($api_key, -4),
            'method' => 'GET',
        ]);
        
        $response = wp_remote_get($url, [
            'headers' => $headers,
            'timeout' => 60, // Longer timeout for image search
        ]);
        
        if (is_wp_error($response)) {
            $this->log_debug('search_image', 'WP_Error', [
                'error' => $response->get_error_message(),
                'error_code' => $response->get_error_code(),
            ]);
            return [
                'success' => false,
                'message' => $response->get_error_message()
            ];
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        $this->log_debug('search_image', 'Response received', [
            'status_code' => $status_code,
            'body_length' => strlen($body),
            'has_data' => !empty($data),
        ]);
        
        if ($status_code === 200 && $data) {
            // TinyEye API returns results in data['results']['matches'] format
            $matches = $data['results']['matches'] ?? [];
            $total_results = $data['stats']['total_filtered_results'] ?? count($matches);
            
            // Normalize response format
            $results = [
                'success' => true,
                'search_id' => null, // Not provided in GET response
                'total_results' => $total_results,
                'results' => $matches,
                'matches' => $matches,
                'match_percentage' => $this->calculate_match_percentage($data),
            ];
            
            // Include full response for storage
            $results['raw_response'] = $data;
            
            $this->log_debug('search_image', 'Search successful', [
                'total_results' => $total_results,
                'matches_count' => count($matches),
            ]);
            
            return $results;
        }
        
        // Handle error responses
        $error_message = __('Image search failed', 'wp-image-guardian');
        if ($status_code === 401 || $status_code === 403) {
            $error_message = __('Invalid API key', 'wp-image-guardian');
        } elseif ($status_code === 429) {
            $error_message = __('Rate limit exceeded', 'wp-image-guardian');
        } elseif ($status_code === 400) {
            // Try to get error message from response
            if (isset($data['messages']) && is_array($data['messages']) && !empty($data['messages'])) {
                $error_message = implode(', ', $data['messages']);
            } elseif (isset($data['message'])) {
                $error_message = $data['message'];
            } else {
                $error_message = __('Invalid request', 'wp-image-guardian');
            }
            
            $this->log_debug('search_image', 'Request failed with 400', [
                'body' => $body,
                'error_message' => $error_message,
            ]);
        }
        
        return [
            'success' => false,
            'message' => $error_message,
            'status_code' => $status_code,
            'response_body' => $body,
        ];
    }
    
    /**
     * Calculate match percentage from TinyEye response
     */
    private function calculate_match_percentage($data) {
        // TinyEye API returns matches in data['results']['matches']
        $matches = $data['results']['matches'] ?? $data['results'] ?? $data['matches'] ?? [];
        
        if (empty($matches)) {
            return 0;
        }
        
        // Extract scores from matches
        // TinyEye provides both 'score' and 'query_match_percent' fields
        $scores = [];
        foreach ($matches as $match) {
            // Prefer query_match_percent as it's the actual match percentage
            if (isset($match['query_match_percent'])) {
                $scores[] = floatval($match['query_match_percent']);
            } elseif (isset($match['score'])) {
                $scores[] = floatval($match['score']);
            } elseif (isset($match['percentage'])) {
                $scores[] = floatval($match['percentage']);
            } elseif (isset($match['match_percentage'])) {
                $scores[] = floatval($match['match_percentage']);
            }
        }
        
        if (!empty($scores)) {
            // Return highest match percentage
            return max($scores);
        }
        
        // Fallback: use total results as indicator
        $total = count($matches);
        if ($total === 0) {
            return 0;
        } elseif ($total <= 3) {
            return 30; // Low match
        } elseif ($total <= 10) {
            return 60; // Medium match
        } else {
            return 90; // High match
        }
    }
    
    /**
     * Check if API key is the testing key
     */
    public function is_testing_api_key($api_key = null) {
        if ($api_key === null) {
            $api_key = $this->get_api_key();
        }
        
        // Check against all known testing key formats
        if ($api_key === $this->testing_api_key) {
            return true;
        }
        
        // Check if it starts with the testing key prefix
        foreach ($this->testing_api_keys as $test_key) {
            if (strpos($api_key, $test_key) === 0 || $api_key === $test_key) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Mask API key for display (show only last 4 characters)
     */
    public function mask_api_key($api_key = null) {
        if ($api_key === null) {
            $api_key = $this->get_api_key();
        }
        
        if (empty($api_key) || strlen($api_key) <= 4) {
            return str_repeat('*', 12);
        }
        
        $length = strlen($api_key);
        $last_four = substr($api_key, -4);
        $masked = str_repeat('*', max(8, $length - 4));
        
        return $masked . $last_four;
    }
}

