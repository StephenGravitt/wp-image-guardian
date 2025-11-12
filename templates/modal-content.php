<?php
if (!defined('ABSPATH')) {
    exit;
}

$results_data = json_decode($results->results_data, true);
$total_results = $results_data['total_results'] ?? 0;
$matches = $results_data['results'] ?? [];
?>

<div class="wp-image-guardian-modal-results">
    <div class="results-header">
        <h3><?php printf(__('Found %d similar images', 'wp-image-guardian'), $total_results); ?></h3>
        <p class="results-description">
            <?php _e('These images were found to be similar to your uploaded image. Review them carefully to determine if there are any copyright concerns.', 'wp-image-guardian'); ?>
        </p>
    </div>
    
    <?php if ($total_results === 0): ?>
        <div class="wp-image-guardian-no-results">
            <h4><?php _e('No Similar Images Found', 'wp-image-guardian'); ?></h4>
            <p><?php _e('Great! No similar images were found in the TinyEye database. This suggests your image is likely original.', 'wp-image-guardian'); ?></p>
            <div class="status-indicator safe">
                <span class="status-icon">🟢</span>
                <span class="status-text"><?php _e('Safe - No matches found', 'wp-image-guardian'); ?></span>
            </div>
        </div>
    <?php else: ?>
        <div class="results-list">
            <?php foreach ($matches as $index => $match): ?>
                <div class="wp-image-guardian-result-item">
                    <div class="result-thumbnail">
                        <?php if (isset($match['thumbnail_url'])): ?>
                            <img src="<?php echo esc_url($match['thumbnail_url']); ?>" 
                                 alt="<?php echo esc_attr($match['title'] ?? ''); ?>" 
                                 class="wp-image-guardian-result-thumbnail" />
                        <?php else: ?>
                            <div class="wp-image-guardian-result-thumbnail placeholder">
                                <?php _e('No Preview', 'wp-image-guardian'); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="result-info">
                        <div class="wp-image-guardian-result-title">
                            <?php echo esc_html($match['title'] ?? __('Untitled', 'wp-image-guardian')); ?>
                        </div>
                        
                        <?php if (isset($match['url'])): ?>
                            <div class="wp-image-guardian-result-url">
                                <a href="<?php echo esc_url($match['url']); ?>" target="_blank" rel="noopener">
                                    <?php echo esc_html($match['url']); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($match['domain'])): ?>
                            <div class="result-domain">
                                <strong><?php _e('Domain:', 'wp-image-guardian'); ?></strong> 
                                <?php echo esc_html($match['domain']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($match['size'])): ?>
                            <div class="result-size">
                                <strong><?php _e('Size:', 'wp-image-guardian'); ?></strong> 
                                <?php echo esc_html($match['size']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="result-score">
                        <?php if (isset($match['score'])): ?>
                            <div class="wp-image-guardian-result-score">
                                <?php printf(__('Score: %s%%', 'wp-image-guardian'), $match['score']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="results-footer">
            <div class="risk-assessment">
                <h4><?php _e('Risk Assessment', 'wp-image-guardian'); ?></h4>
                <?php if ($total_results <= 3): ?>
                    <div class="status-indicator warning">
                        <span class="status-icon">🟡</span>
                        <span class="status-text"><?php _e('Warning - Few matches found', 'wp-image-guardian'); ?></span>
                    </div>
                    <p><?php _e('A few similar images were found. Review them to ensure they don\'t represent copyright concerns.', 'wp-image-guardian'); ?></p>
                <?php else: ?>
                    <div class="status-indicator danger">
                        <span class="status-icon">🔴</span>
                        <span class="status-text"><?php _e('Danger - Many matches found', 'wp-image-guardian'); ?></span>
                    </div>
                    <p><?php _e('Many similar images were found. This may indicate a copyright issue. Please review carefully.', 'wp-image-guardian'); ?></p>
                <?php endif; ?>
            </div>
            
            <div class="action-buttons">
                <button type="button" class="button button-primary mark-safe" data-attachment-id="<?php echo $results->attachment_id; ?>">
                    <?php _e('Mark as Safe', 'wp-image-guardian'); ?>
                </button>
                <button type="button" class="button button-secondary mark-unsafe" data-attachment-id="<?php echo $results->attachment_id; ?>">
                    <?php _e('Mark as Unsafe', 'wp-image-guardian'); ?>
                </button>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.wp-image-guardian-modal-results {
    max-height: 600px;
    overflow-y: auto;
}

.results-header {
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #ddd;
}

.results-header h3 {
    margin: 0 0 10px 0;
    color: #0073aa;
}

.results-description {
    color: #666;
    margin: 0;
}

.results-list {
    margin: 20px 0;
}

.wp-image-guardian-result-item {
    border: 1px solid #ddd;
    border-radius: 5px;
    padding: 15px;
    margin: 10px 0;
    display: flex;
    align-items: flex-start;
    gap: 15px;
}

.result-thumbnail {
    flex-shrink: 0;
}

.wp-image-guardian-result-thumbnail {
    width: 100px;
    height: 100px;
    object-fit: cover;
    border-radius: 5px;
    border: 1px solid #ddd;
}

.wp-image-guardian-result-thumbnail.placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f5f5f5;
    color: #666;
    font-size: 12px;
    text-align: center;
}

.result-info {
    flex: 1;
    min-width: 0;
}

.wp-image-guardian-result-title {
    font-weight: bold;
    margin-bottom: 8px;
    color: #333;
}

.wp-image-guardian-result-url {
    margin-bottom: 8px;
}

.wp-image-guardian-result-url a {
    color: #0073aa;
    text-decoration: none;
    word-break: break-all;
}

.wp-image-guardian-result-url a:hover {
    text-decoration: underline;
}

.result-domain, .result-size {
    font-size: 12px;
    color: #666;
    margin-bottom: 4px;
}

.result-score {
    flex-shrink: 0;
    display: flex;
    align-items: center;
}

.wp-image-guardian-result-score {
    background: #0073aa;
    color: white;
    padding: 5px 10px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: bold;
}

.results-footer {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #ddd;
}

.risk-assessment {
    margin-bottom: 20px;
}

.risk-assessment h4 {
    margin: 0 0 10px 0;
    color: #333;
}

.status-indicator {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    border-radius: 5px;
    margin-bottom: 10px;
}

.status-indicator.safe {
    background: #d4edda;
    border-left: 4px solid #28a745;
}

.status-indicator.warning {
    background: #fff3cd;
    border-left: 4px solid #ffc107;
}

.status-indicator.danger {
    background: #f8d7da;
    border-left: 4px solid #dc3545;
}

.status-icon {
    font-size: 18px;
}

.status-text {
    font-weight: bold;
}

.action-buttons {
    display: flex;
    gap: 10px;
    justify-content: center;
}

.wp-image-guardian-no-results {
    text-align: center;
    padding: 40px;
    color: #666;
}

.wp-image-guardian-no-results h4 {
    color: #28a745;
    margin-bottom: 10px;
}

.wp-image-guardian-no-results .status-indicator {
    justify-content: center;
    margin-top: 20px;
}
</style>

<script>
jQuery(document).ready(function($) {
    $('.mark-safe, .mark-unsafe').on('click', function() {
        var attachmentId = $(this).data('attachment-id');
        var action = $(this).hasClass('mark-safe') ? 'mark_safe' : 'mark_unsafe';
        
        $.post(ajaxurl, {
            action: 'wp_image_guardian_' + action,
            attachment_id: attachmentId,
            nonce: wpImageGuardian.nonce
        }, function(response) {
            if (response.success) {
                alert(response.data);
                $('#wp-image-guardian-modal').hide();
                location.reload();
            } else {
                alert('Error: ' + response.data);
            }
        });
    });
});
</script>
