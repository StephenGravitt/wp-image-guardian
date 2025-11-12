<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div id="wp-image-guardian-modal" class="wp-image-guardian-modal" style="display: none;">
    <div class="wp-image-guardian-modal-content">
        <div class="wp-image-guardian-modal-header">
            <h2><?php _e('TinyEye Search Results', 'wp-image-guardian'); ?></h2>
            <button type="button" class="wp-image-guardian-modal-close">&times;</button>
        </div>
        <div class="wp-image-guardian-modal-body">
            <div class="wp-image-guardian-loading">
                <p><?php _e('Loading results...', 'wp-image-guardian'); ?></p>
            </div>
            <div class="wp-image-guardian-results" style="display: none;">
                <!-- Results will be loaded here via AJAX -->
            </div>
        </div>
        <div class="wp-image-guardian-modal-footer">
            <button type="button" class="button button-secondary wp-image-guardian-modal-close">
                <?php _e('Close', 'wp-image-guardian'); ?>
            </button>
        </div>
    </div>
</div>

<style>
.wp-image-guardian-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 999999;
    display: flex;
    align-items: center;
    justify-content: center;
}

.wp-image-guardian-modal-content {
    background: #fff;
    border-radius: 5px;
    max-width: 90%;
    max-height: 90%;
    width: 800px;
    display: flex;
    flex-direction: column;
}

.wp-image-guardian-modal-header {
    padding: 20px;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.wp-image-guardian-modal-header h2 {
    margin: 0;
}

.wp-image-guardian-modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #666;
}

.wp-image-guardian-modal-close:hover {
    color: #000;
}

.wp-image-guardian-modal-body {
    padding: 20px;
    flex: 1;
    overflow-y: auto;
}

.wp-image-guardian-modal-footer {
    padding: 20px;
    border-top: 1px solid #ddd;
    text-align: right;
}

.wp-image-guardian-loading {
    text-align: center;
    padding: 40px;
}

.wp-image-guardian-results {
    /* Results styling will be added dynamically */
}

.wp-image-guardian-result-item {
    border: 1px solid #ddd;
    border-radius: 5px;
    padding: 15px;
    margin: 10px 0;
    display: flex;
    align-items: center;
}

.wp-image-guardian-result-thumbnail {
    width: 100px;
    height: 100px;
    object-fit: cover;
    border-radius: 5px;
    margin-right: 15px;
}

.wp-image-guardian-result-info {
    flex: 1;
}

.wp-image-guardian-result-title {
    font-weight: bold;
    margin-bottom: 5px;
}

.wp-image-guardian-result-url {
    color: #666;
    font-size: 12px;
    word-break: break-all;
}

.wp-image-guardian-result-score {
    background: #0073aa;
    color: white;
    padding: 5px 10px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: bold;
}

.wp-image-guardian-no-results {
    text-align: center;
    padding: 40px;
    color: #666;
}

.wp-image-guardian-error {
    text-align: center;
    padding: 40px;
    color: #d63638;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Modal functionality
    $('.wp-image-guardian-modal-close').on('click', function() {
        $('#wp-image-guardian-modal').hide();
    });
    
    $(document).on('click', '.view-results, .view-detailed-results', function() {
        var attachmentId = $(this).data('attachment-id');
        showResultsModal(attachmentId);
    });
    
    function showResultsModal(attachmentId) {
        $('#wp-image-guardian-modal').show();
        $('.wp-image-guardian-loading').show();
        $('.wp-image-guardian-results').hide();
        
        $.post(ajaxurl, {
            action: 'wp_image_guardian_get_modal_content',
            attachment_id: attachmentId,
            nonce: wpImageGuardian.nonce
        }, function(response) {
            $('.wp-image-guardian-loading').hide();
            
            if (response.success) {
                $('.wp-image-guardian-results').html(response.data.content).show();
            } else {
                $('.wp-image-guardian-results').html(
                    '<div class="wp-image-guardian-error">' + response.data + '</div>'
                ).show();
            }
        });
    }
    
    // Close modal when clicking outside
    $('#wp-image-guardian-modal').on('click', function(e) {
        if (e.target === this) {
            $(this).hide();
        }
    });
});
</script>
