<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Bulk Check Images', 'wp-image-guardian'); ?></h1>
    
    <div class="wp-image-guardian-bulk-check">
        <div class="bulk-actions">
            <h3><?php _e('Bulk Actions', 'wp-image-guardian'); ?></h3>
            <p><?php _e('Select images below and click "Check Selected Images" to check them for copyright issues.', 'wp-image-guardian'); ?></p>
            
            <div class="bulk-controls">
                <label>
                    <input type="checkbox" id="select-all-images" />
                    <?php _e('Select All', 'wp-image-guardian'); ?>
                </label>
                <button type="button" class="button button-primary wp-image-guardian-bulk-check">
                    <?php _e('Check Selected Images', 'wp-image-guardian'); ?>
                </button>
                <span class="bulk-status">
                    <span id="selected-count">0</span> <?php _e('images selected', 'wp-image-guardian'); ?>
                </span>
            </div>
        </div>
        
        <div class="bulk-stats">
            <h3><?php _e('Statistics', 'wp-image-guardian'); ?></h3>
            <div class="stats-grid">
                <div class="stat-item">
                    <strong><?php echo count($unchecked_images); ?></strong>
                    <span><?php _e('Unchecked Images', 'wp-image-guardian'); ?></span>
                </div>
                <div class="stat-item">
                    <strong><?php echo count($checked_images); ?></strong>
                    <span><?php _e('Recently Checked', 'wp-image-guardian'); ?></span>
                </div>
            </div>
        </div>
        
        <div class="image-grid">
            <?php if (!empty($unchecked_images)): ?>
                <h3><?php _e('Unchecked Images (Last 7 Days)', 'wp-image-guardian'); ?></h3>
                <?php foreach ($unchecked_images as $image): ?>
                    <div class="image-item">
                        <input type="checkbox" class="wp-image-guardian-bulk-checkbox" 
                               value="<?php echo $image->ID; ?>" 
                               data-image-id="<?php echo $image->ID; ?>" />
                        <div class="image-preview">
                            <?php echo wp_get_attachment_image($image->ID, 'thumbnail', false, ['class' => 'image-thumbnail']); ?>
                        </div>
                        <div class="image-title"><?php echo esc_html($image->post_title); ?></div>
                        <div class="image-status unchecked"><?php _e('Not Checked', 'wp-image-guardian'); ?></div>
                        <div class="image-date">
                            <?php echo human_time_diff(strtotime($image->post_date), current_time('timestamp')); ?> <?php _e('ago', 'wp-image-guardian'); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-images">
                    <p><?php _e('No unchecked images found in the last 7 days.', 'wp-image-guardian'); ?></p>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($checked_images)): ?>
            <div class="recently-checked">
                <h3><?php _e('Recently Checked Images', 'wp-image-guardian'); ?></h3>
                <div class="image-grid">
                    <?php foreach ($checked_images as $check): ?>
                        <div class="image-item checked">
                            <div class="image-preview">
                                <?php echo wp_get_attachment_image($check->attachment_id, 'thumbnail', false, ['class' => 'image-thumbnail']); ?>
                            </div>
                            <div class="image-title"><?php echo esc_html($check->post_title); ?></div>
                            <div class="image-status <?php echo $check->risk_level; ?>">
                                <?php
                                switch ($check->risk_level) {
                                    case 'safe':
                                        _e('Safe', 'wp-image-guardian');
                                        break;
                                    case 'warning':
                                        _e('Warning', 'wp-image-guardian');
                                        break;
                                    case 'danger':
                                        _e('Danger', 'wp-image-guardian');
                                        break;
                                    default:
                                        _e('Unknown', 'wp-image-guardian');
                                }
                                ?>
                            </div>
                            <div class="image-results">
                                <?php printf(__('%d matches', 'wp-image-guardian'), $check->results_count); ?>
                            </div>
                            <div class="image-date">
                                <?php echo human_time_diff(strtotime($check->checked_at), current_time('timestamp')); ?> <?php _e('ago', 'wp-image-guardian'); ?>
                            </div>
                            <div class="image-actions">
                                <a href="<?php echo admin_url('post.php?post=' . $check->attachment_id . '&action=edit'); ?>" 
                                   class="button button-small">
                                    <?php _e('View', 'wp-image-guardian'); ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Select all functionality
    $('#select-all-images').on('change', function() {
        var checked = $(this).is(':checked');
        $('.wp-image-guardian-bulk-checkbox').prop('checked', checked);
        updateSelectedCount();
    });
    
    // Individual checkbox changes
    $('.wp-image-guardian-bulk-checkbox').on('change', function() {
        updateSelectedCount();
        updateSelectAllState();
    });
    
    function updateSelectedCount() {
        var count = $('.wp-image-guardian-bulk-checkbox:checked').length;
        $('#selected-count').text(count);
    }
    
    function updateSelectAllState() {
        var total = $('.wp-image-guardian-bulk-checkbox').length;
        var checked = $('.wp-image-guardian-bulk-checkbox:checked').length;
        
        if (checked === 0) {
            $('#select-all-images').prop('indeterminate', false).prop('checked', false);
        } else if (checked === total) {
            $('#select-all-images').prop('indeterminate', false).prop('checked', true);
        } else {
            $('#select-all-images').prop('indeterminate', true);
        }
    }
    
    // Initialize
    updateSelectedCount();
    updateSelectAllState();
});
</script>

