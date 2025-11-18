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
    
    <?php if (!$oauth_connected): ?>
        <!-- Not Connected - Show Connect Button -->
        <div class="notice notice-info" style="max-width: 600px;">
            <h2><?php _e('Connect to Image Guardian', 'wp-image-guardian'); ?></h2>
            <p><?php _e('Connect your WordPress site to Image Guardian to start checking images for copyright issues. You will be redirected to sign in or create an account if you don\'t have one yet.', 'wp-image-guardian'); ?></p>
            <p>
                <a href="<?php echo admin_url('upload.php?page=wp-image-guardian&action=oauth'); ?>" class="button button-primary button-large">
                    <?php _e('Connect to Image Guardian', 'wp-image-guardian'); ?>
                </a>
            </p>
            <p class="description">
                <?php _e('After connecting, your subscription plan, TinyEye API key, and other settings will be automatically configured from your Image Guardian account.', 'wp-image-guardian'); ?>
            </p>
        </div>
    <?php else: ?>
        <!-- Connected - Show Dashboard Content -->
        <!-- Connection Status -->
        <div class="notice notice-success">
            <p><strong><?php _e('✅ Connected to Image Guardian', 'wp-image-guardian'); ?></strong></p>
            <?php if ($user_info): ?>
                <p><?php printf(__('Account: %s', 'wp-image-guardian'), esc_html($user_info['name'] ?? $user_info['email'] ?? 'Unknown')); ?></p>
            <?php endif; ?>
            <p>
                <button type="button" id="disconnect-oauth" class="button button-secondary">
                    <?php _e('Disconnect', 'wp-image-guardian'); ?>
                </button>
            </p>
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
</style>
