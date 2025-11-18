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
        // Try to get client_id from settings, or use empty string (service may provide public client)
        $client_id = $this->settings['oauth_client_id'] ?? '';
        $redirect_uri = admin_url('admin-ajax.php?action=wp_image_guardian_oauth_callback');
        $state = wp_generate_password(32, false);
        
        // Store state for validation
        set_transient('wp_image_guardian_oauth_state', $state, 600);
        
        $params = [
            'redirect_uri' => $redirect_uri,
            'response_type' => 'code',
            'scope' => 'read write tinyeye_manage domains_manage',
            'state' => $state,
        ];
        
        // Only include client_id if it's set (service may use public client or detect from redirect_uri)
        if (!empty($client_id)) {
            $params['client_id'] = $client_id;
        }
        
        return $this->api_base_url . '/api/v1/oauth/authorize?' . http_build_query($params);
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
            // Encrypt tokens before storing (per integration guide security best practices)
            $token_data_to_store = $token_data['data'];
            if (function_exists('wp_encrypt')) {
                if (isset($token_data_to_store['access_token'])) {
                    $token_data_to_store['access_token'] = wp_encrypt($token_data_to_store['access_token']);
                }
                if (isset($token_data_to_store['refresh_token'])) {
                    $token_data_to_store['refresh_token'] = wp_encrypt($token_data_to_store['refresh_token']);
                }
            }
            // Store expires_at timestamp
            if (isset($token_data['data']['expires_in'])) {
                $token_data_to_store['expires_at'] = time() + $token_data['data']['expires_in'];
            }
            
            // Store tokens
            update_option('wp_image_guardian_oauth_tokens', $token_data_to_store);
            
            // Get user info (use unencrypted token for API call)
            $user_info = $this->fetch_user_info($token_data['data']['access_token']);
            
            if ($user_info['success']) {
                update_option('wp_image_guardian_user_info', $user_info['data']);
            }
            
            // Fetch plugin status to get subscription plan, TinyEye key, etc.
            // Note: We can't use WP_Image_Guardian_API here as it would create circular dependency
            // So we'll make the API call directly
            $account_status = $this->fetch_plugin_status($token_data['data']['access_token']);
            
            if ($account_status['success'] && isset($account_status['data'])) {
                $status_data = $account_status['data'];
                
                // Store subscription plan from API
                if (isset($status_data['subscription_plan']) || isset($status_data['subscription_status'])) {
                    $subscription_plan = $status_data['subscription_plan'] ?? $status_data['subscription_status'] ?? 'free';
                    $settings = get_option('wp_image_guardian_settings', []);
                    $settings['subscription_plan'] = $subscription_plan;
                    update_option('wp_image_guardian_settings', $settings);
                }
                
                // Store TinyEye API key if provided by API
                if (isset($status_data['tinyeye_api_key']) && !empty($status_data['tinyeye_api_key'])) {
                    $settings = get_option('wp_image_guardian_settings', []);
                    $settings['tinyeye_api_key'] = $status_data['tinyeye_api_key'];
                    update_option('wp_image_guardian_settings', $settings);
                }
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
        
        // Decrypt client_secret if encrypted (per integration guide)
        if (function_exists('wp_decrypt') && is_string($client_secret) && strlen($client_secret) > 50) {
            $decrypted = wp_decrypt($client_secret);
            if ($decrypted !== false) {
                $client_secret = $decrypted;
            }
        }
        
        $redirect_uri = admin_url('admin-ajax.php?action=wp_image_guardian_oauth_callback');
        
        $token_params = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirect_uri,
        ];
        
        // Only include client credentials if they're set (service may use public client)
        if (!empty($client_id)) {
            $token_params['client_id'] = $client_id;
        }
        if (!empty($client_secret)) {
            $token_params['client_secret'] = $client_secret;
        }
        
        $response = wp_remote_post($this->api_base_url . '/api/v1/oauth/token', [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($token_params),
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
        $response = wp_remote_get($this->api_base_url . '/api/v1/account', [
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
            'message' => $data['message'] ?? __('Failed to get user info', 'wp-image-guardian')
        ];
    }
    
    private function fetch_plugin_status($access_token) {
        $response = wp_remote_get($this->api_base_url . '/api/v1/plugin/status', [
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
            'message' => $data['message'] ?? __('Failed to get plugin status', 'wp-image-guardian')
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
        
        // Decrypt token if encrypted
        $access_token = $tokens['access_token'];
        if (function_exists('wp_decrypt') && is_string($access_token) && strlen($access_token) > 50) {
            // Likely encrypted, try to decrypt
            $decrypted = wp_decrypt($access_token);
            if ($decrypted !== false) {
                $access_token = $decrypted;
            }
        }
        
        // Check if token is expired (with 5 minute buffer as per integration guide)
        $expires_at = isset($tokens['expires_at']) ? $tokens['expires_at'] : null;
        if ($expires_at && time() >= ($expires_at - 300)) {
            // Try to refresh token
            $refreshed = $this->refresh_token();
            if ($refreshed['success']) {
                $tokens = get_option('wp_image_guardian_oauth_tokens', []);
                $access_token = $tokens['access_token'];
                // Decrypt if encrypted
                if (function_exists('wp_decrypt') && is_string($access_token) && strlen($access_token) > 50) {
                    $decrypted = wp_decrypt($access_token);
                    if ($decrypted !== false) {
                        $access_token = $decrypted;
                    }
                }
                return $access_token;
            }
            return null;
        }
        
        return $access_token;
    }
    
    public function refresh_token() {
        $tokens = get_option('wp_image_guardian_oauth_tokens', []);
        
        if (empty($tokens['refresh_token'])) {
            return ['success' => false, 'message' => 'No refresh token available'];
        }
        
        $client_id = $this->settings['oauth_client_id'] ?? '';
        $client_secret = $this->settings['oauth_client_secret'] ?? '';
        
        // Decrypt client_secret and refresh_token if encrypted
        if (function_exists('wp_decrypt')) {
            if (is_string($client_secret) && strlen($client_secret) > 50) {
                $decrypted = wp_decrypt($client_secret);
                if ($decrypted !== false) {
                    $client_secret = $decrypted;
                }
            }
            
            $refresh_token = $tokens['refresh_token'];
            if (is_string($refresh_token) && strlen($refresh_token) > 50) {
                $decrypted = wp_decrypt($refresh_token);
                if ($decrypted !== false) {
                    $refresh_token = $decrypted;
                }
            } else {
                $refresh_token = $tokens['refresh_token'];
            }
        } else {
            $refresh_token = $tokens['refresh_token'];
        }
        
        $response = wp_remote_post($this->api_base_url . '/api/v1/oauth/token', [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'grant_type' => 'refresh_token',
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'refresh_token' => $refresh_token,
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
            // Encrypt tokens before storing
            $token_data_to_store = $data['data'];
            if (function_exists('wp_encrypt')) {
                if (isset($token_data_to_store['access_token'])) {
                    $token_data_to_store['access_token'] = wp_encrypt($token_data_to_store['access_token']);
                }
                if (isset($token_data_to_store['refresh_token'])) {
                    $token_data_to_store['refresh_token'] = wp_encrypt($token_data_to_store['refresh_token']);
                }
            }
            // Store expires_at timestamp
            if (isset($data['data']['expires_in'])) {
                $token_data_to_store['expires_at'] = time() + $data['data']['expires_in'];
            }
            
            update_option('wp_image_guardian_oauth_tokens', $token_data_to_store);
            return [
                'success' => true,
                'data' => $data['data'] // Return unencrypted for immediate use
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
