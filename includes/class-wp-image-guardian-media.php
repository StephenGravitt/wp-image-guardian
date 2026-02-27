<?php

if (!defined('ABSPATH')) {
    exit;
}

class WP_Image_Guardian_Media {
    
    private $database;
    private $api;
    
    public function __construct() {
        $this->database = new WP_Image_Guardian_Database();
        $this->api = new WP_Image_Guardian_API();
    }
    
    public function init() {
        add_filter('attachment_fields_to_edit', [$this, 'add_image_guardian_fields'], 10, 2);
        add_filter('attachment_fields_to_save', [$this, 'save_image_guardian_fields'], 10, 2);
        add_action('admin_footer', [$this, 'add_media_modal']);
        add_action('wp_ajax_wp_image_guardian_get_modal_content', [$this, 'get_modal_content']);
        
        // Media library columns & actions
        add_filter('manage_upload_columns', [$this, 'add_media_columns']);
        add_action('manage_media_custom_column', [$this, 'render_media_columns'], 10, 2);
        add_filter('media_row_actions', [$this, 'add_media_row_actions'], 10, 2);
        
        // Media library filters
        add_action('restrict_manage_posts', [$this, 'add_media_library_filters']);
        add_action('pre_get_posts', [$this, 'filter_media_library_query']);
        
        // Manual risk override
        add_action('wp_ajax_wp_image_guardian_set_manual_risk', [$this, 'ajax_set_manual_risk']);
        
        // Image safe toggle
        add_action('wp_ajax_wp_image_guardian_toggle_image_safe', [$this, 'ajax_toggle_image_safe']);
    }
    
    public function add_image_guardian_fields($fields, $post) {
        if (!wp_attachment_is_image($post->ID)) {
            return $fields;
        }
        
        $check_status = $this->database->get_image_status($post->ID);
        $risk_level = $check_status ? $check_status->risk_level : 'unknown';
        $user_decision = $check_status ? $check_status->user_decision : null;
        
        $status_html = $this->get_status_html($risk_level, $user_decision, $post->ID);
        
        $fields['image_guardian_status'] = [
            'label' => __('Image Guardian Status', 'wp-image-guardian'),
            'input' => 'html',
            'html' => $status_html,
        ];
        
        if ($check_status && $check_status->status === 'completed') {
            $fields['image_guardian_results'] = [
                'label' => __('TinyEye Results', 'wp-image-guardian'),
                'input' => 'html',
                'html' => $this->get_results_html($post->ID, $check_status),
            ];
        }
        
        return $fields;
    }
    
    public function save_image_guardian_fields($post, $attachment) {
        // Handle any save operations if needed
        return $post;
    }
    
