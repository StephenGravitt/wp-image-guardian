<?php
if (!defined('ABSPATH')) {
    exit;
}

// Helper functions for status display (using helpers class)
function wp_image_guardian_get_status_icon($risk_level, $user_decision) {
    return WP_Image_Guardian_Helpers::get_status_icon($risk_level, $user_decision);
}

function wp_image_guardian_get_status_text($risk_level, $user_decision) {
    return WP_Image_Guardian_Helpers::get_status_text($risk_level, $user_decision);
}

// Calculate percentage
$checked_percent = $total_media > 0 ? round(($checked_media / $total_media) * 100, 1) : 0;
?>

<div class="wrap">
    <h1><?php _e('Image Guardian', 'wp-image-guardian'); ?></h1>
    
    <!-- API Key Settings Section -->
    <div class="wp-image-guardian-settings-section">
        <h2><?php _e('TinyEye API Key Settings', 'wp-image-guardian'); ?></h2>
        
        <?php if ($has_constant_key): ?>
            <div class="notice notice-info">
                <p><strong><?php _e('API Key Set via Constant', 'wp-image-guardian'); ?></strong></p>
                <p><?php _e('Your API key is configured via the WP_IMAGE_GUARDIAN_TINEYE_API_KEY constant in wp-config.php. To change it, update the constant.', 'wp-image-guardian'); ?></p>
                <p><?php printf(__('Current API Key: %s', 'wp-image-guardian'), '<code>' . esc_html($masked_api_key) . '</code>'); ?></p>
            </div>
        <?php else: ?>
            <form method="post" action="">
                <?php wp_nonce_field('wp_image_guardian_settings', 'wp_image_guardian_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="tinyeye_api_key"><?php _e('TinyEye API Key', 'wp-image-guardian'); ?></label>
                        </th>
                        <td>
                            <?php if (!empty($api_key)): ?>
                                <p>
                                    <strong><?php _e('Current API Key:', 'wp-image-guardian'); ?></strong> 
                                    <code><?php echo esc_html($masked_api_key); ?></code>
                                </p>
                                <p class="description">
                                    <?php _e('Enter a new API key to replace the current one.', 'wp-image-guardian'); ?>
                                </p>
                            <?php endif; ?>
                            <input 
                                type="text" 
                                id="tinyeye_api_key" 
                                name="tinyeye_api_key" 
                                value="" 
                                class="regular-text" 
                                placeholder="<?php esc_attr_e('Enter your TinyEye API key', 'wp-image-guardian'); ?>"
                                autocomplete="off"
                            />
                            <p class="description">
                                <?php _e('Enter your TinyEye API key. It will be validated upon saving.', 'wp-image-guardian'); ?>
                                <?php if (!$has_constant_key): ?>
                                    <?php _e('Alternatively, you can define WP_IMAGE_GUARDIAN_TINEYE_API_KEY in wp-config.php.', 'wp-image-guardian'); ?>
                                <?php endif; ?>
                            </p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" name="wp_image_guardian_save_settings" class="button button-primary" value="<?php esc_attr_e('Save & Validate API Key', 'wp-image-guardian'); ?>" />
                </p>
            </form>
        <?php endif; ?>
        
        <?php if ($is_testing_key): ?>
            <div class="notice notice-warning">
                <p><strong><?php _e('Testing API Key Detected', 'wp-image-guardian'); ?></strong></p>
                <p><?php _e('You are using the testing API key. Only "melon cat" images will be returned in search results.', 'wp-image-guardian'); ?></p>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($api_key) && $remaining_searches !== null): ?>
            <div class="wp-image-guardian-api-status">
                <p>
                    <strong><?php _e('Remaining Searches:', 'wp-image-guardian'); ?></strong> 
                    <span class="remaining-searches-count"><?php echo esc_html($remaining_searches); ?></span>
                </p>
            </div>
        <?php elseif (empty($api_key)): ?>
            <div class="notice notice-warning">
                <p><?php _e('No API key configured. Please enter your TinyEye API key above to start checking images.', 'wp-image-guardian'); ?></p>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if (!empty($api_key)): ?>
        <!-- Media Summary Section -->
        <div class="wp-image-guardian-summary-section">
            <h2><?php _e('Media Summary', 'wp-image-guardian'); ?></h2>
            
            <div class="summary-stats-grid">
                <div class="summary-stat-box">
                    <h3><?php echo number_format($total_media); ?></h3>
                    <p><?php _e('Total Uploaded Media', 'wp-image-guardian'); ?></p>
                </div>
                
                <div class="summary-stat-box">
                    <h3><?php echo number_format($checked_media); ?></h3>
                    <p><?php _e('Total Media Checked', 'wp-image-guardian'); ?></p>
                    <span class="stat-percent"><?php echo esc_html($checked_percent); ?>%</span>
                </div>
            </div>
            
            <!-- Risk Breakdown -->
            <div class="risk-breakdown-section">
                <h3><?php _e('Risk Assessment', 'wp-image-guardian'); ?></h3>
                <div class="risk-breakdown-grid">
                    <?php 
                    $risk_levels = [
                        'high' => ['label' => __('High Risk', 'wp-image-guardian'), 'class' => 'danger'],
                        'medium' => ['label' => __('Medium Risk', 'wp-image-guardian'), 'class' => 'warning'],
                        'low' => ['label' => __('Low Risk', 'wp-image-guardian'), 'class' => 'safe'],
                    ];
                    
                    foreach ($risk_levels as $level => $info): 
                        $total = $risk_breakdown[$level]['total'] ?? 0;
                        $reviewed = $risk_breakdown[$level]['reviewed'] ?? 0;
                        $safe = $risk_breakdown[$level]['safe'] ?? 0;
                        $unsafe = $risk_breakdown[$level]['unsafe'] ?? 0;
                        $filter_url = admin_url('upload.php?risk_level=' . $level);
                        $reviewed_filter_url = admin_url('upload.php?risk_level=' . $level . '&reviewed=yes');
                        $safe_filter_url = admin_url('upload.php?risk_level=' . $level . '&user_decision=safe');
                        $unsafe_filter_url = admin_url('upload.php?risk_level=' . $level . '&user_decision=unsafe');
                    ?>
                        <div class="risk-breakdown-box <?php echo esc_attr($info['class']); ?>">
                            <h4><?php echo esc_html($info['label']); ?></h4>
                            <div class="risk-numbers">
                                <div class="risk-item">
                                    <strong><?php _e('Total:', 'wp-image-guardian'); ?></strong>
                                    <a href="<?php echo esc_url($filter_url); ?>"><?php echo number_format($total); ?></a>
                                </div>
                                <div class="risk-item">
                                    <strong><?php _e('Reviewed:', 'wp-image-guardian'); ?></strong>
                                    <a href="<?php echo esc_url($reviewed_filter_url); ?>"><?php echo number_format($reviewed); ?></a>
                                </div>
                                <div class="risk-item">
                                    <strong><?php _e('Safe:', 'wp-image-guardian'); ?></strong>
                                    <a href="<?php echo esc_url($safe_filter_url); ?>"><?php echo number_format($safe); ?></a>
                                </div>
                                <div class="risk-item">
                                    <strong><?php _e('Unsafe:', 'wp-image-guardian'); ?></strong>
                                    <a href="<?php echo esc_url($unsafe_filter_url); ?>"><?php echo number_format($unsafe); ?></a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Auto Check Settings Section -->
        <div class="wp-image-guardian-auto-check-section">
            <h2><?php _e('Auto Check Settings', 'wp-image-guardian'); ?></h2>
            <?php 
            $auto_check_enabled = get_option('wp_image_guardian_auto_check', false);
            ?>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="auto_check_enabled"><?php _e('Auto Check New Uploads', 'wp-image-guardian'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" id="auto_check_enabled" name="auto_check_enabled" value="1" <?php checked($auto_check_enabled, true); ?> />
                            <?php _e('Automatically check new image uploads', 'wp-image-guardian'); ?>
                        </label>
                        <p class="description">
                            <?php _e('When enabled, newly uploaded images will be automatically checked using TinyEye.', 'wp-image-guardian'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <button type="button" id="save-auto-check" class="button button-primary">
                    <?php _e('Save Auto Check Settings', 'wp-image-guardian'); ?>
                </button>
            </p>
        </div>
        
        <!-- Bulk Check Section -->
        <div class="wp-image-guardian-bulk-check-section">
            <h2><?php _e('Bulk Check Media', 'wp-image-guardian'); ?></h2>
            <div class="bulk-check-info">
                <p>
                    <strong><?php _e('Unchecked Images:', 'wp-image-guardian'); ?></strong> 
                    <span id="unchecked-count"><?php echo number_format($unchecked_count); ?></span>
                </p>
                <?php if ($remaining_searches !== null): ?>
                    <p>
                        <strong><?php _e('Remaining Searches:', 'wp-image-guardian'); ?></strong> 
                        <span id="remaining-searches"><?php echo number_format($remaining_searches); ?></span>
                    </p>
                <?php endif; ?>
            </div>
            <p>
                <button type="button" id="start-bulk-check" class="button button-primary" <?php echo ($unchecked_count == 0 || ($remaining_searches !== null && $remaining_searches <= 0)) ? 'disabled' : ''; ?>>
                    <?php _e('Start Bulk Check', 'wp-image-guardian'); ?>
                </button>
                <button type="button" id="cancel-bulk-check" class="button button-secondary" style="display: none;">
                    <?php _e('Cancel Bulk Check', 'wp-image-guardian'); ?>
                </button>
                <button type="button" id="reset-bulk-check" class="button button-secondary" style="display: none;">
                    <?php _e('Cancel and Clear Queue', 'wp-image-guardian'); ?>
                </button>
            </p>
            <p id="reset-bulk-check-help" class="description" style="display: none; color: #666; font-style: italic; margin-top: 5px;">
                <?php _e('Use this button to stop the bulk generator at any time if it appears to be misbehaving or stuck. This will clear the queue and reset the bulk check state.', 'wp-image-guardian'); ?>
            </p>
            <div id="bulk-check-progress" style="display: none;">
                <div class="progress-bar">
                    <div class="progress-fill" style="width: 0%;"></div>
                </div>
                <p class="progress-text">
                    <span id="progress-current">0</span> / <span id="progress-total">0</span> 
                    <?php _e('images checked', 'wp-image-guardian'); ?>
                </p>
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
                                    <span class="status-<?php echo esc_attr($check->risk_level ?? 'unknown'); ?>">
                                        <?php echo wp_image_guardian_get_status_icon($check->risk_level ?? 'unknown', $check->user_decision ?? null); ?>
                                        <?php echo wp_image_guardian_get_status_text($check->risk_level ?? 'unknown', $check->user_decision ?? null); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo esc_html($check->results_count ?? 0); ?> <?php _e('matches', 'wp-image-guardian'); ?>
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
    <?php endif; ?>
</div>

<style>
.wp-image-guardian-settings-section,
.wp-image-guardian-summary-section,
.wp-image-guardian-bulk-check-section,
.wp-image-guardian-auto-check-section {
    background: #fff;
    padding: 20px;
    margin: 20px 0;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.summary-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.summary-stat-box {
    background: #f9f9f9;
    padding: 20px;
    border-radius: 5px;
    text-align: center;
    border-left: 4px solid #0073aa;
}

.summary-stat-box h3 {
    font-size: 2em;
    margin: 0;
    color: #0073aa;
}

.stat-percent {
    display: block;
    margin-top: 5px;
    font-size: 0.9em;
    color: #666;
}

.risk-breakdown-section {
    margin-top: 30px;
}

.risk-breakdown-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin: 20px 0;
}

.risk-breakdown-box {
    padding: 20px;
    border-radius: 5px;
    border-left: 4px solid;
}

.risk-breakdown-box.safe {
    background: #d4edda;
    border-color: #28a745;
}

.risk-breakdown-box.warning {
    background: #fff3cd;
    border-color: #ffc107;
}

.risk-breakdown-box.danger {
    background: #f8d7da;
    border-color: #dc3545;
}

.risk-breakdown-box h4 {
    margin-top: 0;
}

.risk-numbers {
    margin-top: 10px;
}

.risk-numbers div {
    margin: 5px 0;
}

.risk-numbers a {
    font-weight: bold;
    text-decoration: none;
    color: inherit;
}

.risk-numbers a:hover {
    text-decoration: underline;
}

.progress-bar {
    width: 100%;
    height: 30px;
    background: #f0f0f0;
    border-radius: 3px;
    overflow: hidden;
    margin: 10px 0;
}

.progress-fill {
    height: 100%;
    background: #0073aa;
    transition: width 0.3s ease;
}
</style>

<script>
jQuery(document).ready(function($) {
    var bulkCheckInterval = null;
    
    // Auto check toggle
    $('#save-auto-check').on('click', function() {
        var button = $(this);
        var enabled = $('#auto_check_enabled').is(':checked');
        
        button.prop('disabled', true).text('<?php _e('Saving...', 'wp-image-guardian'); ?>');
        
        $.post(ajaxurl, {
            action: 'wp_image_guardian_auto_check_toggle',
            enabled: enabled,
            nonce: '<?php echo wp_create_nonce('wp_image_guardian_nonce'); ?>'
        }, function(response) {
            button.prop('disabled', false).text('<?php _e('Save Auto Check Settings', 'wp-image-guardian'); ?>');
            
            if (response.success) {
                alert('<?php _e('Auto check settings saved!', 'wp-image-guardian'); ?>');
            } else {
                alert('Error: ' + response.data);
            }
        });
    });
    
    // Start bulk check
    $('#start-bulk-check').on('click', function() {
        var button = $(this);
        
        if (!confirm('<?php _e('Start bulk check? This will check only images that have not been checked yet.', 'wp-image-guardian'); ?>')) {
            return;
        }
        
        button.prop('disabled', true).text('<?php _e('Starting...', 'wp-image-guardian'); ?>');
        $('#cancel-bulk-check').show();
        $('#bulk-check-progress').show();
        
        $.post(ajaxurl, {
            action: 'wp_image_guardian_start_bulk_check',
            nonce: '<?php echo wp_create_nonce('wp_image_guardian_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                button.prop('disabled', true).text('<?php _e('Running...', 'wp-image-guardian'); ?>');
                $('#progress-total').text(response.data.total);
                
                // Start polling for progress
                bulkCheckInterval = setInterval(function() {
                    updateBulkCheckProgress();
                }, 2000); // Poll every 2 seconds
                
                updateBulkCheckProgress();
            } else {
                alert('Error: ' + response.data);
                button.prop('disabled', false).text('<?php _e('Start Bulk Check', 'wp-image-guardian'); ?>');
                $('#cancel-bulk-check').hide();
                $('#bulk-check-progress').hide();
            }
        });
    });
    
    // Cancel bulk check
    $('#cancel-bulk-check').on('click', function() {
        if (!confirm('<?php _e('Cancel bulk check?', 'wp-image-guardian'); ?>')) {
            return;
        }
        
        $.post(ajaxurl, {
            action: 'wp_image_guardian_cancel_bulk_check',
            nonce: '<?php echo wp_create_nonce('wp_image_guardian_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                if (bulkCheckInterval) {
                    clearInterval(bulkCheckInterval);
                    bulkCheckInterval = null;
                }
                $('#start-bulk-check').prop('disabled', false).text('<?php _e('Start Bulk Check', 'wp-image-guardian'); ?>');
                $('#cancel-bulk-check').hide();
                $('#reset-bulk-check').hide();
                alert('<?php _e('Bulk check cancelled', 'wp-image-guardian'); ?>');
                location.reload();
            } else {
                alert('Error: ' + response.data);
            }
        });
    });
    
    // Cancel and clear queue (force stop bulk generator)
    $('#reset-bulk-check').on('click', function() {
        if (!confirm('<?php _e('Cancel and clear the bulk check queue? This will stop the bulk generator immediately and clear any pending items. Use this if the bulk generator appears to be stuck or misbehaving.', 'wp-image-guardian'); ?>')) {
            return;
        }
        
        $.post(ajaxurl, {
            action: 'wp_image_guardian_reset_bulk_check',
            nonce: '<?php echo wp_create_nonce('wp_image_guardian_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                if (bulkCheckInterval) {
                    clearInterval(bulkCheckInterval);
                    bulkCheckInterval = null;
                }
                $('#start-bulk-check').prop('disabled', false).text('<?php _e('Start Bulk Check', 'wp-image-guardian'); ?>');
                $('#cancel-bulk-check').hide();
                $('#reset-bulk-check').hide();
                $('#reset-bulk-check-help').hide();
                $('#bulk-check-progress').hide();
                alert('<?php _e('Bulk check queue cleared successfully. You can now start a new bulk check.', 'wp-image-guardian'); ?>');
                location.reload();
            } else {
                alert('Error: ' + response.data);
            }
        });
    });
    
    // Track if we've already shown completion to prevent loops
    var completionShown = false;
    
    // Update bulk check progress
    function updateBulkCheckProgress() {
        // Don't poll if we've already shown completion
        if (completionShown) {
            return;
        }
        
        $.post(ajaxurl, {
            action: 'wp_image_guardian_get_bulk_progress',
            nonce: '<?php echo wp_create_nonce('wp_image_guardian_nonce'); ?>'
        }, function(response) {
            // Handle empty or malformed responses
            if (!response || typeof response !== 'object') {
                console.error('Invalid response from server');
                return;
            }
            
            if (response.success && response.data) {
                var progress = response.data;
                
                // Ensure progress has required properties
                if (!progress.status) {
                    console.error('Missing status in progress data');
                    return;
                }
                
                var percent = progress.total > 0 ? (progress.current / progress.total) * 100 : 0;
                
                $('#progress-current').text(progress.current || 0);
                $('#progress-total').text(progress.total || 0);
                $('.progress-fill').css('width', percent + '%');
                
                // Show reset button if stuck or if user might need to force stop
                if (progress.status === 'running') {
                    // Show reset button if stuck (remaining is 0 but status is running)
                    // or if cancel button is already shown (make reset available as alternative)
                    if ((progress.remaining === 0 && progress.current >= progress.total) || $('#cancel-bulk-check').is(':visible')) {
                        $('#reset-bulk-check').show();
                        $('#reset-bulk-check-help').show();
                    } else {
                        $('#reset-bulk-check').hide();
                        $('#reset-bulk-check-help').hide();
                    }
                } else {
                    $('#reset-bulk-check').hide();
                    $('#reset-bulk-check-help').hide();
                }
                
                // Handle completed or cancelled status
                if (progress.status === 'completed' || progress.status === 'cancelled') {
                    // Prevent multiple completion alerts
                    if (completionShown) {
                        return;
                    }
                    completionShown = true;
                    
                    // Clear all intervals immediately
                    if (bulkCheckInterval) {
                        clearInterval(bulkCheckInterval);
                        bulkCheckInterval = null;
                    }
                    
                    $('#start-bulk-check').prop('disabled', false).text('<?php _e('Start Bulk Check', 'wp-image-guardian'); ?>');
                    $('#cancel-bulk-check').hide();
                    $('#reset-bulk-check').hide();
                    $('#reset-bulk-check-help').hide();
                    
                    if (progress.status === 'completed') {
                        alert('<?php _e('Bulk check completed!', 'wp-image-guardian'); ?>');
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        // Cancelled - reload to refresh state
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    }
                }
            } else {
                // Handle error response
                console.error('Error response:', response);
                // Don't show alert for errors, just log
            }
        }).fail(function(xhr, status, error) {
            console.error('AJAX request failed:', status, error);
            // Stop polling on repeated failures
            if (bulkCheckInterval) {
                clearInterval(bulkCheckInterval);
                bulkCheckInterval = null;
            }
        });
    }
    
    // Check if bulk check is already running on page load
    var initialCheck = setInterval(function() {
        $.post(ajaxurl, {
            action: 'wp_image_guardian_get_bulk_progress',
            nonce: '<?php echo wp_create_nonce('wp_image_guardian_nonce'); ?>'
        }, function(response) {
            // Handle empty or malformed responses
            if (!response || typeof response !== 'object') {
                clearInterval(initialCheck);
                return;
            }
            
            if (response.success && response.data) {
                var progress = response.data;
                
                // Ensure progress has required properties
                if (!progress.status) {
                    clearInterval(initialCheck);
                    return;
                }
                
                // Only start polling if status is 'running'
                if (progress.status === 'running') {
                    $('#start-bulk-check').prop('disabled', true).text('<?php _e('Running...', 'wp-image-guardian'); ?>');
                    $('#bulk-check-progress').show();
                    
                    // Show cancel button, and also show reset button as alternative
                    $('#cancel-bulk-check').show();
                    
                    // Show reset button if stuck or as alternative to cancel
                    if (progress.remaining === 0 && progress.current >= progress.total) {
                        // Stuck state - show reset prominently
                        $('#reset-bulk-check').show();
                        $('#reset-bulk-check-help').show();
                        $('#cancel-bulk-check').hide();
                    } else {
                        // Normal running - show both cancel and reset options
                        $('#reset-bulk-check').show();
                        $('#reset-bulk-check-help').show();
                    }
                    
                    // Clear initial check and start regular polling
                    clearInterval(initialCheck);
                    bulkCheckInterval = setInterval(function() {
                        updateBulkCheckProgress();
                    }, 2000);
                    updateBulkCheckProgress();
                } else if (progress.status === 'completed' || progress.status === 'cancelled') {
                    // Already completed/cancelled - don't start polling
                    clearInterval(initialCheck);
                    // Don't show alert on page load for completed status
                } else {
                    // Idle or other status - stop initial check
                    clearInterval(initialCheck);
                }
            } else {
                // Error response - stop initial check
                clearInterval(initialCheck);
            }
        }).fail(function(xhr, status, error) {
            console.error('Initial check failed:', status, error);
            clearInterval(initialCheck);
        });
    }, 1000);
});
</script>

