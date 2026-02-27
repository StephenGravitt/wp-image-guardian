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
        
        var originalText = button.text();
        button.addClass('checking').prop('disabled', true).text(wpImageGuardian.strings.checking);
        
        $.post(ajaxurl, {
            action: 'wp_image_guardian_check_image',
            attachment_id: attachmentId,
            nonce: wpImageGuardian.nonce
        }, function(response) {
            button.removeClass('checking').prop('disabled', false);
            
            if (response.success) {
                // Show success message briefly, then reload
                button.text('✓ ' + wpImageGuardian.strings.checked || 'Checked');
                button.addClass('checked');
                
                // Reload page to show updated status
                setTimeout(function() {
                    location.reload();
                }, 500);
            } else {
                button.text(originalText);
                alert('Error: ' + (response.data || 'Unknown error'));
            }
        }).fail(function() {
            button.removeClass('checking').prop('disabled', false).text(originalText);
            alert('Network error. Please try again.');
        });
    });
    
    // Handle image safe toggle
    $(document).on('change', '.image-safe-toggle', function(e) {
        var checkbox = $(this);
        var attachmentId = checkbox.data('attachment-id');
        var isSafe = checkbox.is(':checked');
        
        checkbox.prop('disabled', true);
        
        $.post(ajaxurl, {
            action: 'wp_image_guardian_toggle_image_safe',
            attachment_id: attachmentId,
            is_safe: isSafe,
            nonce: wpImageGuardian.nonce
        }, function(response) {
            checkbox.prop('disabled', false);
            
            if (!response.success) {
                // Revert checkbox on error
                checkbox.prop('checked', !isSafe);
                alert('Error: ' + (response.data || 'Failed to update status'));
            }
        }).fail(function() {
            checkbox.prop('disabled', false);
            checkbox.prop('checked', !isSafe);
            alert('Network error. Please try again.');
        });
    });
    
    // Handle view results button
    $(document).on('click', '.view-results, .view-detailed-results', function(e) {
        if ($(this).is(':disabled') || $(this).hasClass('disabled')) {
            e.preventDefault();
            console.log('[WP Image Guardian] View Results button is disabled');
            return;
        }
        
        e.preventDefault();
        var attachmentId = $(this).data('attachment-id');
        console.log('[WP Image Guardian] View Results clicked for attachment:', attachmentId);
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
    
    
    function showResultsModal(attachmentId) {
        console.log('[WP Image Guardian] showResultsModal called with attachment ID:', attachmentId);
        
        $('#wp-image-guardian-modal').show();
        $('.wp-image-guardian-loading').show();
        $('.wp-image-guardian-results').hide();
        
        console.log('[WP Image Guardian] Sending AJAX request to get modal content');
        
        $.post(ajaxurl, {
            action: 'wp_image_guardian_get_modal_content',
            attachment_id: attachmentId,
            nonce: wpImageGuardian.nonce
        }, function(response) {
            console.log('[WP Image Guardian] AJAX response received:', response);
            $('.wp-image-guardian-loading').hide();
            
            if (response.success) {
                console.log('[WP Image Guardian] Response successful, content length:', response.data.content ? response.data.content.length : 0);
                $('.wp-image-guardian-results').html(response.data.content).show();
            } else {
                console.error('[WP Image Guardian] Response error:', response.data);
                $('.wp-image-guardian-results').html(
                    '<div class="wp-image-guardian-error">' + response.data + '</div>'
                ).show();
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {
            console.error('[WP Image Guardian] AJAX request failed:', {
                status: jqXHR.status,
                statusText: textStatus,
                error: errorThrown,
                responseText: jqXHR.responseText ? jqXHR.responseText.substring(0, 500) : 'No response'
            });
            $('.wp-image-guardian-loading').hide();
            $('.wp-image-guardian-results').html(
                '<div class="wp-image-guardian-error">Network error. Please check the console and debug log for details.</div>'
            ).show();
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
    
    // Auto-refresh remaining searches
    if ($('.remaining-searches-count').length) {
        setInterval(function() {
            $.post(ajaxurl, {
                action: 'wp_image_guardian_get_remaining_searches',
                nonce: wpImageGuardian.nonce
            }, function(response) {
                if (response.success && response.data.remaining_searches !== undefined) {
                    $('.remaining-searches-count').text(response.data.remaining_searches);
                    $('#remaining-searches').text(response.data.remaining_searches);
                }
            });
        }, 300000); // Refresh every 5 minutes
    }
});