    private function get_status_html($risk_level, $user_decision, $attachment_id) {
        $is_checked_meta = get_post_meta($attachment_id, '_wp_image_guardian_checked', true);
        $total_results = intval(get_post_meta($attachment_id, '_wp_image_guardian_total_results', true));
        $has_results = $total_results > 0;
        $is_queued = get_post_meta($attachment_id, '_wp_image_guardian_queued', true);

        $is_checked = ($risk_level !== 'unknown') || (bool) $is_checked_meta;
        
        // Determine status text
        if ($is_queued && !$is_checked) {
            $checked_text = __('Pending Check', 'wp-image-guardian');
            $status_class = 'pending';
        } elseif ($is_checked) {
            $checked_text = __('Checked', 'wp-image-guardian');
            $status_class = 'checked';
        } else {
            $checked_text = __('Not Checked', 'wp-image-guardian');
            $status_class = 'not-checked';
        }
        
        // Get risk level display text & class
        $normalized_risk = $this->normalize_risk_level($risk_level);
        $risk_level_text = $this->get_risk_level_display($normalized_risk);
        
        // Determine if image should be marked as safe
        $is_safe = false;
        if ($user_decision === 'safe') {
            $is_safe = true;
        } elseif ($user_decision === 'unsafe') {
            $is_safe = false;
        } elseif (in_array($normalized_risk, ['low'], true)) {
            $is_safe = true;
        }
        
        $html  = '<div class="wp-image-guardian-status" data-attachment-id="' . $attachment_id . '">';
        
        // Checked/Not Checked/Pending Status
        $html .= '<div class="status-row">';
        $html .= '<span class="status-label">' . __('Status:', 'wp-image-guardian') . '</span>';
        $html .= '<span class="status-badge ' . esc_attr($status_class) . '">' . esc_html($checked_text) . '</span>';
        if ($is_queued && !$is_checked) {
            $queued_at = get_post_meta($attachment_id, '_wp_image_guardian_queued_at', true);
            if ($queued_at) {
                $html .= '<span class="status-note">' . sprintf(__('Queued %s ago', 'wp-image-guardian'), human_time_diff(strtotime($queued_at))) . '</span>';
            }
        }
        $html .= '</div>';
        
        // Risk Level (always show, falls back to Inconclusive)
        $html .= '<div class="status-row">';
        $html .= '<span class="status-label">' . __('Risk Level:', 'wp-image-guardian') . '</span>';
        $html .= '<span class="risk-level-badge risk-' . esc_attr($normalized_risk) . '">' . esc_html($risk_level_text) . '</span>';
        $html .= '</div>';
        
        if ($is_checked) {
            $html .= '<div class="status-row">';
            $html .= '<span class="status-label">' . __('Matches:', 'wp-image-guardian') . '</span>';
            $html .= '<span class="match-count">' . number_format($total_results) . '</span>';
            $html .= '</div>';
        }
        
        // Image Safe Toggle
        $html .= '<div class="status-row image-safe-row">';
        $html .= '<label class="image-safe-label">';
        $html .= '<input type="checkbox" class="image-safe-toggle" data-attachment-id="' . $attachment_id . '" ' . checked($is_safe, true, false) . ' ' . ($is_checked ? '' : 'disabled') . ' />';
        $html .= '<span>' . __('Image Safe', 'wp-image-guardian') . '</span>';
        $html .= '</label>';
        if (!$is_checked) {
            $html .= '<span class="description">' . __('Check image first', 'wp-image-guardian') . '</span>';
        }
        $html .= '</div>';
        
        // Action Buttons
        $html .= '<div class="status-actions">';
        if (!$is_checked) {
            $button_text = $is_queued ? __('Check Now (Skip Queue)', 'wp-image-guardian') : __('Check Image', 'wp-image-guardian');
            $button_class = $is_queued ? 'button button-secondary' : 'button button-primary';
            $html .= '<button type="button" class="' . $button_class . ' wp-image-guardian-check-image" data-attachment-id="' . $attachment_id . '">';
            $html .= $button_text;
            $html .= '</button>';
        } else {
            $view_button_classes = 'button button-small view-results';
            $view_button_extra   = '';
            if (!$has_results) {
                $view_button_classes .= ' disabled';
                $view_button_extra = ' disabled="disabled" aria-disabled="true" title="' . esc_attr__('No TinyEye results available', 'wp-image-guardian') . '"';
            }
            $html .= '<button type="button" class="' . $view_button_classes . '" data-attachment-id="' . $attachment_id . '"' . $view_button_extra . '>';
            $html .= __('View Results', 'wp-image-guardian');
            $html .= '</button>';
            
            $html .= '<button type="button" class="button button-small wp-image-guardian-check-image" data-attachment-id="' . $attachment_id . '">';
            $html .= __('Re-check Image', 'wp-image-guardian');
            $html .= '</button>';
        }
        $html .= '</div>';
        
        $html .= '</div>';
        
        return $html;
    }
    
    private function normalize_risk_level($risk_level) {
        $risk_level = strtolower($risk_level);
        $map = [
            'safe' => 'low',
            'warning' => 'medium',
            'danger' => 'high',
        ];
        return $map[$risk_level] ?? ($risk_level ?: 'unknown');
    }
    
    private function get_risk_level_display($risk_level) {
        switch ($risk_level) {
            case 'high':
                return __('High', 'wp-image-guardian');
            case 'medium':
                return __('Medium', 'wp-image-guardian');
            case 'low':
                return __('Low', 'wp-image-guardian');
            case 'safe':
                return __('Low', 'wp-image-guardian'); // Map safe to low
            case 'unknown':
                return __('Inconclusive', 'wp-image-guardian');
            default:
                return __('Inconclusive', 'wp-image-guardian');
        }
    }
    
