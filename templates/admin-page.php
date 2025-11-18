<?php
if (!defined('ABSPATH')) {
    exit;
}

// Helper functions for status display
function wp_image_guardian_get_status_icon($risk_level, $user_decision) {
    if ($user_decision) {
        return $user_decision === 'safe' ? '✅' : '❌';
    }
    switch ($risk_level) {
        case 'safe': return '🟢';
        case 'warning': return '🟡';
        case 'danger': return '🔴';
        default: return '⚪';
    }
}

function wp_image_guardian_get_status_text($risk_level, $user_decision) {
    if ($user_decision) {
        return $user_decision === 'safe' ? 
            __('Marked as Safe', 'wp-image-guardian') : 
            __('Marked as Unsafe', 'wp-image-guardian');
    }
    switch ($risk_level) {
        case 'safe': return __('Safe - No matches found', 'wp-image-guardian');
        case 'warning': return __('Warning - Few matches found', 'wp-image-guardian');
        case 'danger': return __('Danger - Many matches found', 'wp-image-guardian');
        default: return __('Not checked', 'wp-image-guardian');
    }
}
?>

<div class="wrap">
    <h1><?php _e('Image Guardian', 'wp-image-guardian'); ?></h1>
    
    <?php
    // Tab navigation
    $tabs = [
        'dashboard' => __('Dashboard', 'wp-image-guardian'),
        'settings' => __('Settings', 'wp-image-guardian'),
    ];
    ?>
    
    <nav class="nav-tab-wrapper">
        <?php foreach ($tabs as $tab_key => $tab_label): ?>
            <a href="<?php echo admin_url('upload.php?page=wp-image-guardian&tab=' . $tab_key); ?>" 
               class="nav-tab <?php echo $current_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
                <?php echo $tab_label; ?>
            </a>
        <?php endforeach; ?>
    </nav>
    
    <div class="wp-image-guardian-tab-content">
        <?php if ($current_tab === 'dashboard'): ?>
            <!-- Dashboard Tab -->
            <?php if (!$oauth_connected): ?>
                <div class="notice notice-warning">
                    <p><strong><?php _e('Not Connected', 'wp-image-guardian'); ?></strong></p>
                    <p><?php _e('Please connect your Image Guardian account to start checking images for copyright issues.', 'wp-image-guardian'); ?></p>
                    <p>
                        <a href="<?php echo admin_url('upload.php?page=wp-image-guardian&tab=settings'); ?>" class="button button-primary">
                            <?php _e('Go to Settings', 'wp-image-guardian'); ?>
                        </a>
                    </p>
                </div>
            <?php else: ?>
                
                <!-- Connection Status -->
                <div class="notice notice-success">
                    <p><strong><?php _e('Connected to Image Guardian', 'wp-image-guardian'); ?></strong></p>
                    <?php if ($user_info): ?>
                        <p><?php printf(__('Account: %s', 'wp-image-guardian'), $user_info['name'] ?? 'Unknown'); ?></p>
                    <?php endif; ?>
                </div>
                
                <!-- Usage Stats -->
                <?php if ($usage_stats['success']): ?>
                    <div class="wp-image-guardian-stats">
                        <h2><?php _e('Usage Statistics', 'wp-image-guardian'); ?></h2>
                        <div class="stats-grid">
                            <div class="stat-box">
                                <h3><?php echo $usage_stats['data']['api_requests_used'] ?? 0; ?></h3>
                                <p><?php _e('Images Checked', 'wp-image-guardian'); ?></p>
                            </div>
                            <div class="stat-box">
                                <h3><?php echo $usage_stats['data']['remaining_requests'] ?? 0; ?></h3>
                                <p><?php _e('Remaining Checks', 'wp-image-guardian'); ?></p>
                            </div>
                            <div class="stat-box">
                                <h3><?php echo $usage_stats['data']['subscription_status'] ?? 'free'; ?></h3>
                                <p><?php _e('Plan', 'wp-image-guardian'); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Risk Statistics -->
                <div class="wp-image-guardian-risk-stats">
                    <h2><?php _e('Risk Analysis', 'wp-image-guardian'); ?></h2>
                    <div class="risk-grid">
                        <div class="risk-box safe">
                            <h3><?php echo $risk_stats['safe'] ?? 0; ?></h3>
                            <p><?php _e('Safe Images', 'wp-image-guardian'); ?></p>
                            <span class="risk-icon">🟢</span>
                        </div>
                        <div class="risk-box warning">
                            <h3><?php echo $risk_stats['warning'] ?? 0; ?></h3>
                            <p><?php _e('Warning Images', 'wp-image-guardian'); ?></p>
                            <span class="risk-icon">🟡</span>
                        </div>
                        <div class="risk-box danger">
                            <h3><?php echo $risk_stats['danger'] ?? 0; ?></h3>
                            <p><?php _e('Danger Images', 'wp-image-guardian'); ?></p>
                            <span class="risk-icon">🔴</span>
                        </div>
                        <div class="risk-box unknown">
                            <h3><?php echo $risk_stats['unknown'] ?? 0; ?></h3>
                            <p><?php _e('Unchecked Images', 'wp-image-guardian'); ?></p>
                            <span class="risk-icon">⚪</span>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Checks -->
                <?php if (!empty($recent_checks)): ?>
                    <div class="wp-image-guardian-recent">
                        <h2><?php _e('Recent Image Checks', 'wp-image-guardian'); ?></h2>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Image', 'wp-image-guardian'); ?></th>
                                    <th><?php _e('Status', 'wp-image-guardian'); ?></th>
                                    <th><?php _e('Results', 'wp-image-guardian'); ?></th>
                                    <th><?php _e('Checked', 'wp-image-guardian'); ?></th>
                                    <th><?php _e('Actions', 'wp-image-guardian'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_checks as $check): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo esc_html($check->post_title ?? ''); ?></strong>
                                        </td>
                                        <td>
                                            <span class="status-<?php echo $check->risk_level ?? 'unknown'; ?>">
                                                <?php echo wp_image_guardian_get_status_icon($check->risk_level ?? 'unknown', $check->user_decision ?? null); ?>
                                                <?php echo wp_image_guardian_get_status_text($check->risk_level ?? 'unknown', $check->user_decision ?? null); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo $check->results_count ?? 0; ?> <?php _e('matches', 'wp-image-guardian'); ?>
                                        </td>
                                        <td>
                                            <?php 
                                            if (isset($check->checked_at)) {
                                                echo human_time_diff(strtotime($check->checked_at), current_time('timestamp')); 
                                                echo ' ' . __('ago', 'wp-image-guardian');
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php if (isset($check->attachment_id)): ?>
                                                <a href="<?php echo admin_url('post.php?post=' . $check->attachment_id . '&action=edit'); ?>" class="button button-small">
                                                    <?php _e('View', 'wp-image-guardian'); ?>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                
                <!-- Premium Features -->
                <?php
                $premium = new WP_Image_Guardian_Premium();
                if (!$premium->is_premium_user()): 
                ?>
                    <div class="wp-image-guardian-premium">
                        <h2><?php _e('Upgrade to Premium', 'wp-image-guardian'); ?></h2>
                        <div class="premium-features">
                            <div class="feature">
                                <h3><?php _e('Bulk Image Checking', 'wp-image-guardian'); ?></h3>
                                <p><?php _e('Check multiple images at once', 'wp-image-guardian'); ?></p>
                            </div>
                            <div class="feature">
                                <h3><?php _e('Auto Check New Uploads', 'wp-image-guardian'); ?></h3>
                                <p><?php _e('Automatically check new image uploads', 'wp-image-guardian'); ?></p>
                            </div>
                            <div class="feature">
                                <h3><?php _e('Advanced Reporting', 'wp-image-guardian'); ?></h3>
                                <p><?php _e('Detailed reports and analytics', 'wp-image-guardian'); ?></p>
                            </div>
                            <div class="feature">
                                <h3><?php _e('Unlimited Checks', 'wp-image-guardian'); ?></h3>
                                <p><?php _e('No limits on image checks', 'wp-image-guardian'); ?></p>
                            </div>
                        </div>
                        <p>
                            <a href="<?php echo $premium->get_upgrade_url(); ?>" class="button button-primary button-large">
                                <?php _e('Upgrade Now', 'wp-image-guardian'); ?>
                            </a>
                        </p>
                    </div>
                <?php else: ?>
                    <div class="wp-image-guardian-premium-features">
                        <h2><?php _e('Premium Features', 'wp-image-guardian'); ?></h2>
                        <p>
                            <a href="<?php echo admin_url('upload.php?page=wp-image-guardian-bulk'); ?>" class="button">
                                <?php _e('Bulk Check Images', 'wp-image-guardian'); ?>
                            </a>
                            <a href="<?php echo admin_url('upload.php?page=wp-image-guardian-auto'); ?>" class="button">
                                <?php _e('Auto Check Settings', 'wp-image-guardian'); ?>
                            </a>
                        </p>
                    </div>
                <?php endif; ?>
                
            <?php endif; ?>
            
        <?php elseif ($current_tab === 'settings'): ?>
            <!-- Settings Tab -->
            <form method="post" action="">
                <?php wp_nonce_field('wp_image_guardian_settings', 'wp_image_guardian_nonce'); ?>
                
                <h2><?php _e('API Configuration', 'wp-image-guardian'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label><?php _e('API Base URL', 'wp-image-guardian'); ?></label>
                        </th>
                        <td>
                            <code><?php echo esc_html(WP_IMAGE_GUARDIAN_API_BASE_URL); ?></code>
                            <p class="description">
                                <?php _e('API base URL is configured via the WP_IMAGE_GUARDIAN_API_BASE_URL constant.', 'wp-image-guardian'); ?>
                                <?php _e('To change it, define the constant in wp-config.php:', 'wp-image-guardian'); ?>
                                <br>
                                <code>define('WP_IMAGE_GUARDIAN_API_BASE_URL', 'http://your-api-url.com');</code>
                                <br><br>
                                <strong><?php _e('Note:', 'wp-image-guardian'); ?></strong>
                                <?php _e('The plugin expects endpoints at the root level. If your Laravel API routes are under the /api prefix, you may need to include /api in the base URL or modify the endpoint paths in the API class.', 'wp-image-guardian'); ?>
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
                            <a href="<?php echo admin_url('upload.php?page=wp-image-guardian&tab=dashboard'); ?>" class="button">
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
        <?php endif; ?>
    </div>
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
.wp-image-guardian-tab-content {
    margin-top: 20px;
}

.wp-image-guardian-stats .stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.stat-box {
    background: #f9f9f9;
    padding: 20px;
    border-radius: 5px;
    text-align: center;
    border-left: 4px solid #0073aa;
}

.stat-box h3 {
    font-size: 2em;
    margin: 0;
    color: #0073aa;
}

.risk-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin: 20px 0;
}

.risk-box {
    padding: 20px;
    border-radius: 5px;
    text-align: center;
    position: relative;
}

.risk-box.safe {
    background: #d4edda;
    border-left: 4px solid #28a745;
}

.risk-box.warning {
    background: #fff3cd;
    border-left: 4px solid #ffc107;
}

.risk-box.danger {
    background: #f8d7da;
    border-left: 4px solid #dc3545;
}

.risk-box.unknown {
    background: #e2e3e5;
    border-left: 4px solid #6c757d;
}

.risk-box h3 {
    font-size: 2em;
    margin: 0;
}

.risk-icon {
    position: absolute;
    top: 10px;
    right: 10px;
    font-size: 1.5em;
}

.premium-features {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.feature {
    background: #f9f9f9;
    padding: 20px;
    border-radius: 5px;
    border-left: 4px solid #0073aa;
}

.feature h3 {
    margin-top: 0;
    color: #0073aa;
}

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

