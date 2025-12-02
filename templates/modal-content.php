<?php
if (!defined('ABSPATH')) {
    exit;
}

// results_data should already be an array (decoded by get_image_results)
$results_data = is_array($results->results_data) ? $results->results_data : (is_string($results->results_data) ? json_decode($results->results_data, true) : []);

// Handle different data structures - check all possible locations
$matches = [];

// Check normalized format first
if (!empty($results_data['matches']) && is_array($results_data['matches'])) {
    $matches = $results_data['matches'];
} elseif (!empty($results_data['results']) && is_array($results_data['results'])) {
    $matches = $results_data['results'];
}

// If no matches in normalized format, check raw_response
if (empty($matches) && !empty($results_data['raw_response']['results']['matches']) && is_array($results_data['raw_response']['results']['matches'])) {
    $matches = $results_data['raw_response']['results']['matches'];
}

// Get total results - use database field first (most reliable since it's what's displayed in status)
$total_results = !empty($results->results_count) ? absint($results->results_count) : 0;

// If database field is 0, try parsed data
if ($total_results === 0) {
    if (!empty($results_data['total_results'])) {
        $total_results = absint($results_data['total_results']);
    } elseif (!empty($results_data['raw_response']['stats']['total_filtered_results'])) {
        $total_results = absint($results_data['raw_response']['stats']['total_filtered_results']);
    } elseif (!empty($results_data['raw_response']['stats']['total_results'])) {
        $total_results = absint($results_data['raw_response']['stats']['total_results']);
    } elseif (!empty($matches)) {
        $total_results = count($matches);
    }
}

$match_percentage = $results->match_percentage ?? $results_data['match_percentage'] ?? null;
$risk_level = $results->risk_level ?? 'unknown';
?>

