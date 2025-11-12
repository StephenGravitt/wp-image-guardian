jQuery(document).ready(function($) {
    'use strict';
    
    // Image Guardian Admin JavaScript
    
    // Initialize tooltips
    $('[data-tooltip]').each(function() {
        $(this).attr('title', $(this).data('tooltip'));
    });
    
    // Handle check image button clicks
    $(document).on('click', '.wp-image-guardian-check-image', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var attachmentId = button.data('attachment-id');
        
        if (button.hasClass('checking')) {
            return;
        }
        
        button.addClass('checking').text(wpImageGuardian.strings.checking);
        
        $.post(ajaxurl, {
            action: 'wp_image_guardian_check_image',
            attachment_id: attachmentId,
            nonce: wpImageGuardian.nonce
        }, function(response) {
            button.removeClass('checking');
            
            if (response.success) {
                button.text(wpImageGuardian.strings.safe);
                button.addClass('checked');
                
                // Update status display
                updateImageStatus(attachmentId, response.data);
                
                // Reload page to show updated status
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                button.text(wpImageGuardian.strings.error);
                alert('Error: ' + response.data);
            }
        });
    });
    
    // Handle mark safe/unsafe buttons
    $(document).on('click', '.mark-safe, .mark-unsafe', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var attachmentId = button.data('attachment-id');
        var action = button.hasClass('mark-safe') ? 'mark_safe' : 'mark_unsafe';
        
        if (button.hasClass('processing')) {
            return;
        }
        
        button.addClass('processing').prop('disabled', true);
        
        $.post(ajaxurl, {
            action: 'wp_image_guardian_' + action,
            attachment_id: attachmentId,
            nonce: wpImageGuardian.nonce
        }, function(response) {
            button.removeClass('processing').prop('disabled', false);
            
            if (response.success) {
                // Update status display
                updateImageStatus(attachmentId, { user_decision: action.replace('mark_', '') });
                button.closest('.status-actions').html('<span class="marked">' + response.data + '</span>');
            } else {
                alert('Error: ' + response.data);
            }
        });
    });
    
    // Handle view results button
    $(document).on('click', '.view-results, .view-detailed-results', function(e) {
        e.preventDefault();
        
        var attachmentId = $(this).data('attachment-id');
        showResultsModal(attachmentId);
    });
    
    // Handle bulk check
    $(document).on('click', '.wp-image-guardian-bulk-check', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var attachmentIds = [];
        
        $('.wp-image-guardian-bulk-checkbox:checked').each(function() {
            attachmentIds.push($(this).val());
        });
        
        if (attachmentIds.length === 0) {
            alert('Please select images to check.');
            return;
        }
        
        if (!wpImageGuardian.isPremium) {
            alert('Bulk checking is a premium feature. Please upgrade your account.');
            return;
        }
        
        if (!confirm('Check ' + attachmentIds.length + ' images? This may take a while.')) {
            return;
        }
        
        button.addClass('processing').prop('disabled', true).text('Checking...');
        
        $.post(ajaxurl, {
            action: 'wp_image_guardian_bulk_check',
            attachment_ids: attachmentIds,
            nonce: wpImageGuardian.nonce
        }, function(response) {
            button.removeClass('processing').prop('disabled', false).text('Bulk Check');
            
            if (response.success) {
                alert('Bulk check completed. ' + response.data.length + ' images processed.');
                location.reload();
            } else {
                alert('Error: ' + response.data);
            }
        });
    });
    
    // Handle auto check toggle
    $(document).on('change', '#wp_image_guardian_auto_check', function() {
        var enabled = $(this).is(':checked');
        
        $.post(ajaxurl, {
            action: 'wp_image_guardian_auto_check_toggle',
            enabled: enabled,
            nonce: wpImageGuardian.nonce
        }, function(response) {
            if (response.success) {
                // Show success message
                showNotice('Auto-check settings updated.', 'success');
            } else {
                alert('Error: ' + response.data);
                // Revert checkbox
                $('#wp_image_guardian_auto_check').prop('checked', !enabled);
            }
        });
    });
    
    // Handle disconnect OAuth
    $(document).on('click', '#disconnect-oauth', function(e) {
        e.preventDefault();
        
        if (confirm('Are you sure you want to disconnect from Image Guardian?')) {
            $.post(ajaxurl, {
                action: 'wp_image_guardian_disconnect',
                nonce: wpImageGuardian.nonce
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error disconnecting. Please try again.');
                }
            });
        }
    });
    
    // Utility functions
    function updateImageStatus(attachmentId, data) {
        var statusContainer = $('.wp-image-guardian-status[data-attachment-id="' + attachmentId + '"]');
        if (statusContainer.length) {
            // Update status display based on data
            var riskLevel = data.risk_level || 'unknown';
            var userDecision = data.user_decision;
            
            statusContainer.removeClass('status-safe status-warning status-danger status-unknown')
                          .addClass('status-' + riskLevel);
            
            if (userDecision) {
                statusContainer.addClass('user-marked-' + userDecision);
            }
        }
    }
    
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
    
    function showNotice(message, type) {
        type = type || 'info';
        var noticeClass = 'notice-' + type;
        var notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
        
        $('.wrap h1').after(notice);
        
        setTimeout(function() {
            notice.fadeOut();
        }, 5000);
    }
    
    // Close modal when clicking outside
    $(document).on('click', '#wp-image-guardian-modal', function(e) {
        if (e.target === this) {
            $(this).hide();
        }
    });
    
    // Close modal with escape key
    $(document).on('keydown', function(e) {
        if (e.keyCode === 27) { // Escape key
            $('#wp-image-guardian-modal').hide();
        }
    });
    
    // Initialize status indicators
    $('.wp-image-guardian-status').each(function() {
        var status = $(this);
        var attachmentId = status.data('attachment-id');
        
        // Add click handlers for status actions
        status.find('.mark-safe, .mark-unsafe').on('click', function(e) {
            e.preventDefault();
            var action = $(this).hasClass('mark-safe') ? 'mark_safe' : 'mark_unsafe';
            
            $(this).addClass('processing').prop('disabled', true);
            
            $.post(ajaxurl, {
                action: 'wp_image_guardian_' + action,
                attachment_id: attachmentId,
                nonce: wpImageGuardian.nonce
            }, function(response) {
                if (response.success) {
                    status.find('.status-actions').html('<span class="marked">' + response.data + '</span>');
                } else {
                    alert('Error: ' + response.data);
                }
            });
        });
    });
    
    // Auto-refresh usage stats
    if ($('.wp-image-guardian-stats').length) {
        setInterval(function() {
            $.post(ajaxurl, {
                action: 'wp_image_guardian_get_usage_stats',
                nonce: wpImageGuardian.nonce
            }, function(response) {
                if (response.success) {
                    updateUsageStats(response.data);
                }
            });
        }, 30000); // Refresh every 30 seconds
    }
    
    function updateUsageStats(data) {
        $('.stat-box').each(function() {
            var stat = $(this);
            var type = stat.data('stat-type');
            
            if (type && data[type] !== undefined) {
                stat.find('h3').text(data[type]);
            }
        });
    }
});
