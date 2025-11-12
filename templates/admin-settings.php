<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Image Guardian Settings', 'wp-image-guardian'); ?></h1>
    
    <form method="post" action="">
        <?php wp_nonce_field('wp_image_guardian_settings', 'wp_image_guardian_nonce'); ?>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="api_base_url"><?php _e('API Base URL', 'wp-image-guardian'); ?></label>
                </th>
                <td>
                    <input type="url" id="api_base_url" name="api_base_url" 
                           value="<?php echo esc_attr($settings['api_base_url'] ?? ''); ?>" 
                           class="regular-text" required />
                    <p class="description">
                        <?php _e('The base URL for the Image Guardian API service.', 'wp-image-guardian'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="oauth_client_id"><?php _e('OAuth Client ID', 'wp-image-guardian'); ?></label>
                </th>
                <td>
                    <input type="text" id="oauth_client_id" name="oauth_client_id" 
                           value="<?php echo esc_attr($settings['oauth_client_id'] ?? ''); ?>" 
                           class="regular-text" />
                    <p class="description">
                        <?php _e('Your OAuth client ID from Image Guardian.', 'wp-image-guardian'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="oauth_client_secret"><?php _e('OAuth Client Secret', 'wp-image-guardian'); ?></label>
                </th>
                <td>
                    <input type="password" id="oauth_client_secret" name="oauth_client_secret" 
                           value="<?php echo esc_attr($settings['oauth_client_secret'] ?? ''); ?>" 
                           class="regular-text" />
                    <p class="description">
                        <?php _e('Your OAuth client secret from Image Guardian.', 'wp-image-guardian'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="tinyeye_api_key"><?php _e('TinyEye API Key', 'wp-image-guardian'); ?></label>
                </th>
                <td>
                    <input type="password" id="tinyeye_api_key" name="tinyeye_api_key" 
                           value="<?php echo esc_attr($settings['tinyeye_api_key'] ?? ''); ?>" 
                           class="regular-text" />
                    <p class="description">
                        <?php _e('Your TinyEye API key. Get one from', 'wp-image-guardian'); ?> 
                        <a href="https://tineye.com" target="_blank">tineye.com</a>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="subscription_plan"><?php _e('Subscription Plan', 'wp-image-guardian'); ?></label>
                </th>
                <td>
                    <select id="subscription_plan" name="subscription_plan">
                        <option value="free" <?php selected($settings['subscription_plan'] ?? 'free', 'free'); ?>>
                            <?php _e('Free (1 check)', 'wp-image-guardian'); ?>
                        </option>
                        <option value="premium_monthly" <?php selected($settings['subscription_plan'] ?? '', 'premium_monthly'); ?>>
                            <?php _e('Premium Monthly ($5/month)', 'wp-image-guardian'); ?>
                        </option>
                        <option value="premium_yearly" <?php selected($settings['subscription_plan'] ?? '', 'premium_yearly'); ?>>
                            <?php _e('Premium Yearly ($50/year)', 'wp-image-guardian'); ?>
                        </option>
                    </select>
                    <p class="description">
                        <?php _e('Your current subscription plan.', 'wp-image-guardian'); ?>
                    </p>
                </td>
            </tr>
        </table>
        
        <h2><?php _e('OAuth Connection', 'wp-image-guardian'); ?></h2>
        
        <?php if ($oauth_connected): ?>
            <div class="notice notice-success">
                <p><strong><?php _e('Connected to Image Guardian', 'wp-image-guardian'); ?></strong></p>
                <?php if ($user_info): ?>
                    <p><?php printf(__('Account: %s', 'wp-image-guardian'), $user_info['name'] ?? 'Unknown'); ?></p>
                    <p><?php printf(__('Plan: %s', 'wp-image-guardian'), $user_info['subscription_plan'] ?? 'Unknown'); ?></p>
                <?php endif; ?>
                <p>
                    <a href="<?php echo admin_url('upload.php?page=wp-image-guardian'); ?>" class="button">
                        <?php _e('Go to Dashboard', 'wp-image-guardian'); ?>
                    </a>
                    <button type="button" id="disconnect-oauth" class="button button-secondary">
                        <?php _e('Disconnect', 'wp-image-guardian'); ?>
                    </button>
                </p>
            </div>
        <?php else: ?>
            <div class="notice notice-warning">
                <p><strong><?php _e('Not Connected', 'wp-image-guardian'); ?></strong></p>
                <p><?php _e('Please configure your OAuth credentials above and connect to Image Guardian.', 'wp-image-guardian'); ?></p>
                <p>
                    <a href="<?php echo admin_url('upload.php?page=wp-image-guardian&action=oauth'); ?>" class="button button-primary">
                        <?php _e('Connect to Image Guardian', 'wp-image-guardian'); ?>
                    </a>
                </p>
            </div>
        <?php endif; ?>
        
        <h2><?php _e('Domain Registration', 'wp-image-guardian'); ?></h2>
        
        <p><?php _e('Your WordPress site domain:', 'wp-image-guardian'); ?> <strong><?php echo get_site_url(); ?></strong></p>
        
        <?php if (get_option('wp_image_guardian_domain_approved', false)): ?>
            <div class="notice notice-success">
                <p><strong><?php _e('Domain Approved', 'wp-image-guardian'); ?></strong></p>
                <p><?php _e('Your domain has been approved for use with Image Guardian.', 'wp-image-guardian'); ?></p>
            </div>
        <?php else: ?>
            <div class="notice notice-warning">
                <p><strong><?php _e('Domain Not Approved', 'wp-image-guardian'); ?></strong></p>
                <p><?php _e('Your domain needs to be approved before you can use Image Guardian. This happens automatically when you connect your account.', 'wp-image-guardian'); ?></p>
            </div>
        <?php endif; ?>
        
        <h2><?php _e('Usage Information', 'wp-image-guardian'); ?></h2>
        
        <div class="wp-image-guardian-usage-info">
            <h3><?php _e('Free Plan', 'wp-image-guardian'); ?></h3>
            <ul>
                <li><?php _e('1 image check per account', 'wp-image-guardian'); ?></li>
                <li><?php _e('Manual checking only', 'wp-image-guardian'); ?></li>
                <li><?php _e('Basic risk assessment', 'wp-image-guardian'); ?></li>
            </ul>
            
            <h3><?php _e('Premium Plans', 'wp-image-guardian'); ?></h3>
            <ul>
                <li><?php _e('Unlimited image checks', 'wp-image-guardian'); ?></li>
                <li><?php _e('Bulk checking capabilities', 'wp-image-guardian'); ?></li>
                <li><?php _e('Auto-check new uploads', 'wp-image-guardian'); ?></li>
                <li><?php _e('Advanced reporting', 'wp-image-guardian'); ?></li>
                <li><?php _e('Priority support', 'wp-image-guardian'); ?></li>
            </ul>
        </div>
        
        <?php submit_button(__('Save Settings', 'wp-image-guardian')); ?>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    $('#disconnect-oauth').on('click', function() {
        if (confirm('<?php _e('Are you sure you want to disconnect from Image Guardian?', 'wp-image-guardian'); ?>')) {
            $.post(ajaxurl, {
                action: 'wp_image_guardian_disconnect',
                nonce: '<?php echo wp_create_nonce('wp_image_guardian_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('<?php _e('Error disconnecting. Please try again.', 'wp-image-guardian'); ?>');
                }
            });
        }
    });
});
</script>

<style>
.wp-image-guardian-usage-info {
    background: #f9f9f9;
    padding: 20px;
    border-radius: 5px;
    margin: 20px 0;
}

.wp-image-guardian-usage-info h3 {
    margin-top: 0;
    color: #0073aa;
}

.wp-image-guardian-usage-info ul {
    margin: 10px 0;
}

.wp-image-guardian-usage-info li {
    margin: 5px 0;
}
</style>
