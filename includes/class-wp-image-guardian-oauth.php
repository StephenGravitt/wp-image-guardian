<?php

if (!defined('ABSPATH')) {
    exit;
}

class WP_Image_Guardian_OAuth {
    
    private $settings;
    private $api_base_url;
    
    public function __construct() {
        $this->settings = get_option('wp_image_guardian_settings', []);
        $this->api_base_url = WP_IMAGE_GUARDIAN_API_BASE_URL;
    }
    
    public function init() {
        add_action('admin_init', [$this, 'handle_oauth_redirect']);
    }
    
    public function get_authorization_url() {
        $client_id = $this->settings['oauth_client_id'] ?? '';
        $redirect_uri = admin_url('admin-ajax.php?action=wp_image_guardian_oauth_callback');
        $state = wp_generate_password(32, false);
        
        // Store state for validation
        set_transient('wp_image_guardian_oauth_state', $state, 600);
        
        $params = [
            'client_id' => $client_id,
            'redirect_uri' => $redirect_uri,
            'response_type' => 'code',
            'scope' => 'read write tinyeye_manage domains_manage',
            'state' => $state,
        ];
        
        return $this->api_base_url . '/oauth/authorize?' . http_build_query($params);
    }
    
    public function handle_oauth_redirect() {
        // Validate GET parameters
        if (!isset($_GET['page']) || $_GET['page'] !== 'wp-image-guardian') {
            return;
        }
        
        if (!isset($_GET['action']) || $_GET['action'] !== 'oauth') {
            return;
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'wp-image-guardian'), __('Error', 'wp-image-guardian'), ['response' => 403]);
        }
        
        $auth_url = $this->get_authorization_url();
        
        // Validate URL before redirecting
        if (!filter_var($auth_url, FILTER_VALIDATE_URL)) {
            wp_die(__('Invalid authorization URL', 'wp-image-guardian'), __('Error', 'wp-image-guardian'), ['response' => 500]);
        }
        
        wp_safe_redirect($auth_url);
        exit;
    }
    
    public function handle_callback($code, $state) {
        // Validate state
        $stored_state = get_transient('wp_image_guardian_oauth_state');
        if (!$stored_state || $stored_state !== $state) {
            return [
                'success' => false,
                'message' => __('Invalid OAuth state', 'wp-image-guardian')
            ];
        }
        
        // Exchange code for token
        $token_data = $this->exchange_code_for_token($code);
        
        if ($token_data['success']) {
            // Store tokens
            update_option('wp_image_guardian_oauth_tokens', $token_data['data']);
            
            // Get user info
            $user_info = $this->fetch_user_info($token_data['data']['access_token']);
            
            if ($user_info['success']) {
                update_option('wp_image_guardian_user_info', $user_info['data']);
            }
            
            return [
                'success' => true,
                'message' => __('Successfully connected to Image Guardian', 'wp-image-guardian')
            ];
        }
        
        return [
            'success' => false,
            'message' => $token_data['message'] ?? __('OAuth authentication failed', 'wp-image-guardian')
        ];
    }
    
    private function exchange_code_for_token($code) {
        $client_id = $this->settings['oauth_client_id'] ?? '';
        $client_secret = $this->settings['oauth_client_secret'] ?? '';
        $redirect_uri = admin_url('admin-ajax.php?action=wp_image_guardian_oauth_callback');
        
        $response = wp_remote_post($this->api_base_url . '/oauth/token', [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'grant_type' => 'authorization_code',
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'code' => $code,
                'redirect_uri' => $redirect_uri,
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
            return [
                'success' => true,
                'data' => $data['data']
            ];
        }
        
        return [
            'success' => false,
            'message' => $data['message'] ?? __('Token exchange failed', 'wp-image-guardian')
        ];
    }
    
    private function fetch_user_info($access_token) {
        $response = wp_remote_get($this->api_base_url . '/account', [
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
            'message' => $data['message'] ?? __('Failed to get user info', 'wp-image-guardian')
        ];
    }
    
    public function is_connected() {
        $tokens = get_option('wp_image_guardian_oauth_tokens', []);
        return !empty($tokens['access_token']);
    }
    
    public function get_access_token() {
        $tokens = get_option('wp_image_guardian_oauth_tokens', []);
        
        if (empty($tokens['access_token'])) {
            return null;
        }
        
        // Check if token is expired
        if (isset($tokens['expires_at']) && $tokens['expires_at'] < time()) {
            // Try to refresh token
            $refreshed = $this->refresh_token();
            if ($refreshed['success']) {
                return $refreshed['data']['access_token'];
            }
            return null;
        }
        
        return $tokens['access_token'];
    }
    
    private function refresh_token() {
        $tokens = get_option('wp_image_guardian_oauth_tokens', []);
        
        if (empty($tokens['refresh_token'])) {
            return ['success' => false, 'message' => 'No refresh token available'];
        }
        
        $client_id = $this->settings['oauth_client_id'] ?? '';
        $client_secret = $this->settings['oauth_client_secret'] ?? '';
        
        $response = wp_remote_post($this->api_base_url . '/oauth/token', [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'grant_type' => 'refresh_token',
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'refresh_token' => $tokens['refresh_token'],
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
            update_option('wp_image_guardian_oauth_tokens', $data['data']);
            return [
                'success' => true,
                'data' => $data['data']
            ];
        }
        
        return [
            'success' => false,
            'message' => $data['message'] ?? __('Token refresh failed', 'wp-image-guardian')
        ];
    }
    
    public function disconnect() {
        delete_option('wp_image_guardian_oauth_tokens');
        delete_option('wp_image_guardian_user_info');
    }
    
    public function get_user_info() {
        return get_option('wp_image_guardian_user_info', []);
    }
}