<div class="wp-image-guardian-modal-results">
    <div class="results-header">
        <h3><?php printf(__('Found %d similar images', 'wp-image-guardian'), $total_results); ?></h3>
        <p class="results-description">
            <?php _e('These images were found to be similar to your uploaded image. Review them carefully to determine if there are any copyright concerns.', 'wp-image-guardian'); ?>
        </p>
    </div>
    
    <?php if ($total_results === 0 || empty($matches)): ?>
        <div class="wp-image-guardian-no-results">
            <?php 
            // Check if there's a reason for no results (e.g., unsupported format)
            $reason = $results_data['reason'] ?? '';
            $message = $results_data['message'] ?? '';
            
            if ($reason === 'unsupported_format'): ?>
                <h4><?php _e('Unsupported Image Format', 'wp-image-guardian'); ?></h4>
                <p class="format-error"><?php echo esc_html($message ?: __('This image format is not supported by TinyEye and was not checked.', 'wp-image-guardian')); ?></p>
                <p class="description">
                    <strong><?php _e('Supported formats:', 'wp-image-guardian'); ?></strong><br>
                    JPEG, PNG, WebP, GIF, BMP, AVIF, TIFF, HEIC, HEIF
                </p>
            <?php else: ?>
                <h4><?php _e('No Similar Images Found', 'wp-image-guardian'); ?></h4>
                <p><?php _e('Great! No similar images were found in the TinyEye database. This suggests your image is likely original.', 'wp-image-guardian'); ?></p>
            <?php endif; ?>
            
            <?php if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG): ?>
                <div style="background: #f0f0f0; padding: 10px; margin: 10px 0; font-size: 11px; font-family: monospace; border: 1px solid #ddd;">
                    <strong>Debug Info:</strong><br>
                    Total Results: <?php echo esc_html($total_results); ?><br>
                    Matches Count: <?php echo esc_html(count($matches)); ?><br>
                    Has results_data: <?php echo !empty($results_data) ? 'Yes' : 'No'; ?><br>
                    Has matches key: <?php echo !empty($results_data['matches']) ? 'Yes (' . count($results_data['matches']) . ')' : 'No'; ?><br>
                    Has raw_response: <?php echo !empty($results_data['raw_response']) ? 'Yes' : 'No'; ?><br>
                    Results Count (DB): <?php echo esc_html($results->results_count ?? 'N/A'); ?><br>
                    <?php if (!empty($results_data) && is_array($results_data)): ?>
                        Top-level keys: <?php echo esc_html(implode(', ', array_keys($results_data))); ?><br>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($reason !== 'unsupported_format'): ?>
                <div class="status-indicator safe">
                    <span class="status-icon">🟢</span>
                    <span class="status-text"><?php _e('Safe - No matches found', 'wp-image-guardian'); ?></span>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="results-list">
            <?php foreach ($matches as $match): ?>
                <div class="wp-image-guardian-result-item">
                    <div class="result-thumbnail">
                        <?php 
                        $image_url = $match['image_url'] ?? $match['thumbnail_url'] ?? null;
                        if ($image_url): 
                        ?>
                            <img src="<?php echo esc_url($image_url); ?>" 
                                 alt="<?php echo esc_attr($match['domain'] ?? __('Similar Image', 'wp-image-guardian')); ?>" 
                                 class="wp-image-guardian-result-thumbnail"
                                 onerror="this.parentElement.innerHTML='<div class=\'wp-image-guardian-result-thumbnail placeholder\'><?php _e('No Preview', 'wp-image-guardian'); ?></div>';" />
                        <?php else: ?>
                            <div class="wp-image-guardian-result-thumbnail placeholder">
                                <?php _e('No Preview', 'wp-image-guardian'); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="result-info">
                        <?php 
                        $score = $match['query_match_percent'] ?? $match['score'] ?? $match['match_percentage'] ?? $match['percentage'] ?? null;
                        $score_value = $score !== null && is_numeric($score) ? floatval($score) : 0;
                        ?>
                        
                        <div class="result-header-row">
                            <div class="result-score-badge">
                                <div class="wp-image-guardian-result-score" data-score="<?php echo esc_attr($score_value); ?>">
                                    <div class="score-label"><?php _e('Match', 'wp-image-guardian'); ?></div>
                                    <div class="score-value"><?php echo number_format($score_value, 1); ?>%</div>
                                </div>
                            </div>
                            <?php if (!empty($match['domain'])): ?>
                                <div class="result-domain-badge">
                                    <strong><?php echo esc_html($match['domain']); ?></strong>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!empty($match['backlinks']) && is_array($match['backlinks']) && !empty($match['backlinks'][0])): ?>
                            <?php $backlink = $match['backlinks'][0]; ?>
                            <div class="wp-image-guardian-result-url">
                                <strong><?php _e('Source:', 'wp-image-guardian'); ?></strong>
                                <a href="<?php echo esc_url($backlink['backlink'] ?? $backlink['url'] ?? ''); ?>" target="_blank" rel="noopener">
                                    <?php echo esc_html($backlink['backlink'] ?? $backlink['url'] ?? ''); ?>
                                </a>
                            </div>
                        <?php elseif (!empty($match['url'])): ?>
                            <div class="wp-image-guardian-result-url">
                                <strong><?php _e('URL:', 'wp-image-guardian'); ?></strong>
                                <a href="<?php echo esc_url($match['url']); ?>" target="_blank" rel="noopener">
                                    <?php echo esc_html($match['url']); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <div class="result-meta">
                            <?php if (!empty($match['width']) && !empty($match['height'])): ?>
                                <div><strong><?php _e('Dimensions', 'wp-image-guardian'); ?>:</strong> <?php echo intval($match['width']); ?> × <?php echo intval($match['height']); ?> px</div>
                            <?php endif; ?>
                            
                            <?php if (!empty($match['filesize'])): ?>
                                <div><strong><?php _e('File Size', 'wp-image-guardian'); ?>:</strong> <?php echo number_format($match['filesize']); ?> bytes</div>
                            <?php endif; ?>
                            
                            <?php if (!empty($match['format'])): ?>
                                <div><strong><?php _e('Format', 'wp-image-guardian'); ?>:</strong> <?php echo esc_html($match['format']); ?></div>
                            <?php endif; ?>
                            
                            <?php if (!empty($backlink['crawl_date'])): ?>
                                <div><strong><?php _e('Crawled', 'wp-image-guardian'); ?>:</strong> <?php echo esc_html($backlink['crawl_date']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="results-footer">
            <div class="risk-assessment">
                <h4><?php _e('Risk Assessment', 'wp-image-guardian'); ?></h4>
                <?php 
                // Use match percentage if available, otherwise use count
                $display_risk = $risk_level;
                if (in_array($display_risk, ['safe', 'warning', 'danger'])) {
                    // Map old risk levels
                    $risk_map = ['safe' => 'low', 'warning' => 'medium', 'danger' => 'high'];
                    $display_risk = $risk_map[$display_risk] ?? $display_risk;
                }
                
                $risk_classes = [
                    'high' => 'danger',
                    'medium' => 'warning',
                    'low' => 'safe',
                ];
                $risk_class = $risk_classes[$display_risk] ?? 'warning';
                ?>
                <div class="status-indicator <?php echo esc_attr($risk_class); ?>">
                    <span class="status-icon">
                        <?php 
                        switch ($display_risk) {
                            case 'high': echo '🔴'; break;
                            case 'medium': echo '🟡'; break;
                            case 'low': echo '🟢'; break;
                            default: echo '⚪';
                        }
                        ?>
                    </span>
                    <span class="status-text">
                        <?php 
                        switch ($display_risk) {
                            case 'high': _e('High Risk', 'wp-image-guardian'); break;
                            case 'medium': _e('Medium Risk', 'wp-image-guardian'); break;
                            case 'low': _e('Low Risk', 'wp-image-guardian'); break;
                            default: _e('Unknown Risk', 'wp-image-guardian');
                        }
                        ?>
                    </span>
                </div>
                <?php if ($match_percentage !== null): ?>
                    <p class="match-percentage-info">
                        <strong><?php _e('Match Percentage:', 'wp-image-guardian'); ?></strong> 
                        <?php echo number_format($match_percentage, 1); ?>%
                    </p>
                <?php endif; ?>
                <p>
                    <?php 
                    if ($display_risk === 'high') {
                        _e('Many similar images were found with high match scores. This may indicate a copyright issue. Please review carefully.', 'wp-image-guardian');
                    } elseif ($display_risk === 'medium') {
                        _e('Some similar images were found. Review them to ensure they don\'t represent copyright concerns.', 'wp-image-guardian');
                    } else {
                        _e('Few or no similar images were found. This suggests your image is likely original.', 'wp-image-guardian');
                    }
                    ?>
                </p>
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
    flex-direction: column;
    gap: 12px;
}

