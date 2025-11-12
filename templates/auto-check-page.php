<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Auto Check Settings', 'wp-image-guardian'); ?></h1>
    
    <div class="wp-image-guardian-auto-check">
        <div class="auto-check-info">
            <h2><?php _e('Automatic Image Checking', 'wp-image-guardian'); ?></h2>
            <p><?php _e('Enable automatic checking of new image uploads. When enabled, all new images uploaded to your WordPress site will be automatically checked for copyright issues using TinyEye.', 'wp-image-guardian'); ?></p>
        </div>
        
        <form method="post" action="">
            <?php wp_nonce_field('wp_image_guardian_auto_check', 'wp_image_guardian_nonce'); ?>
            
            <div class="auto-check-settings">
                <h3><?php _e('Auto Check Configuration', 'wp-image-guardian'); ?></h3>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="auto_check"><?php _e('Enable Auto Check', 'wp-image-guardian'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" id="auto_check" name="auto_check" 
                                       <?php checked($auto_check_enabled); ?> />
                                <?php _e('Automatically check new image uploads', 'wp-image-guardian'); ?>
                            </label>
                            <p class="description">
                                <?php _e('When enabled, all new images will be automatically checked for copyright issues.', 'wp-image-guardian'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <?php _e('Check Frequency', 'wp-image-guardian'); ?>
                        </th>
                        <td>
                            <p><?php _e('Images are checked hourly for new uploads.', 'wp-image-guardian'); ?></p>
                            <p class="description">
                                <?php _e('The system runs a background check every hour to process any new images that haven\'t been checked yet.', 'wp-image-guardian'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <?php _e('Current Status', 'wp-image-guardian'); ?>
                        </th>
                        <td>
                            <?php if ($auto_check_enabled): ?>
                                <span class="status-enabled">
                                    <span class="status-icon">✅</span>
                                    <?php _e('Auto Check is ENABLED', 'wp-image-guardian'); ?>
                                </span>
                            <?php else: ?>
                                <span class="status-disabled">
                                    <span class="status-icon">❌</span>
                                    <?php _e('Auto Check is DISABLED', 'wp-image-guardian'); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(__('Save Auto Check Settings', 'wp-image-guardian')); ?>
            </div>
        </form>
        
        <div class="auto-check-stats">
            <h3><?php _e('Recent Auto Checks', 'wp-image-guardian'); ?></h3>
            
            <?php if (!empty($recent_auto_checks)): ?>
                <div class="recent-checks-list">
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
                            <?php foreach ($recent_auto_checks as $check): ?>
                                <tr>
                                    <td>
                                        <div class="image-info">
                                            <?php echo wp_get_attachment_image($check->attachment_id, [40, 40], false, ['class' => 'attachment-thumbnail']); ?>
                                            <div class="image-details">
                                                <strong><?php echo esc_html($check->post_title); ?></strong>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-<?php echo $check->risk_level; ?>">
                                            <?php
                                            switch ($check->risk_level) {
                                                case 'safe':
                                                    echo '🟢 ' . __('Safe', 'wp-image-guardian');
                                                    break;
                                                case 'warning':
                                                    echo '🟡 ' . __('Warning', 'wp-image-guardian');
                                                    break;
                                                case 'danger':
                                                    echo '🔴 ' . __('Danger', 'wp-image-guardian');
                                                    break;
                                                default:
                                                    echo '⚪ ' . __('Unknown', 'wp-image-guardian');
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo $check->results_count; ?> <?php _e('matches', 'wp-image-guardian'); ?>
                                    </td>
                                    <td>
                                        <?php echo human_time_diff(strtotime($check->checked_at), current_time('timestamp')); ?> <?php _e('ago', 'wp-image-guardian'); ?>
                                    </td>
                                    <td>
                                        <a href="<?php echo admin_url('post.php?post=' . $check->attachment_id . '&action=edit'); ?>" 
                                           class="button button-small">
                                            <?php _e('View', 'wp-image-guardian'); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-recent-checks">
                    <p><?php _e('No recent auto checks found.', 'wp-image-guardian'); ?></p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="auto-check-requirements">
            <h3><?php _e('Requirements for Auto Check', 'wp-image-guardian'); ?></h3>
            <ul>
                <li><?php _e('Premium subscription (Monthly or Yearly)', 'wp-image-guardian'); ?></li>
                <li><?php _e('Valid TinyEye API key configured', 'wp-image-guardian'); ?></li>
                <li><?php _e('Connected to Image Guardian service', 'wp-image-guardian'); ?></li>
                <li><?php _e('Sufficient API request quota', 'wp-image-guardian'); ?></li>
            </ul>
        </div>
        
        <div class="auto-check-troubleshooting">
            <h3><?php _e('Troubleshooting', 'wp-image-guardian'); ?></h3>
            <div class="troubleshooting-items">
                <div class="troubleshooting-item">
                    <h4><?php _e('Auto Check Not Working?', 'wp-image-guardian'); ?></h4>
                    <ul>
                        <li><?php _e('Verify your premium subscription is active', 'wp-image-guardian'); ?></li>
                        <li><?php _e('Check that your TinyEye API key is valid', 'wp-image-guardian'); ?></li>
                        <li><?php _e('Ensure you have remaining API requests', 'wp-image-guardian'); ?></li>
                        <li><?php _e('Check WordPress cron is working properly', 'wp-image-guardian'); ?></li>
                    </ul>
                </div>
                
                <div class="troubleshooting-item">
                    <h4><?php _e('Manual Check Still Required?', 'wp-image-guardian'); ?></h4>
                    <p><?php _e('Auto check runs hourly. For immediate checking, use the manual check feature in the media library.', 'wp-image-guardian'); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.wp-image-guardian-auto-check {
    background: #f9f9f9;
    padding: 20px;
    border-radius: 5px;
    margin: 20px 0;
}

.auto-check-info {
    background: white;
    padding: 20px;
    border-radius: 5px;
    border: 1px solid #ddd;
    margin-bottom: 20px;
}

.auto-check-info h2 {
    margin-top: 0;
    color: #0073aa;
}

.auto-check-settings {
    background: white;
    padding: 20px;
    border-radius: 5px;
    border: 1px solid #ddd;
    margin-bottom: 20px;
}

.auto-check-settings h3 {
    margin-top: 0;
    color: #0073aa;
}

.auto-check-settings .form-table {
    margin: 0;
}

.auto-check-settings .form-table th {
    width: 200px;
    padding: 15px 10px 15px 0;
}

.auto-check-settings .form-table td {
    padding: 15px 10px;
}

.status-enabled {
    color: #28a745;
    font-weight: bold;
    display: flex;
    align-items: center;
    gap: 8px;
}

.status-disabled {
    color: #dc3545;
    font-weight: bold;
    display: flex;
    align-items: center;
    gap: 8px;
}

.status-icon {
    font-size: 16px;
}

.auto-check-stats {
    background: white;
    padding: 20px;
    border-radius: 5px;
    border: 1px solid #ddd;
    margin-bottom: 20px;
}

.auto-check-stats h3 {
    margin-top: 0;
    color: #0073aa;
}

.recent-checks-list {
    margin-top: 15px;
}

.recent-checks-list .wp-list-table {
    margin: 0;
}

.image-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.attachment-thumbnail {
    width: 40px;
    height: 40px;
    object-fit: cover;
    border-radius: 3px;
}

.image-details strong {
    display: block;
    font-size: 14px;
}

.status-safe {
    color: #28a745;
    font-weight: bold;
}

.status-warning {
    color: #ffc107;
    font-weight: bold;
}

.status-danger {
    color: #dc3545;
    font-weight: bold;
}

.no-recent-checks {
    text-align: center;
    padding: 40px;
    color: #666;
    background: #f8f9fa;
    border-radius: 5px;
    border: 1px solid #dee2e6;
}

.auto-check-requirements {
    background: white;
    padding: 20px;
    border-radius: 5px;
    border: 1px solid #ddd;
    margin-bottom: 20px;
}

.auto-check-requirements h3 {
    margin-top: 0;
    color: #0073aa;
}

.auto-check-requirements ul {
    margin: 10px 0;
}

.auto-check-requirements li {
    margin: 5px 0;
    padding-left: 20px;
    position: relative;
}

.auto-check-requirements li::before {
    content: '✓';
    position: absolute;
    left: 0;
    color: #28a745;
    font-weight: bold;
}

.auto-check-troubleshooting {
    background: white;
    padding: 20px;
    border-radius: 5px;
    border: 1px solid #ddd;
}

.auto-check-troubleshooting h3 {
    margin-top: 0;
    color: #0073aa;
}

.troubleshooting-items {
    margin-top: 15px;
}

.troubleshooting-item {
    margin-bottom: 20px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 5px;
    border-left: 4px solid #0073aa;
}

.troubleshooting-item h4 {
    margin-top: 0;
    color: #0073aa;
}

.troubleshooting-item ul {
    margin: 10px 0;
}

.troubleshooting-item li {
    margin: 5px 0;
}

@media (max-width: 768px) {
    .image-info {
        flex-direction: column;
        align-items: flex-start;
        gap: 5px;
    }
    
    .attachment-thumbnail {
        width: 30px;
        height: 30px;
    }
}
</style>