<style>
.wp-image-guardian-bulk-check {
    background: #f9f9f9;
    padding: 20px;
    border-radius: 5px;
    margin: 20px 0;
}

.bulk-actions {
    background: white;
    padding: 20px;
    border-radius: 5px;
    border: 1px solid #ddd;
    margin-bottom: 20px;
}

.bulk-actions h3 {
    margin-top: 0;
    color: #0073aa;
}

.bulk-controls {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-top: 15px;
}

.bulk-controls label {
    display: flex;
    align-items: center;
    gap: 5px;
    font-weight: bold;
}

.bulk-status {
    color: #666;
    font-size: 14px;
}

.bulk-stats {
    background: white;
    padding: 20px;
    border-radius: 5px;
    border: 1px solid #ddd;
    margin-bottom: 20px;
}

.bulk-stats h3 {
    margin-top: 0;
    color: #0073aa;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 20px;
    margin-top: 15px;
}

.stat-item {
    text-align: center;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 5px;
    border-left: 4px solid #0073aa;
}

.stat-item strong {
    display: block;
    font-size: 2em;
    color: #0073aa;
    margin-bottom: 5px;
}

.stat-item span {
    color: #666;
    font-size: 14px;
}

.image-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.image-item {
    background: white;
    border: 1px solid #ddd;
    border-radius: 5px;
    padding: 15px;
    text-align: center;
    position: relative;
    transition: all 0.3s ease;
}

.image-item:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.image-item input[type="checkbox"] {
    position: absolute;
    top: 10px;
    left: 10px;
    z-index: 1;
}

.image-preview {
    margin-bottom: 10px;
}

.image-thumbnail {
    width: 100%;
    height: 120px;
    object-fit: cover;
    border-radius: 3px;
}

.image-title {
    font-size: 12px;
    font-weight: bold;
    margin-bottom: 8px;
    word-break: break-word;
    line-height: 1.3;
}

.image-status {
    font-size: 11px;
    padding: 4px 8px;
    border-radius: 3px;
    display: inline-block;
    margin-bottom: 8px;
    font-weight: bold;
}

.image-status.unchecked {
    background: #e2e3e5;
    color: #6c757d;
}

.image-status.checked {
    background: #d4edda;
    color: #28a745;
}

.image-status.safe {
    background: #d4edda;
    color: #28a745;
}

.image-status.warning {
    background: #fff3cd;
    color: #856404;
}

.image-status.danger {
    background: #f8d7da;
    color: #721c24;
}

.image-results {
    font-size: 11px;
    color: #666;
    margin-bottom: 8px;
}

.image-date {
    font-size: 11px;
    color: #999;
    margin-bottom: 10px;
}

.image-actions {
    margin-top: 10px;
}

.image-actions .button {
    font-size: 11px;
    padding: 4px 8px;
    height: auto;
    line-height: 1.4;
}

.no-images {
    text-align: center;
    padding: 40px;
    color: #666;
    background: white;
    border-radius: 5px;
    border: 1px solid #ddd;
}

.recently-checked {
    margin-top: 30px;
}

.recently-checked h3 {
    color: #0073aa;
    margin-bottom: 15px;
}

.image-item.checked {
    border-left: 4px solid #28a745;
}

@media (max-width: 768px) {
    .bulk-controls {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .image-grid {
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>