    private function get_results_html($attachment_id, $check_status) {
        // Safely decode results_data
        $results_data = [];
        if (!empty($check_status->results_data)) {
            if (is_string($check_status->results_data)) {
                $results_data = json_decode($check_status->results_data, true) ?: [];
            } elseif (is_array($check_status->results_data)) {
                $results_data = $check_status->results_data;
            }
        }
        
        // Fallback to post meta if database doesn't have it
        if (empty($results_data)) {
            $meta_report = get_post_meta($attachment_id, '_wp_image_guardian_report', true);
            if (is_array($meta_report)) {
                $results_data = $meta_report;
            } elseif (is_string($meta_report)) {
                $results_data = json_decode($meta_report, true) ?: [];
            }
        }
        
        $total_results = $results_data['total_results'] ?? ($check_status->results_count ?? 0);
        
        // Apply filter to allow modification of results display
        $display_html = apply_filters('wp_image_guardian_results_display', '', $attachment_id, $check_status, $results_data);
        
        if (!empty($display_html)) {
            return $display_html;
        }
        
        $html = '<div class="wp-image-guardian-results">';
        $html .= '<p><strong>' . sprintf(__('Found %d similar images', 'wp-image-guardian'), $total_results) . '</strong></p>';
        
        if ($total_results > 0) {
            $html .= '<button type="button" class="button view-detailed-results" data-attachment-id="' . $attachment_id . '">';
            $html .= __('View Detailed Results', 'wp-image-guardian');
            $html .= '</button>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    private function get_status_class($risk_level, $user_decision) {
        if ($user_decision) {
            return $user_decision === 'safe' ? 'status-safe' : 'status-unsafe';
        }
        
        switch ($risk_level) {
            case 'safe':
                return 'status-safe';
            case 'warning':
                return 'status-warning';
            case 'danger':
                return 'status-danger';
            default:
                return 'status-unknown';
        }
    }
    
    private function get_status_text($risk_level, $user_decision) {
        if ($user_decision) {
            return $user_decision === 'safe' ? 
                __('Marked as Safe', 'wp-image-guardian') : 
                __('Marked as Unsafe', 'wp-image-guardian');
        }
        
        switch ($risk_level) {
            case 'safe':
                return __('Safe - No matches found', 'wp-image-guardian');
            case 'warning':
                return __('Warning - Few matches found', 'wp-image-guardian');
            case 'danger':
                return __('Danger - Many matches found', 'wp-image-guardian');
            default:
                return __('Not checked', 'wp-image-guardian');
        }
    }
    
    private function get_status_icon($risk_level, $user_decision) {
        if ($user_decision) {
            return $user_decision === 'safe' ? '✅' : '❌';
        }
        
        switch ($risk_level) {
            case 'safe':
                return '🟢';
            case 'warning':
                return '🟡';
            case 'danger':
                return '🔴';
            default:
                return '⚪';
        }
    }
    
    public function add_media_modal() {
        $screen = get_current_screen();
        if ($screen && $screen->id === 'attachment') {
            include WP_IMAGE_GUARDIAN_PLUGIN_DIR . 'templates/media-modal.php';
        }
    }
    
    public function get_modal_content() {
        // Debug: Log AJAX request start
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[WP Image Guardian - get_modal_content] AJAX request started');
        }
        
        // Verify security
        $error = WP_Image_Guardian_Helpers::verify_ajax_request();
        if ($error) {
            if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('[WP Image Guardian - get_modal_content] Security check failed: ' . $error['message']);
            }
            wp_send_json_error($error['message']);
            return;
        }
        
