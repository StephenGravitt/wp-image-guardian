# Image Guardian API - Third Party Integration Guide

This guide is for developers integrating WordPress plugins (or other applications) with the Image Guardian API service using OAuth2 authentication.

---

## Table of Contents

1. [Overview](#overview)
2. [Prerequisites](#prerequisites)
3. [Getting Started](#getting-started)
4. [OAuth2 Authentication Flow](#oauth2-authentication-flow)
5. [API Endpoints](#api-endpoints)
6. [Domain Verification](#domain-verification)
7. [Error Handling](#error-handling)
8. [Code Examples](#code-examples)
9. [Security Best Practices](#security-best-practices)
10. [Testing](#testing)

---

## Overview

The Image Guardian API is a Laravel-based service that provides reverse image search capabilities via the TinyEye API. It uses OAuth2 (Authorization Code flow with refresh tokens) for secure authentication.

### Key Features

- **OAuth2 Authentication** - Secure token-based authentication
- **Domain Verification** - Only authorized domains can access the API
- **Account Management** - Multi-tenant account system
- **Usage Tracking** - Track API usage and enforce limits
- **Subscription Management** - Free and premium tiers

---

## Prerequisites

Before integrating, you'll need:

1. **An Image Guardian Account** - Users must create an account at the Image Guardian service
2. **OAuth Client Credentials** - Each WordPress site needs its own OAuth client (created by the account owner)
3. **TinyEye API Key** - Users must provide their own TinyEye API key
4. **HTTPS** - All API communication must use HTTPS in production

---

## Getting Started

### Step 1: Create an OAuth Client

The account owner must create an OAuth client for each WordPress site:

1. Log in to the Image Guardian dashboard
2. Navigate to **OAuth Clients**
3. Click **Create New Client**
4. Provide:
   - **Name**: Descriptive name (e.g., "My WordPress Site")
   - **Redirect URIs**: Your WordPress callback URL(s)
     - Example: `https://yoursite.com/wp-admin/admin.php?page=image-guardian&action=oauth_callback`
     - You can add multiple URIs (comma-separated)
5. Click **Create**
6. **IMPORTANT**: Copy the `client_id` and `client_secret` immediately - the secret is only shown once!

### Step 2: Register Your Domain

Before making API requests, you must register and verify your domain:

1. In the Image Guardian dashboard, navigate to **Authorized Domains**
2. Click **Add Domain**
3. Enter your domain (e.g., `yoursite.com` - no protocol, no www)
4. Click **Register**
5. You'll receive a verification token and file name
6. Create the verification file in your WordPress root directory:
   - **File name**: `.ig-verify-{token}`
   - **File content**: The verification token (exact string)
7. Click **Verify Domain** in the dashboard
8. Once verified, your domain is authorized for API requests

### Step 3: Store Credentials Securely

Store the following in your WordPress plugin (encrypted if possible):

- `client_id` - OAuth client ID
- `client_secret` - OAuth client secret (encrypt before storing)
- `api_base_url` - Image Guardian API base URL (e.g., `https://api.imageguardian.com`)
- `redirect_uri` - Your callback URL (must match the one registered with the OAuth client)

**WordPress Storage Example:**
```php
// Store credentials
update_option('ig_oauth_client_id', 'your_client_id');
update_option('ig_oauth_client_secret', wp_encrypt('your_client_secret')); // Encrypt the secret
update_option('ig_api_base_url', 'https://api.imageguardian.com');
update_option('ig_redirect_uri', admin_url('admin.php?page=image-guardian&action=oauth_callback'));
```

---

## OAuth2 Authentication Flow

### Authorization Code Flow

The Image Guardian API uses the standard OAuth2 Authorization Code flow with refresh tokens.

#### Step 1: Initiate Authorization

Redirect the user to the authorization endpoint:

```php
$state = wp_create_nonce('ig_oauth');
set_transient('ig_oauth_state', $state, 600); // 10 minutes

$auth_url = add_query_arg([
    'client_id' => get_option('ig_oauth_client_id'),
    'redirect_uri' => get_option('ig_redirect_uri'),
    'response_type' => 'code',
    'scope' => 'read write tinyeye_manage domains_manage',
    'state' => $state,
], get_option('ig_api_base_url') . '/oauth/authorize');

wp_redirect($auth_url);
exit;
```

**Parameters:**
- `client_id` (required) - Your OAuth client ID
- `redirect_uri` (required) - Must exactly match a registered redirect URI
- `response_type` (required) - Must be `code`
- `scope` (optional) - Space-separated list of requested scopes:
  - `read` - Read access (default, always included)
  - `write` - Create/update searches and account data
  - `tinyeye_manage` - Manage TinyEye API credentials
  - `domains_manage` - Manage authorized domains
- `state` (required) - CSRF protection token

#### Step 2: Handle Authorization Callback

After the user authorizes, they'll be redirected back with an authorization code:

```php
// Verify state parameter
$state = get_transient('ig_oauth_state');
if (!$state || $state !== $_GET['state']) {
    wp_die('Invalid state parameter. Possible CSRF attack.');
}
delete_transient('ig_oauth_state');

// Check for errors
if (isset($_GET['error'])) {
    wp_die('Authorization failed: ' . esc_html($_GET['error']));
}

// Exchange authorization code for tokens
$response = wp_remote_post(get_option('ig_api_base_url') . '/oauth/token', [
    'body' => [
        'grant_type' => 'authorization_code',
        'client_id' => get_option('ig_oauth_client_id'),
        'client_secret' => wp_decrypt(get_option('ig_oauth_client_secret')),
        'code' => $_GET['code'],
        'redirect_uri' => get_option('ig_redirect_uri'),
    ],
]);

if (is_wp_error($response)) {
    wp_die('Token exchange failed: ' . $response->get_error_message());
}

$token_data = json_decode(wp_remote_retrieve_body($response), true);

if (!isset($token_data['access_token'])) {
    wp_die('Token exchange failed: Invalid response');
}

// Store tokens securely
update_option('ig_access_token', wp_encrypt($token_data['access_token']));
update_option('ig_refresh_token', wp_encrypt($token_data['refresh_token']));
update_option('ig_token_expires_at', time() + $token_data['expires_in']);
update_option('ig_last_token_refresh', time());

// Redirect to success page
wp_redirect(admin_url('admin.php?page=image-guardian&connected=1'));
exit;
```

#### Step 3: Use Access Token for API Requests

Include the access token in the `Authorization` header:

```php
$access_token = wp_decrypt(get_option('ig_access_token'));

$response = wp_remote_post(get_option('ig_api_base_url') . '/api/v1/search/reverse', [
    'headers' => [
        'Authorization' => 'Bearer ' . $access_token,
        'Content-Type' => 'application/json',
        'Origin' => home_url(), // Required for domain validation
    ],
    'body' => json_encode([
        'image_url' => 'https://example.com/image.jpg',
        'tinyeye_api_key' => 'user_provided_tinyeye_key',
    ]),
]);
```

#### Step 4: Refresh Expired Tokens

Access tokens expire after 1 hour. Automatically refresh when expired:

```php
function ig_get_access_token() {
    $encrypted_token = get_option('ig_access_token');
    $expires_at = get_option('ig_token_expires_at');
    
    // Check if token is expired (with 5 minute buffer)
    if (!$encrypted_token || !$expires_at || time() >= ($expires_at - 300)) {
        ig_refresh_access_token();
        $encrypted_token = get_option('ig_access_token');
    }
    
    return $encrypted_token ? wp_decrypt($encrypted_token) : null;
}

function ig_refresh_access_token() {
    $refresh_token = wp_decrypt(get_option('ig_refresh_token'));
    
    if (!$refresh_token) {
        // No refresh token, need to re-authorize
        ig_initiate_authorization();
        return;
    }
    
    $response = wp_remote_post(get_option('ig_api_base_url') . '/oauth/token', [
        'body' => [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refresh_token,
            'client_id' => get_option('ig_oauth_client_id'),
            'client_secret' => wp_decrypt(get_option('ig_oauth_client_secret')),
        ],
    ]);
    
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        // Refresh failed, need to re-authorize
        delete_option('ig_access_token');
        delete_option('ig_refresh_token');
        ig_initiate_authorization();
        return;
    }
    
    $token_data = json_decode(wp_remote_retrieve_body($response), true);
    
    update_option('ig_access_token', wp_encrypt($token_data['access_token']));
    if (isset($token_data['refresh_token'])) {
        update_option('ig_refresh_token', wp_encrypt($token_data['refresh_token']));
    }
    update_option('ig_token_expires_at', time() + $token_data['expires_in']);
    update_option('ig_last_token_refresh', time());
}
```

---

## API Endpoints

### Base URL

All API requests should be made to: `https://api.imageguardian.com` (or your configured base URL)

### Authentication

All API endpoints (except public endpoints) require authentication via Bearer token:

```
Authorization: Bearer {access_token}
```

Additionally, for WordPress plugin endpoints, include the `Origin` header:

```
Origin: https://yoursite.com
```

### Available Endpoints

#### Search Endpoints

**POST `/api/v1/search/reverse`**
Perform a reverse image search.

**Request:**
```json
{
  "image_url": "https://example.com/image.jpg",
  "tinyeye_api_key": "user_provided_tinyeye_key"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "search_id": 123,
    "results_count": 5,
    "matches": [...]
  }
}
```

**GET `/api/v1/search/history`**
Get search history for the account.

**GET `/api/v1/search/{searchId}`**
Get details of a specific search.

**GET `/api/v1/search/usage/stats`**
Get account usage statistics.

#### Account Endpoints

**GET `/api/v1/account`**
Get account information.

**PUT `/api/v1/account`**
Update account information.

#### Domain Management Endpoints

**GET `/api/v1/domains`**
List authorized domains (requires `domains_manage` scope).

**POST `/api/v1/domains`**
Register a new domain (requires `domains_manage` scope).

**Request:**
```json
{
  "domain": "yoursite.com",
  "notes": "Optional notes"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "domain": "yoursite.com",
    "verification_token": "abc123...",
    "verification_file": ".ig-verify-abc123...",
    "verification_url": "https://yoursite.com/.ig-verify-abc123..."
  }
}
```

**POST `/api/v1/domains/{id}/verify`**
Verify domain ownership (requires `domains_manage` scope).

**PUT `/api/v1/domains/{id}`**
Update domain settings (requires `domains_manage` scope).

**DELETE `/api/v1/domains/{id}`**
Remove a domain (requires `domains_manage` scope).

#### OAuth Client Management

**GET `/api/v1/oauth-clients`**
List OAuth clients for the account.

**POST `/api/v1/oauth-clients`**
Create a new OAuth client.

**Request:**
```json
{
  "name": "My WordPress Site",
  "redirect_uris": [
    "https://yoursite.com/wp-admin/admin.php?page=image-guardian&action=oauth_callback"
  ]
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": "client-uuid",
    "secret": "client-secret-show-only-once",
    "name": "My WordPress Site",
    "redirect_uris": [...]
  }
}
```

**GET `/api/v1/oauth-clients/{id}`**
Get OAuth client details.

**PUT `/api/v1/oauth-clients/{id}`**
Update OAuth client.

**DELETE `/api/v1/oauth-clients/{id}`**
Revoke OAuth client (invalidates all tokens).

#### OAuth Endpoints (Passport)

**GET `/oauth/authorize`**
Authorization endpoint (redirect users here).

**POST `/oauth/token`**
Token exchange and refresh endpoint.

**GET `/api/v1/oauth/scopes`**
Get available OAuth scopes.

#### WordPress Plugin Endpoints

**POST `/api/v1/plugin/search`**
Simplified search endpoint for WordPress plugins.

**GET `/api/v1/plugin/status`**
Get plugin status and account information.

---

## Domain Verification

### Why Domain Verification?

Domain verification ensures that only authorized WordPress sites can access the API, preventing unauthorized use of account credentials.

### Verification Process

1. **Register Domain** - Account owner registers domain via API or dashboard
2. **Receive Token** - System generates a unique verification token
3. **Create Verification File** - Place file in WordPress root directory:
   - **File name**: `.ig-verify-{token}`
   - **File content**: The verification token (exact string, no extra whitespace)
4. **Verify Domain** - Call the verify endpoint or click "Verify" in dashboard
5. **System Checks** - System attempts to fetch the verification file via HTTPS
6. **Domain Authorized** - Once verified, domain is authorized for API requests

### Verification File Example

**File**: `.ig-verify-abc123def456...`
**Content**: `abc123def456...` (exact token string)

**WordPress Implementation:**
```php
function ig_create_verification_file($token) {
    $file_path = ABSPATH . '.ig-verify-' . $token;
    file_put_contents($file_path, $token);
    
    // Optionally set permissions
    chmod($file_path, 0644);
}
```

### Automatic Domain Registration

You can automatically register the current WordPress site's domain:

```php
function ig_register_domain() {
    $domain = parse_url(home_url(), PHP_URL_HOST);
    $access_token = ig_get_access_token();
    
    $response = wp_remote_post(get_option('ig_api_base_url') . '/api/v1/domains', [
        'headers' => [
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json',
        ],
        'body' => json_encode([
            'domain' => $domain,
        ]),
    ]);
    
    $data = json_decode(wp_remote_retrieve_body($response), true);
    
    if ($data['success']) {
        $token = $data['data']['verification_token'];
        
        // Create verification file
        $file_path = ABSPATH . '.ig-verify-' . $token;
        file_put_contents($file_path, $token);
        
        // Notify system that file is ready
        wp_remote_post(get_option('ig_api_base_url') . '/api/v1/domains/' . $data['data']['id'] . '/verify', [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
            ],
        ]);
    }
}
```

---

## Error Handling

### HTTP Status Codes

- `200` - Success
- `201` - Created
- `400` - Bad Request (validation errors)
- `401` - Unauthorized (invalid or expired token)
- `403` - Forbidden (domain not authorized, insufficient permissions)
- `404` - Not Found
- `422` - Validation Error
- `429` - Too Many Requests (rate limited)
- `500` - Server Error

### Error Response Format

```json
{
  "success": false,
  "message": "Error description",
  "error_code": "ERROR_CODE",
  "errors": {
    "field_name": ["Validation error message"]
  }
}
```

### Common Error Codes

- `DOMAIN_NOT_AUTHORIZED` - Domain not registered or verified
- `LIMIT_EXCEEDED` - Account API limit exceeded
- `TOKEN_EXPIRED` - Access token expired (refresh required)
- `INVALID_TOKEN` - Invalid access token
- `VALIDATION_ERROR` - Request validation failed

### Handling Token Expiration

Always check for `401 Unauthorized` responses and refresh the token:

```php
function ig_make_api_request($endpoint, $args = []) {
    $access_token = ig_get_access_token();
    
    $response = wp_remote_request(get_option('ig_api_base_url') . $endpoint, array_merge([
        'headers' => [
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json',
            'Origin' => home_url(),
        ],
    ], $args));
    
    $status_code = wp_remote_retrieve_response_code($response);
    
    // Handle token expiration
    if ($status_code === 401) {
        // Try refreshing token once
        ig_refresh_access_token();
        $access_token = ig_get_access_token();
        
        // Retry request
        $response = wp_remote_request(get_option('ig_api_base_url') . $endpoint, array_merge([
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
                'Origin' => home_url(),
            ],
        ], $args));
        
        $status_code = wp_remote_retrieve_response_code($response);
    }
    
    if ($status_code >= 400) {
        $error_data = json_decode(wp_remote_retrieve_body($response), true);
        // Handle error
        return new WP_Error('api_error', $error_data['message'] ?? 'API request failed', $error_data);
    }
    
    return json_decode(wp_remote_retrieve_body($response), true);
}
```

---

## Code Examples

### Complete WordPress Plugin Integration

```php
<?php
/**
 * Plugin Name: Image Guardian Integration
 * Description: Integrate with Image Guardian API
 */

class Image_Guardian_Integration {
    
    private $api_base_url = 'https://api.imageguardian.com';
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'handle_oauth_callback']);
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'Image Guardian',
            'Image Guardian',
            'manage_options',
            'image-guardian',
            [$this, 'render_settings_page']
        );
    }
    
    public function render_settings_page() {
        $connected = get_option('ig_access_token') ? true : false;
        
        ?>
        <div class="wrap">
            <h1>Image Guardian Settings</h1>
            
            <?php if (!$connected): ?>
                <p>Click the button below to connect your WordPress site to Image Guardian.</p>
                <a href="<?php echo esc_url($this->get_authorization_url()); ?>" class="button button-primary">
                    Connect to Image Guardian
                </a>
            <?php else: ?>
                <p>✅ Connected to Image Guardian</p>
                <p>Account: <?php echo esc_html($this->get_account_name()); ?></p>
                <a href="<?php echo esc_url($this->get_disconnect_url()); ?>" class="button">
                    Disconnect
                </a>
            <?php endif; ?>
        </div>
        <?php
    }
    
    private function get_authorization_url() {
        $state = wp_create_nonce('ig_oauth');
        set_transient('ig_oauth_state', $state, 600);
        
        return add_query_arg([
            'client_id' => get_option('ig_oauth_client_id'),
            'redirect_uri' => admin_url('admin.php?page=image-guardian&action=oauth_callback'),
            'response_type' => 'code',
            'scope' => 'read write tinyeye_manage domains_manage',
            'state' => $state,
        ], $this->api_base_url . '/oauth/authorize');
    }
    
    public function handle_oauth_callback() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'image-guardian') {
            return;
        }
        
        if (!isset($_GET['action']) || $_GET['action'] !== 'oauth_callback') {
            return;
        }
        
        // Verify state
        $state = get_transient('ig_oauth_state');
        if (!$state || $state !== $_GET['state']) {
            wp_die('Invalid state parameter.');
        }
        delete_transient('ig_oauth_state');
        
        // Exchange code for tokens
        $response = wp_remote_post($this->api_base_url . '/oauth/token', [
            'body' => [
                'grant_type' => 'authorization_code',
                'client_id' => get_option('ig_oauth_client_id'),
                'client_secret' => wp_decrypt(get_option('ig_oauth_client_secret')),
                'code' => $_GET['code'],
                'redirect_uri' => admin_url('admin.php?page=image-guardian&action=oauth_callback'),
            ],
        ]);
        
        $token_data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($token_data['access_token'])) {
            update_option('ig_access_token', wp_encrypt($token_data['access_token']));
            update_option('ig_refresh_token', wp_encrypt($token_data['refresh_token']));
            update_option('ig_token_expires_at', time() + $token_data['expires_in']);
            
            // Register and verify domain
            $this->register_domain();
            
            wp_redirect(admin_url('admin.php?page=image-guardian&connected=1'));
            exit;
        }
    }
    
    private function register_domain() {
        $domain = parse_url(home_url(), PHP_URL_HOST);
        $access_token = wp_decrypt(get_option('ig_access_token'));
        
        $response = wp_remote_post($this->api_base_url . '/api/v1/domains', [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'domain' => $domain,
            ]),
        ]);
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($data['success']) {
            $token = $data['data']['verification_token'];
            $file_path = ABSPATH . '.ig-verify-' . $token;
            file_put_contents($file_path, $token);
            
            wp_remote_post($this->api_base_url . '/api/v1/domains/' . $data['data']['id'] . '/verify', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                ],
            ]);
        }
    }
    
    private function get_account_name() {
        $response = $this->make_api_request('/api/v1/account');
        return $response['data']['name'] ?? 'Unknown';
    }
    
    private function make_api_request($endpoint, $args = []) {
        $access_token = $this->get_access_token();
        
        $response = wp_remote_request($this->api_base_url . $endpoint, array_merge([
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
                'Origin' => home_url(),
            ],
        ], $args));
        
        if (wp_remote_retrieve_response_code($response) === 401) {
            $this->refresh_access_token();
            $access_token = $this->get_access_token();
            
            $response = wp_remote_request($this->api_base_url . $endpoint, array_merge([
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json',
                    'Origin' => home_url(),
                ],
            ], $args));
        }
        
        return json_decode(wp_remote_retrieve_body($response), true);
    }
    
    private function get_access_token() {
        $encrypted_token = get_option('ig_access_token');
        $expires_at = get_option('ig_token_expires_at');
        
        if (!$encrypted_token || !$expires_at || time() >= ($expires_at - 300)) {
            $this->refresh_access_token();
            $encrypted_token = get_option('ig_access_token');
        }
        
        return $encrypted_token ? wp_decrypt($encrypted_token) : null;
    }
    
    private function refresh_access_token() {
        $refresh_token = wp_decrypt(get_option('ig_refresh_token'));
        
        $response = wp_remote_post($this->api_base_url . '/oauth/token', [
            'body' => [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refresh_token,
                'client_id' => get_option('ig_oauth_client_id'),
                'client_secret' => wp_decrypt(get_option('ig_oauth_client_secret')),
            ],
        ]);
        
        $token_data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($token_data['access_token'])) {
            update_option('ig_access_token', wp_encrypt($token_data['access_token']));
            if (isset($token_data['refresh_token'])) {
                update_option('ig_refresh_token', wp_encrypt($token_data['refresh_token']));
            }
            update_option('ig_token_expires_at', time() + $token_data['expires_in']);
        }
    }
}

new Image_Guardian_Integration();
```

---

## Security Best Practices

1. **Encrypt Sensitive Data**
   - Always encrypt `client_secret` and tokens before storing
   - Use WordPress encryption functions: `wp_encrypt()` / `wp_decrypt()`

2. **Validate State Parameter**
   - Always validate the `state` parameter in OAuth callbacks
   - Use WordPress nonces for additional security

3. **Use HTTPS**
   - All API communication must use HTTPS
   - Never send credentials over HTTP

4. **Store Tokens Securely**
   - Use WordPress options API with encryption
   - Consider using WordPress Transients API for temporary data

5. **Validate Redirect URIs**
   - Ensure redirect URIs match exactly (including trailing slashes)
   - Use absolute URLs

6. **Handle Token Expiration**
   - Always check token expiration before making requests
   - Implement automatic token refresh

7. **Domain Verification**
   - Always register and verify domains before making API requests
   - Keep verification files secure (read-only)

8. **Error Handling**
   - Never expose sensitive error messages to end users
   - Log errors securely for debugging

---

## Testing

### Development Environment

For development/testing, you can disable domain validation by setting:

```php
// In your Laravel .env file
OAUTH_SKIP_DOMAIN_VALIDATION=true
```

**Note**: Never use this in production!

### Test OAuth Flow

1. Create a test OAuth client
2. Use a local development URL as redirect URI (e.g., `http://localhost:8080/wp-admin/...`)
3. Test the full authorization flow
4. Verify token refresh works
5. Test API requests with valid tokens
6. Test error handling (expired tokens, invalid requests, etc.)

### Test Domain Verification

1. Register a test domain
2. Create verification file
3. Verify domain
4. Test API requests from verified domain
5. Test that unverified domains are rejected

---

## Support

For integration support, please contact:
- **Email**: support@imageguardian.com
- **Documentation**: https://docs.imageguardian.com
- **API Status**: https://status.imageguardian.com

---

## Changelog

### Version 1.0.0
- Initial release
- OAuth2 authentication
- Domain verification
- Search API endpoints
- Account management

---

**Last Updated**: November 2025