.result-thumbnail {
    width: 100%;
}

.wp-image-guardian-result-thumbnail {
    width: 100%;
    max-width: 260px;
    height: auto;
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

.result-header-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
    flex-wrap: wrap;
}

.result-score-badge {
    flex-shrink: 0;
}

.wp-image-guardian-result-score {
    background: #0073aa;
    color: white;
    padding: 8px 12px;
    border-radius: 5px;
    text-align: center;
    min-width: 70px;
    display: inline-block;
}

.wp-image-guardian-result-score .score-label {
    font-size: 10px;
    opacity: 0.9;
    margin-bottom: 2px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.wp-image-guardian-result-score .score-value {
    font-size: 20px;
    font-weight: bold;
    line-height: 1.2;
}

.result-domain-badge {
    background: #f0f0f0;
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 13px;
    display: inline-block;
}

.wp-image-guardian-result-url {
    margin-bottom: 10px;
    word-break: break-all;
}

.wp-image-guardian-result-url a {
    color: #0073aa;
    text-decoration: none;
}

.wp-image-guardian-result-url a:hover {
    text-decoration: underline;
}

.result-meta {
    display: flex;
    flex-direction: column;
    gap: 4px;
    font-size: 12px;
    color: #666;
    margin-top: 8px;
}

.result-meta div {
    line-height: 1.5;
}

.match-percentage-info {
    margin: 10px 0;
    padding: 10px;
    background: #f5f5f5;
    border-radius: 3px;
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

.wp-image-guardian-no-results .format-error {
    color: #856404;
    background: #fff3cd;
    padding: 12px;
    border-radius: 4px;
    border-left: 4px solid #ffc107;
    margin: 15px 0;
}

.wp-image-guardian-no-results .description {
    background: #f8f9fa;
    padding: 12px;
    border-radius: 4px;
    border: 1px solid #dee2e6;
    margin-top: 15px;
    font-size: 13px;
    text-align: left;
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