        // Validate attachment ID
        $validation = WP_Image_Guardian_Helpers::validate_attachment_id();
        if (!$validation['success']) {
            if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('[WP Image Guardian - get_modal_content] Validation failed: ' . $validation['message']);
            }
            wp_send_json_error($validation['message']);
            return;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[WP Image Guardian - get_modal_content] Fetching results for attachment: ' . $validation['attachment_id']);
        }
        
        $results = $this->database->get_image_results($validation['attachment_id']);
        
        // If database record exists but results_data is empty/wrong, try to get it from the database directly
        if ($results && (empty($results->results_data) || !is_array($results->results_data))) {
            global $wpdb;
            $db_results = $wpdb->get_var($wpdb->prepare(
                "SELECT results_data FROM {$wpdb->prefix}image_guardian_checks WHERE attachment_id = %d",
                $validation['attachment_id']
            ));
            if ($db_results) {
                $decoded = json_decode($db_results, true);
                if (is_array($decoded)) {
                    $results->results_data = $decoded;
                    if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                        error_log('[WP Image Guardian - get_modal_content] Retrieved results_data from database directly');
                    }
                }
            }
        }
        
        // Fallback: If no database record, try to construct from post meta
        if (!$results) {
            if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('[WP Image Guardian - get_modal_content] No database record found, trying post meta fallback for attachment: ' . $validation['attachment_id']);
            }
            
            // Try to get data from post meta
            $checked = get_post_meta($validation['attachment_id'], '_wp_image_guardian_checked', true);
            if ($checked) {
                // Get the full report from post meta
                $meta_report = get_post_meta($validation['attachment_id'], '_wp_image_guardian_report', true);
                
                // Debug: Log what we got from post meta
                if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                    error_log('[WP Image Guardian - get_modal_content] Raw post meta report type: ' . gettype($meta_report));
                    if (is_array($meta_report)) {
                        error_log('[WP Image Guardian - get_modal_content] Post meta report keys: ' . implode(', ', array_keys($meta_report)));
                    }
                }
                
                // Ensure it's an array
                if (is_string($meta_report)) {
                    $meta_report = json_decode($meta_report, true);
                }
                if (!is_array($meta_report)) {
                    $meta_report = [];
                }
                
                // Check if this is the wrong data (has reason/message but no matches/results/raw_response)
                // This happens when mark_image_reviewed was called or wrong data was stored
                if (isset($meta_report['reason']) && isset($meta_report['message']) && 
                    empty($meta_report['matches']) && empty($meta_report['results']) && empty($meta_report['raw_response'])) {
                    // This is wrong data - try to get from database instead
                    global $wpdb;
                    $db_results = $wpdb->get_var($wpdb->prepare(
                        "SELECT results_data FROM {$wpdb->prefix}image_guardian_checks WHERE attachment_id = %d",
                        $validation['attachment_id']
                    ));
                    if ($db_results) {
                        $decoded = json_decode($db_results, true);
                        if (is_array($decoded) && (!isset($decoded['reason']) || !isset($decoded['message']))) {
                            $meta_report = $decoded;
                            if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                                error_log('[WP Image Guardian - get_modal_content] Post meta had wrong data, using database results_data instead');
                            }
                        }
                    }
                }
                
                // Construct a results object from post meta
                $results = (object) [
                    'attachment_id' => $validation['attachment_id'],
                    'results_count' => absint(get_post_meta($validation['attachment_id'], '_wp_image_guardian_total_results', true)),
                    'risk_level' => get_post_meta($validation['attachment_id'], '_wp_image_guardian_risk_level', true) ?: 'unknown',
                    'match_percentage' => get_post_meta($validation['attachment_id'], '_wp_image_guardian_match_percentage', true),
                    'user_decision' => get_post_meta($validation['attachment_id'], '_wp_image_guardian_user_decision', true),
                    'results_data' => $meta_report, // Use the processed array
                ];
                
                if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                    error_log('[WP Image Guardian - get_modal_content] Constructed results from post meta: ' . print_r([
                        'results_count' => $results->results_count,
                        'risk_level' => $results->risk_level,
                        'has_results_data' => !empty($results->results_data),
                        'meta_report_keys' => is_array($meta_report) ? array_keys($meta_report) : 'not array',
                        'has_matches' => !empty($meta_report['matches']),
                        'has_results' => !empty($meta_report['results']),
                        'has_raw_response' => !empty($meta_report['raw_response']),
                    ], true));
                }
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                    error_log('[WP Image Guardian - get_modal_content] No post meta found either for attachment: ' . $validation['attachment_id']);
                }
                wp_send_json_error(__('No results found', 'wp-image-guardian'));
                return;
            }
        }
        
        // Debug: Log what we got from database
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[WP Image Guardian - get_modal_content] Database record found: ' . print_r([
                'attachment_id' => $results->attachment_id ?? 'N/A',
                'results_count' => $results->results_count ?? 'N/A',
                'risk_level' => $results->risk_level ?? 'N/A',
                'has_results_data' => !empty($results->results_data),
                'results_data_type' => gettype($results->results_data),
                'results_data_length' => is_string($results->results_data) ? strlen($results->results_data) : 'N/A',
            ], true));
        }
        
        // Ensure results_data is an array - handle null/empty cases
        $results_data = [];
        if (!empty($results->results_data)) {
            if (is_string($results->results_data)) {
                $results_data = json_decode($results->results_data, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                        error_log('[WP Image Guardian - get_modal_content] JSON decode error: ' . json_last_error_msg());
                    }
                    $results_data = [];
                }
            } elseif (is_array($results->results_data)) {
                $results_data = $results->results_data;
            }
        }
        
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            $matches_for_count = $results_data['matches'] ?? null;
            $matches_count = (is_array($matches_for_count) && !empty($matches_for_count)) ? count($matches_for_count) : 0;
            
            error_log('[WP Image Guardian - get_modal_content] Parsed results_data: ' . print_r([
                'results_data_type' => gettype($results_data),
                'is_array' => is_array($results_data),
                'has_matches' => !empty($results_data['matches']),
                'has_results_key' => !empty($results_data['results']),
                'has_raw_response' => !empty($results_data['raw_response']),
                'total_results' => $results_data['total_results'] ?? 'not set',
                'matches_count' => $matches_count,
                'raw_response_has_matches' => !empty($results_data['raw_response']['results']['matches'] ?? []),
            ], true));
        }
        
        // If still no data, try getting from post meta as fallback
        if (empty($results_data) || !is_array($results_data)) {
            $results_data = get_post_meta($validation['attachment_id'], '_wp_image_guardian_report', true);
            if (is_string($results_data)) {
                $results_data = json_decode($results_data, true);
            }
        }
        
        // Ensure we have valid data structure
        if (empty($results_data) || !is_array($results_data)) {
            $results_data = [];
        }
        
        // Debug: Log the actual structure of results_data to see what we have
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            $top_keys = is_array($results_data) ? array_keys($results_data) : [];
            error_log('[WP Image Guardian - get_modal_content] results_data structure: ' . print_r([
                'top_level_keys' => $top_keys,
                'has_matches' => !empty($results_data['matches']),
                'has_results' => !empty($results_data['results']),
                'has_raw_response' => !empty($results_data['raw_response']),
                'raw_response_keys' => !empty($results_data['raw_response']) && is_array($results_data['raw_response']) ? array_keys($results_data['raw_response']) : 'no raw_response',
                'raw_response_has_results' => !empty($results_data['raw_response']['results']),
                'raw_response_results_keys' => !empty($results_data['raw_response']['results']) && is_array($results_data['raw_response']['results']) ? array_keys($results_data['raw_response']['results']) : 'no results',
                'raw_response_results_has_matches' => !empty($results_data['raw_response']['results']['matches']),
                'matches_count_in_raw' => !empty($results_data['raw_response']['results']['matches']) && is_array($results_data['raw_response']['results']['matches']) ? count($results_data['raw_response']['results']['matches']) : 0,
            ], true));
        }
        
        // Make sure we have matches in the expected format - check all possible locations
        if (empty($results_data['matches']) && empty($results_data['results'])) {
            // Try to extract from raw_response
            if (!empty($results_data['raw_response']['results']['matches']) && is_array($results_data['raw_response']['results']['matches'])) {
                $results_data['matches'] = $results_data['raw_response']['results']['matches'];
                $results_data['results'] = $results_data['raw_response']['results']['matches'];
            }
        }
        
        // If we have results_count > 0 but no matches, try harder to find them
        if ($results->results_count > 0 && empty($results_data['matches']) && empty($results_data['results'])) {
            // Try post meta as last resort - get fresh copy
            $meta_report = get_post_meta($validation['attachment_id'], '_wp_image_guardian_report', true);
            if (is_string($meta_report)) {
                $meta_report = json_decode($meta_report, true);
            }
            
            if (is_array($meta_report)) {
                // Debug what we found in meta_report
                if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                    error_log('[WP Image Guardian - get_modal_content] meta_report structure: ' . print_r([
                        'top_level_keys' => array_keys($meta_report),
                        'has_matches' => !empty($meta_report['matches']),
                        'has_results' => !empty($meta_report['results']),
                        'has_raw_response' => !empty($meta_report['raw_response']),
                        'raw_response_keys' => !empty($meta_report['raw_response']) && is_array($meta_report['raw_response']) ? array_keys($meta_report['raw_response']) : 'no raw_response',
                    ], true));
                }
                
                if (!empty($meta_report['matches']) && is_array($meta_report['matches'])) {
                    $results_data['matches'] = $meta_report['matches'];
                    $results_data['results'] = $meta_report['matches'];
                } elseif (!empty($meta_report['results']) && is_array($meta_report['results'])) {
                    $results_data['matches'] = $meta_report['results'];
                    $results_data['results'] = $meta_report['results'];
                } elseif (!empty($meta_report['raw_response']['results']['matches']) && is_array($meta_report['raw_response']['results']['matches'])) {
                    $results_data['matches'] = $meta_report['raw_response']['results']['matches'];
                    $results_data['results'] = $meta_report['raw_response']['results']['matches'];
                }
            }
        }
        
        // Ensure total_results is set
        if (empty($results_data['total_results'])) {
            if (!empty($results_data['raw_response']['stats']['total_filtered_results'])) {
                $results_data['total_results'] = absint($results_data['raw_response']['stats']['total_filtered_results']);
            } elseif (!empty($results_data['matches']) && is_array($results_data['matches'])) {
                $results_data['total_results'] = count($results_data['matches']);
            } elseif (!empty($results_data['results']) && is_array($results_data['results'])) {
                $results_data['total_results'] = count($results_data['results']);
            } elseif ($results->results_count > 0) {
                $results_data['total_results'] = $results->results_count;
            } else {
                $results_data['total_results'] = 0;
            }
        }
        
        // Ensure matches and results keys are set for template
        if (empty($results_data['matches']) && !empty($results_data['results']) && is_array($results_data['results'])) {
            $results_data['matches'] = $results_data['results'];
        }
        if (empty($results_data['results']) && !empty($results_data['matches']) && is_array($results_data['matches'])) {
            $results_data['results'] = $results_data['matches'];
        }
        
        // Update the results object with processed data
        $results->results_data = $results_data;
        
        // Debug: Log what we found
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[WP Image Guardian - get_modal_content] Final data: ' . print_r([
                'results_count_db' => $results->results_count,
                'total_results' => $results_data['total_results'] ?? 0,
                'matches_found' => !empty($results_data['matches']) ? count($results_data['matches']) : 0,
                'has_raw_response' => !empty($results_data['raw_response']),
                'matches_array_keys' => !empty($results_data['matches']) ? array_keys($results_data['matches'][0] ?? []) : 'no matches',
            ], true));
        }
        
        ob_start();
        include WP_IMAGE_GUARDIAN_PLUGIN_DIR . 'templates/modal-content.php';
        $content = ob_get_clean();
        
        // Debug: Log the generated content
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[WP Image Guardian - get_modal_content] Generated content length: ' . strlen($content));
            error_log('[WP Image Guardian - get_modal_content] Content preview (first 500 chars): ' . substr($content, 0, 500));
        }
        
        wp_send_json_success(['content' => $content]);
    }
    
    public function get_attachment_status($attachment_id) {
        return $this->database->get_image_status($attachment_id);
    }
    
    public function get_attachment_results($attachment_id) {
        return $this->database->get_image_results($attachment_id);
    }
    
    public function check_image($attachment_id) {
        $image_url = wp_get_attachment_url($attachment_id);
        
        if (!$image_url) {
            return [
                'success' => false,
                'message' => __('Invalid image URL', 'wp-image-guardian')
            ];
        }
        
        $result = $this->api->check_image($image_url);
        
        if ($result['success']) {
            $this->database->store_image_check($attachment_id, $result['data']);
            return $result;
        }
        
        return $result;
    }
    
    public function mark_safe($attachment_id) {
        return $this->database->mark_image_safe($attachment_id);
    }
    
    public function mark_unsafe($attachment_id) {
        return $this->database->mark_image_unsafe($attachment_id);
    }
    
    /**
     * Add filters to media library
     */
    public function add_media_library_filters() {
        global $typenow;
        
        if ($typenow !== 'attachment') {
            return;
        }
        
        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'upload') {
            return;
        }
        
        // Risk Level filter
        $risk_level = isset($_GET['risk_level']) ? sanitize_text_field($_GET['risk_level']) : '';
        ?>
        <select name="risk_level" id="risk_level_filter">
            <option value=""><?php _e('All Risk Levels', 'wp-image-guardian'); ?></option>
            <option value="high" <?php selected($risk_level, 'high'); ?>><?php _e('High Risk', 'wp-image-guardian'); ?></option>
            <option value="medium" <?php selected($risk_level, 'medium'); ?>><?php _e('Medium Risk', 'wp-image-guardian'); ?></option>
            <option value="low" <?php selected($risk_level, 'low'); ?>><?php _e('Low Risk', 'wp-image-guardian'); ?></option>
            <option value="unknown" <?php selected($risk_level, 'unknown'); ?>><?php _e('Not Checked', 'wp-image-guardian'); ?></option>
        </select>
        
        <?php
        // Safe/Unsafe filter (user decision)
        $user_decision = isset($_GET['user_decision']) ? sanitize_text_field($_GET['user_decision']) : '';
        ?>
        <select name="user_decision" id="user_decision_filter">
            <option value=""><?php _e('All Decisions', 'wp-image-guardian'); ?></option>
            <option value="safe" <?php selected($user_decision, 'safe'); ?>><?php _e('Safe', 'wp-image-guardian'); ?></option>
            <option value="unsafe" <?php selected($user_decision, 'unsafe'); ?>><?php _e('Unsafe', 'wp-image-guardian'); ?></option>
        </select>
        
        <?php
        // Reviewed filter
        $reviewed = isset($_GET['reviewed']) ? sanitize_text_field($_GET['reviewed']) : '';
        ?>
        <select name="reviewed" id="reviewed_filter">
            <option value=""><?php _e('All Review Status', 'wp-image-guardian'); ?></option>
            <option value="yes" <?php selected($reviewed, 'yes'); ?>><?php _e('Reviewed', 'wp-image-guardian'); ?></option>
            <option value="no" <?php selected($reviewed, 'no'); ?>><?php _e('Not Reviewed', 'wp-image-guardian'); ?></option>
        </select>
        
        <?php
        // Checked filter
        $checked = isset($_GET['checked']) ? sanitize_text_field($_GET['checked']) : '';
        ?>
        <select name="checked" id="checked_filter">
            <option value=""><?php _e('All Check Status', 'wp-image-guardian'); ?></option>
            <option value="yes" <?php selected($checked, 'yes'); ?>><?php _e('Checked', 'wp-image-guardian'); ?></option>
            <option value="no" <?php selected($checked, 'no'); ?>><?php _e('Not Checked', 'wp-image-guardian'); ?></option>
        </select>
        <?php
    }
    
    /**
     * Filter media library query based on selected filters
     */
    public function filter_media_library_query($query) {
        global $pagenow, $wpdb;
        
        if ($pagenow !== 'upload.php' || !$query->is_main_query()) {
            return;
        }
        
        if (!isset($_GET['risk_level']) && !isset($_GET['reviewed']) && !isset($_GET['checked']) && !isset($_GET['user_decision'])) {
            return;
        }
        
        $risk_level = isset($_GET['risk_level']) ? sanitize_text_field($_GET['risk_level']) : '';
        $reviewed = isset($_GET['reviewed']) ? sanitize_text_field($_GET['reviewed']) : '';
        $checked = isset($_GET['checked']) ? sanitize_text_field($_GET['checked']) : '';
        $user_decision = isset($_GET['user_decision']) ? sanitize_text_field($_GET['user_decision']) : '';
        
        // Map old risk levels to new ones for filtering
        $risk_level_map = [
            'safe' => 'low',
            'warning' => 'medium',
            'danger' => 'high',
        ];
        
        $table_name = $wpdb->prefix . 'image_guardian_checks';
        
        // Build WHERE clause
        $where_clauses = [];
        
        if (!empty($risk_level)) {
            // Handle both new and old risk level values
            $risk_levels = [$risk_level];
            if (isset($risk_level_map[$risk_level])) {
                $risk_levels[] = $risk_level_map[$risk_level];
            }
            // Also check reverse mapping
            foreach ($risk_level_map as $old => $new) {
                if ($new === $risk_level) {
                    $risk_levels[] = $old;
                }
            }
            $risk_levels = array_unique($risk_levels);
            $placeholders = implode(',', array_fill(0, count($risk_levels), '%s'));
            $where_clauses[] = $wpdb->prepare("ig.risk_level IN ($placeholders)", ...$risk_levels);
        }
        
        if (!empty($reviewed)) {
            if ($reviewed === 'yes') {
                $where_clauses[] = "ig.user_decision IS NOT NULL";
            } else {
                $where_clauses[] = "(ig.user_decision IS NULL OR ig.user_decision = '')";
            }
        }
        
        if (!empty($checked)) {
            if ($checked === 'yes') {
                $where_clauses[] = "ig.status = 'completed'";
            } else {
                $where_clauses[] = "ig.id IS NULL";
            }
        }
        
        if (!empty($user_decision)) {
            if ($user_decision === 'safe') {
                $where_clauses[] = "ig.user_decision = 'safe'";
            } elseif ($user_decision === 'unsafe') {
                $where_clauses[] = "ig.user_decision = 'unsafe'";
            }
        }
        
        if (empty($where_clauses)) {
            return;
        }
        
        // Modify query to join with image_guardian_checks table
        $where_sql = implode(' AND ', $where_clauses);
        
        // Add JOIN and WHERE
        $query->set('meta_query', []); // Clear any existing meta_query
        
        // Use posts_where filter to add our custom WHERE clause
        add_filter('posts_join', function($join) use ($table_name, $wpdb) {
            if (strpos($join, $table_name) === false) {
                $join .= " LEFT JOIN {$table_name} ig ON {$wpdb->posts}.ID = ig.attachment_id";
            }
            return $join;
        });
        
        add_filter('posts_where', function($where) use ($where_sql) {
            if (!empty($where_sql)) {
                $where .= " AND ({$where_sql})";
            }
            return $where;
        });
    }
    
    /**
     * AJAX handler for setting manual risk level
     */
    public function ajax_set_manual_risk() {
        // Verify security
        $error = WP_Image_Guardian_Helpers::verify_ajax_request();
        if ($error) {
            wp_send_json_error($error['message']);
            return;
        }
        
        // Validate attachment ID
        $validation = WP_Image_Guardian_Helpers::validate_attachment_id();
        if (!$validation['success']) {
            wp_send_json_error($validation['message']);
            return;
        }
        
        // Validate risk level
        $risk_level = isset($_POST['risk_level']) ? sanitize_text_field($_POST['risk_level']) : '';
        if (!WP_Image_Guardian_Helpers::is_valid_risk_level($risk_level, true)) {
            wp_send_json_error(__('Invalid risk level', 'wp-image-guardian'));
            return;
        }
        
        // Update manual risk level
        global $wpdb;
        $table_name = $wpdb->prefix . 'image_guardian_checks';
        
        // Check if record exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$table_name} WHERE attachment_id = %d",
            $validation['attachment_id']
        ));
        
        if ($existing) {
            // Update existing record
            $updated = $wpdb->update(
                $table_name,
                ['manual_risk_level' => $risk_level ?: null, 'updated_at' => current_time('mysql')],
                ['attachment_id' => $validation['attachment_id']],
                ['%s', '%s'],
                ['%d']
            );
            
            // Update post meta
            if ($risk_level) {
                update_post_meta($validation['attachment_id'], '_wp_image_guardian_manual_risk_level', $risk_level);
                update_post_meta($validation['attachment_id'], '_wp_image_guardian_risk_level', $risk_level);
            } else {
                delete_post_meta($validation['attachment_id'], '_wp_image_guardian_manual_risk_level');
            }
            
            if ($updated !== false) {
                wp_send_json_success(__('Risk level updated', 'wp-image-guardian'));
            } else {
                wp_send_json_error(__('Failed to update risk level', 'wp-image-guardian'));
            }
        } else {
            // Create new record with manual risk level
            $url_result = WP_Image_Guardian_Helpers::get_attachment_image_url($validation['attachment_id']);
            $image_url = $url_result['success'] ? $url_result['url'] : '';
            
            $inserted = $wpdb->insert(
                $table_name,
                [
                    'attachment_id' => $validation['attachment_id'],
                    'image_url' => $image_url,
                    'status' => 'pending',
                    'risk_level' => 'unknown',
                    'manual_risk_level' => $risk_level ?: null,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                ],
                ['%d', '%s', '%s', '%s', '%s', '%s', '%s']
            );
            
            // Update post meta
            if ($risk_level) {
                update_post_meta($validation['attachment_id'], '_wp_image_guardian_manual_risk_level', $risk_level);
                update_post_meta($validation['attachment_id'], '_wp_image_guardian_risk_level', $risk_level);
            }
            
            if ($inserted !== false) {
                wp_send_json_success(__('Risk level set', 'wp-image-guardian'));
            } else {
                wp_send_json_error(__('Failed to set risk level', 'wp-image-guardian'));
            }
        }
    }
    
    /**
     * AJAX handler for toggling image safe status
     */
    public function ajax_toggle_image_safe() {
        // Verify security
        $error = WP_Image_Guardian_Helpers::verify_ajax_request();
        if ($error) {
            wp_send_json_error($error['message']);
            return;
        }
        
        // Validate attachment ID
        $validation = WP_Image_Guardian_Helpers::validate_attachment_id();
        if (!$validation['success']) {
            wp_send_json_error($validation['message']);
            return;
        }
        
        // Get safe status
        $is_safe = isset($_POST['is_safe']) && ($_POST['is_safe'] === 'true' || $_POST['is_safe'] === '1' || $_POST['is_safe'] === true);
        
        if ($is_safe) {
            $this->database->mark_image_safe($validation['attachment_id']);
            wp_send_json_success(__('Image marked as safe', 'wp-image-guardian'));
        } else {
            $this->database->mark_image_unsafe($validation['attachment_id']);
            wp_send_json_success(__('Image marked as unsafe', 'wp-image-guardian'));
        }
    }
    
    /**
     * Add custom columns to media library
     */
    public function add_media_columns($columns) {
        $columns['image_guardian_risk'] = __('Risk Level', 'wp-image-guardian');
        $columns['image_guardian_safe'] = __('Image Safe', 'wp-image-guardian');
        return $columns;
    }
    
    /**
     * Render column content
     */
    public function render_media_columns($column_name, $attachment_id) {
        if ($column_name === 'image_guardian_risk') {
            $risk_level = get_post_meta($attachment_id, '_wp_image_guardian_manual_risk_level', true);
            if (!$risk_level) {
                $risk_level = get_post_meta($attachment_id, '_wp_image_guardian_risk_level', true);
            }
            $risk_level = $risk_level ?: 'unknown';
            $normalized = $this->normalize_risk_level($risk_level);
            $label = $this->get_risk_level_display($normalized);
            
            echo '<span class="wp-image-guardian-risk-badge risk-' . esc_attr($normalized) . '">' . esc_html($label) . '</span>';
        } elseif ($column_name === 'image_guardian_safe') {
            $checked = get_post_meta($attachment_id, '_wp_image_guardian_checked', true);
            $decision = get_post_meta($attachment_id, '_wp_image_guardian_user_decision', true);
            if (!$checked) {
                echo '<span class="wp-image-guardian-safe-status unknown">' . __('Not Checked', 'wp-image-guardian') . '</span>';
            } elseif ($decision === 'safe') {
                echo '<span class="wp-image-guardian-safe-status safe">' . __('Safe', 'wp-image-guardian') . '</span>';
            } elseif ($decision === 'unsafe') {
                echo '<span class="wp-image-guardian-safe-status unsafe">' . __('Unsafe', 'wp-image-guardian') . '</span>';
            } else {
                echo '<span class="wp-image-guardian-safe-status pending">' . __('Pending Review', 'wp-image-guardian') . '</span>';
            }
        }
    }
    
    /**
     * Add quick action links
     */
    public function add_media_row_actions($actions, $post) {
        if ($post->post_type === 'attachment' && wp_attachment_is_image($post->ID)) {
            $actions['wp_image_guardian_check'] = '<a href="#" class="wp-image-guardian-check-image" data-attachment-id="' . $post->ID . '">' . esc_html__('Check with Image Guardian', 'wp-image-guardian') . '</a>';
        }
        return $actions;
    }
}
