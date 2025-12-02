<?php

if (!defined('ABSPATH')) {
    exit;
}

class WP_Image_Guardian_Database {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'image_guardian_checks';
    }
    
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            attachment_id bigint(20) unsigned NOT NULL,
            image_url varchar(500) NOT NULL,
            image_hash varchar(64) DEFAULT NULL,
            search_id varchar(100) DEFAULT NULL,
            status varchar(20) DEFAULT 'pending',
            results_count int(11) DEFAULT 0,
            results_data longtext,
            risk_level varchar(20) DEFAULT 'unknown',
            match_percentage decimal(5,2) DEFAULT NULL,
            user_decision varchar(20) DEFAULT NULL,
            manual_risk_level varchar(20) DEFAULT NULL,
            checked_at datetime DEFAULT CURRENT_TIMESTAMP,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY attachment_id (attachment_id),
            KEY status (status),
            KEY risk_level (risk_level),
            KEY user_decision (user_decision),
            KEY checked_at (checked_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Migrate existing data if needed
        $this->migrate_database();
    }
    
    /**
     * Migrate database schema for existing installations
     */
    private function migrate_database() {
        global $wpdb;
        
        $current_version = get_option('wp_image_guardian_db_version', '0');
        $target_version = '2.0';
        
        if (version_compare($current_version, $target_version, '>=')) {
            return; // Already migrated
        }
        
        // Check if columns exist
        $columns = $wpdb->get_col("DESCRIBE {$this->table_name}");
        
        // Add match_percentage column if it doesn't exist
        if (!in_array('match_percentage', $columns)) {
            $wpdb->query("ALTER TABLE {$this->table_name} ADD COLUMN match_percentage decimal(5,2) DEFAULT NULL");
        }
        
        // Add manual_risk_level column if it doesn't exist
        if (!in_array('manual_risk_level', $columns)) {
            $wpdb->query("ALTER TABLE {$this->table_name} ADD COLUMN manual_risk_level varchar(20) DEFAULT NULL");
        }
        
        // Add index on user_decision if it doesn't exist
        $indexes = $wpdb->get_results("SHOW INDEX FROM {$this->table_name} WHERE Key_name = 'user_decision'");
        if (empty($indexes)) {
            $wpdb->query("ALTER TABLE {$this->table_name} ADD INDEX user_decision (user_decision)");
        }
        
        // Update version
        update_option('wp_image_guardian_db_version', $target_version);
    }
    
    public function drop_tables() {
        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS {$this->table_name}");
    }
    
    public function store_image_check($attachment_id, $results) {
        global $wpdb;
        
        // Validate attachment ID
        $attachment_id = absint($attachment_id);
        if ($attachment_id <= 0) {
            return false;
        }
        
        // Validate results is an array
        if (!is_array($results)) {
            return false;
        }
        
        // Normalize TinyEye response structure
        if (empty($results['results']) && !empty($results['matches']) && is_array($results['matches'])) {
            $results['results'] = $results['matches'];
        }
        if (empty($results['results']) && !empty($results['raw_response']['results']['matches'])) {
            $results['results'] = $results['raw_response']['results']['matches'];
        }
        if (!isset($results['total_results'])) {
            if (isset($results['raw_response']['stats']['total_filtered_results'])) {
                $results['total_results'] = absint($results['raw_response']['stats']['total_filtered_results']);
            } elseif (isset($results['results']) && is_array($results['results'])) {
                $results['total_results'] = count($results['results']);
            } else {
                $results['total_results'] = 0;
            }
        }
        
        $image_url = wp_get_attachment_url($attachment_id);
        if (!$image_url) {
            return false;
        }
        
        // Sanitize image URL
        $image_url = esc_url_raw($image_url);
        $image_hash = $this->generate_image_hash($image_url);
        
        $risk_level = $this->calculate_risk_level($results);
        
        // Validate risk level
        if (!WP_Image_Guardian_Helpers::is_valid_risk_level($risk_level)) {
            $risk_level = 'unknown';
        }
        
        // Get match percentage
        $match_percentage = $results['match_percentage'] ?? null;
        if ($match_percentage !== null) {
            $match_percentage = floatval($match_percentage);
            // Clamp between 0 and 100
            $match_percentage = max(0, min(100, $match_percentage));
        }
        
        // Sanitize search_id
        $search_id = isset($results['search_id']) ? sanitize_text_field($results['search_id']) : null;
        if ($search_id && strlen($search_id) > 100) {
            $search_id = substr($search_id, 0, 100);
        }
        
        // Validate and sanitize results_count
        $results_count = isset($results['total_results']) ? absint($results['total_results']) : 0;
        
        // Sanitize JSON data
        $results_data = wp_json_encode($results, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($results_data === false) {
            $results_data = '{}';
        }
        
        // Auto-mark as safe if risk level is low (unless user has already made a decision)
        $existing_record = $wpdb->get_row($wpdb->prepare(
            "SELECT user_decision FROM {$this->table_name} WHERE attachment_id = %d",
            $attachment_id
        ));
        
        $user_decision = null;
        if ($existing_record && $existing_record->user_decision) {
            // Preserve existing user decision
            $user_decision = $existing_record->user_decision;
        } elseif ($risk_level === 'low' || $risk_level === 'safe') {
            // Auto-mark as safe for low risk images
            $user_decision = 'safe';
        }
        
        $data = [
            'attachment_id' => $attachment_id,
            'image_url' => $image_url,
            'image_hash' => $image_hash,
            'search_id' => $search_id,
            'status' => 'completed',
            'results_count' => $results_count,
            'results_data' => $results_data,
            'risk_level' => $risk_level,
            'match_percentage' => $match_percentage,
            'user_decision' => $user_decision,
            'checked_at' => current_time('mysql'),
        ];
        
        // Store as post meta for easy access
        // Full report (raw response from TinyEye)
        // Debug: Log what we're storing
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[WP Image Guardian - store_image_check] Storing report to post meta: ' . print_r([
                'results_keys' => is_array($results) ? array_keys($results) : 'not array',
                'has_matches' => !empty($results['matches']),
                'has_results' => !empty($results['results']),
                'has_raw_response' => !empty($results['raw_response']),
                'total_results' => $results['total_results'] ?? 'not set',
            ], true));
        }
        update_post_meta($attachment_id, '_wp_image_guardian_report', $results);
        
        // Check status
        update_post_meta($attachment_id, '_wp_image_guardian_checked', true);
        update_post_meta($attachment_id, '_wp_image_guardian_checked_at', current_time('mysql'));
        
        // Risk level and score
        update_post_meta($attachment_id, '_wp_image_guardian_risk_level', $risk_level);
        update_post_meta($attachment_id, '_wp_image_guardian_match_percentage', $match_percentage);
        update_post_meta($attachment_id, '_wp_image_guardian_total_results', $results_count);
        
        // User decision (safe/unsafe/null)
        if ($user_decision) {
            update_post_meta($attachment_id, '_wp_image_guardian_user_decision', $user_decision);
        }
        
        // Store search ID if available
        if ($search_id) {
            update_post_meta($attachment_id, '_wp_image_guardian_search_id', $search_id);
        }
        
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[WP Image Guardian - store_image_check] Storing check for attachment: ' . $attachment_id);
            error_log('[WP Image Guardian - store_image_check] Data to store: ' . print_r([
                'attachment_id' => $data['attachment_id'],
                'results_count' => $data['results_count'],
                'risk_level' => $data['risk_level'],
                'results_data_length' => strlen($data['results_data']),
            ], true));
        }
        
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$this->table_name} WHERE attachment_id = %d",
            $attachment_id
        ));
        
        if ($existing) {
            $data['updated_at'] = current_time('mysql');
            // Format: attachment_id, image_url, image_hash, search_id, status, results_count, results_data, risk_level, match_percentage, user_decision, checked_at, updated_at
            $updated = $wpdb->update(
                $this->table_name,
                $data,
                ['attachment_id' => $attachment_id],
                ['%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%f', '%s', '%s', '%s'],
                ['%d']
            );
            
            if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('[WP Image Guardian - store_image_check] Update result: ' . ($updated !== false ? 'success (rows: ' . $updated . ')' : 'failed - ' . $wpdb->last_error));
            }
            
            return $updated !== false ? $existing->id : false;
        } else {
            $data['created_at'] = current_time('mysql');
            $data['updated_at'] = current_time('mysql');
            // Format: attachment_id, image_url, image_hash, search_id, status, results_count, results_data, risk_level, match_percentage, user_decision, checked_at, created_at, updated_at
            $inserted = $wpdb->insert($this->table_name, $data, ['%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s']);
            
            if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('[WP Image Guardian - store_image_check] Insert result: ' . ($inserted !== false ? 'success (ID: ' . $wpdb->insert_id . ')' : 'failed - ' . $wpdb->last_error));
                if ($inserted === false) {
                    error_log('[WP Image Guardian - store_image_check] Last query: ' . $wpdb->last_query);
                }
            }
            
            return $inserted !== false ? $wpdb->insert_id : false;
        }
    }
    
    public function get_image_results($attachment_id) {
        global $wpdb;
        
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE attachment_id = %d",
            $attachment_id
        ));
        
        if ($result) {
            $result->results_data = json_decode($result->results_data, true);
        }
        
        return $result;
    }
    
    public function get_image_status($attachment_id) {
        global $wpdb;
        
        // Try database first
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT status, risk_level, user_decision, checked_at FROM {$this->table_name} WHERE attachment_id = %d",
            $attachment_id
        ));
        
        // If not in database, try post meta as fallback
        if (!$result) {
            $checked = get_post_meta($attachment_id, '_wp_image_guardian_checked', true);
            if ($checked) {
                $result = (object) [
                    'status' => 'completed',
                    'risk_level' => get_post_meta($attachment_id, '_wp_image_guardian_risk_level', true) ?: 'unknown',
                    'user_decision' => get_post_meta($attachment_id, '_wp_image_guardian_user_decision', true) ?: null,
                    'checked_at' => get_post_meta($attachment_id, '_wp_image_guardian_checked_at', true),
                ];
            }
        }
        
        return $result;
    }
    
    public function mark_image_safe($attachment_id) {
        global $wpdb;
        
        // Validate attachment ID
        $attachment_id = absint($attachment_id);
        if ($attachment_id <= 0) {
            return false;
        }
        
        // Update database
        $updated = $wpdb->update(
            $this->table_name,
            ['user_decision' => 'safe', 'updated_at' => current_time('mysql')],
            ['attachment_id' => $attachment_id],
            ['%s', '%s'],
            ['%d']
        );
        
        // Update post meta for easy access
        update_post_meta($attachment_id, '_wp_image_guardian_user_decision', 'safe');
        
        return $updated !== false;
    }
    
    public function mark_image_unsafe($attachment_id) {
        global $wpdb;
        
        // Validate attachment ID
        $attachment_id = absint($attachment_id);
        if ($attachment_id <= 0) {
            return false;
        }
        
        // Update database
        $updated = $wpdb->update(
            $this->table_name,
            ['user_decision' => 'unsafe', 'updated_at' => current_time('mysql')],
            ['attachment_id' => $attachment_id],
            ['%s', '%s'],
            ['%d']
        );
        
        // Update post meta for easy access
        update_post_meta($attachment_id, '_wp_image_guardian_user_decision', 'unsafe');
        
        return $updated !== false;
    }
    
    /**
     * Mark image as reviewed (unsupported format - skip TinyEye check)
     * This creates a record so the image is excluded from bulk checks
     * 
     * @param int $attachment_id Attachment ID
     * @param string $reason Reason for skipping (e.g., 'unsupported_format')
     * @return bool Success status
     */
    public function mark_image_reviewed($attachment_id, $reason = 'unsupported_format') {
        global $wpdb;
        
        // Validate attachment ID
        $attachment_id = absint($attachment_id);
        if ($attachment_id <= 0) {
            return false;
        }
        
        $image_url = wp_get_attachment_url($attachment_id);
        if (!$image_url) {
            return false;
        }
        
        $image_url = esc_url_raw($image_url);
        $image_hash = $this->generate_image_hash($image_url);
        
        // Check if record exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$this->table_name} WHERE attachment_id = %d",
            $attachment_id
        ));
        
        $data = [
            'attachment_id' => $attachment_id,
            'image_url' => $image_url,
            'image_hash' => $image_hash,
            'status' => 'completed',
            'risk_level' => 'unknown', // Inconclusive since format unsupported
            'user_decision' => null,
            'results_count' => 0,
            'results_data' => wp_json_encode([
                'reason' => $reason,
                'message' => __('Unsupported image format - skipped TinyEye check', 'wp-image-guardian'),
            ]),
            'checked_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ];
        
        // Store post meta so UI reflects checked/inconclusive state
        update_post_meta($attachment_id, '_wp_image_guardian_checked', true);
        update_post_meta($attachment_id, '_wp_image_guardian_checked_at', current_time('mysql'));
        update_post_meta($attachment_id, '_wp_image_guardian_risk_level', 'unknown');
        update_post_meta($attachment_id, '_wp_image_guardian_total_results', 0);
        delete_post_meta($attachment_id, '_wp_image_guardian_user_decision');
        
        if ($existing) {
            // Update existing record
            return $wpdb->update(
                $this->table_name,
                $data,
                ['attachment_id' => $attachment_id],
                ['%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s'],
                ['%d']
            ) !== false;
        } else {
            // Create new record
            $data['created_at'] = current_time('mysql');
            return $wpdb->insert(
                $this->table_name,
                $data,
                ['%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s']
            ) !== false;
        }
    }
    
    public function get_unchecked_images($hours = 24) {
        global $wpdb;
        
        // Validate and sanitize hours parameter
        $hours = absint($hours);
        if ($hours <= 0 || $hours > 8760) { // Max 1 year
            $hours = 24;
        }
        
        $date = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT p.ID, p.post_title, p.post_date 
             FROM {$wpdb->posts} p 
             LEFT JOIN {$this->table_name} ig ON p.ID = ig.attachment_id 
             WHERE p.post_type = 'attachment' 
             AND p.post_mime_type LIKE %s 
             AND p.post_date >= %s 
             AND ig.id IS NULL 
             ORDER BY p.post_date DESC",
            'image/%',
            $date
        ));
    }
    
    public function get_checked_images($limit = 50, $offset = 0) {
        global $wpdb;
        
        // Validate and sanitize limit and offset
        $limit = absint($limit);
        $offset = absint($offset);
        
        // Prevent excessive queries
        if ($limit > 1000) {
            $limit = 1000;
        }
        if ($offset > 10000) {
            $offset = 10000;
        }
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT ig.*, p.post_title, p.post_date 
             FROM {$this->table_name} ig 
             JOIN {$wpdb->posts} p ON ig.attachment_id = p.ID 
             WHERE p.post_type = 'attachment' 
             ORDER BY ig.checked_at DESC 
             LIMIT %d OFFSET %d",
            $limit, $offset
        ));
    }
    
    public function get_risk_stats() {
        global $wpdb;
        
        $stats = $wpdb->get_results(
            "SELECT risk_level, COUNT(*) as count 
             FROM {$this->table_name} 
             WHERE status = 'completed' 
             GROUP BY risk_level"
        );
        
        $result = [
            'safe' => 0,
            'warning' => 0,
            'danger' => 0,
            'unknown' => 0,
            'total' => 0
        ];
        
        foreach ($stats as $stat) {
            $result[$stat->risk_level] = intval($stat->count);
            $result['total'] += intval($stat->count);
        }
        
        return $result;
    }
    
    public function get_recent_checks($limit = 10) {
        global $wpdb;
        
        // Validate and sanitize limit
        $limit = absint($limit);
        if ($limit <= 0 || $limit > 100) {
            $limit = 10;
        }
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT ig.*, p.post_title, p.guid 
             FROM {$this->table_name} ig 
             JOIN {$wpdb->posts} p ON ig.attachment_id = p.ID 
             WHERE p.post_type = 'attachment' 
             ORDER BY ig.checked_at DESC 
             LIMIT %d",
            $limit
        ));
    }
    
    private function generate_image_hash($image_url) {
        return hash('sha256', $image_url);
    }
    
    private function calculate_risk_level($results) {
        // Use match percentage if available
        $match_percentage = $results['match_percentage'] ?? null;
        
        if ($match_percentage !== null) {
            // Use percentage-based risk levels
            if ($match_percentage >= 80) {
                return 'high';
            } elseif ($match_percentage >= 50) {
                return 'medium';
            } elseif ($match_percentage > 0) {
                return 'low';
            } else {
                return 'low'; // 0% means no matches
            }
        }
        
        // Fallback to count-based (for backward compatibility)
        $total_results = $results['total_results'] ?? 0;
        
        $risk_level = 'unknown';
        if ($total_results === 0) {
            $risk_level = 'low'; // Changed from 'safe' to 'low'
        } elseif ($total_results <= 3) {
            $risk_level = 'medium'; // Changed from 'warning' to 'medium'
        } else {
            $risk_level = 'high'; // Changed from 'danger' to 'high'
        }
        
        // Apply filter to allow modification of risk level
        return apply_filters('wp_image_guardian_risk_level', $risk_level, $results, $total_results, $match_percentage);
    }
    
    public function cleanup_old_checks($days = 90) {
        global $wpdb;
        
        $date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        return $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_name} WHERE checked_at < %s",
            $date
        ));
    }
    
    public function get_attachment_checks($attachment_ids) {
        if (empty($attachment_ids) || !is_array($attachment_ids)) {
            return [];
        }
        
        global $wpdb;
        
        // Sanitize all attachment IDs
        $attachment_ids = array_map('absint', $attachment_ids);
        $attachment_ids = array_filter($attachment_ids, function($id) {
            return $id > 0;
        });
        
        if (empty($attachment_ids)) {
            return [];
        }
        
        // Limit to prevent excessive queries
        $attachment_ids = array_slice($attachment_ids, 0, 100);
        
        $placeholders = implode(',', array_fill(0, count($attachment_ids), '%d'));
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE attachment_id IN ($placeholders)",
            ...$attachment_ids
        ));
    }
    
    /**
     * Get total count of all image media
     */
    public function get_total_media_count() {
        global $wpdb;
        
        $count = $wpdb->get_var(
            "SELECT COUNT(*) 
             FROM {$wpdb->posts} 
             WHERE post_type = 'attachment' 
             AND post_mime_type LIKE 'image/%'"
        );
        
        return intval($count);
    }
    
    /**
     * Get count of checked media
     */
    public function get_checked_media_count() {
        global $wpdb;
        
        $count = $wpdb->get_var(
            "SELECT COUNT(DISTINCT attachment_id) 
             FROM {$this->table_name} 
             WHERE status = 'completed'"
        );
        
        return intval($count);
    }
    
    /**
     * Get count of unchecked media
     */
    public function get_unchecked_media_count() {
        global $wpdb;
        
        $count = $wpdb->get_var(
            "SELECT COUNT(DISTINCT p.ID)
             FROM {$wpdb->posts} p
             LEFT JOIN {$this->table_name} ig ON p.ID = ig.attachment_id
             WHERE p.post_type = 'attachment'
             AND p.post_mime_type LIKE 'image/%'
             AND ig.id IS NULL"
        );
        
        return intval($count);
    }
    
    /**
     * Get risk breakdown with reviewed counts
     */
    public function get_risk_breakdown() {
        global $wpdb;
        
        // Map old risk levels to new ones and include safe/unsafe breakdown
        // safe -> low, warning -> medium, danger -> high
        $stats = $wpdb->get_results(
            "SELECT 
                CASE 
                    WHEN risk_level = 'safe' THEN 'low'
                    WHEN risk_level = 'warning' THEN 'medium'
                    WHEN risk_level = 'danger' THEN 'high'
                    ELSE risk_level
                END as risk_level,
                COUNT(*) as total,
                SUM(CASE WHEN user_decision IS NOT NULL THEN 1 ELSE 0 END) as reviewed,
                SUM(CASE WHEN user_decision = 'safe' THEN 1 ELSE 0 END) as safe,
                SUM(CASE WHEN user_decision = 'unsafe' THEN 1 ELSE 0 END) as unsafe
             FROM {$this->table_name} 
             WHERE status = 'completed'
             GROUP BY 
                CASE 
                    WHEN risk_level = 'safe' THEN 'low'
                    WHEN risk_level = 'warning' THEN 'medium'
                    WHEN risk_level = 'danger' THEN 'high'
                    ELSE risk_level
                END"
        );
        
        $result = [
            'high' => ['total' => 0, 'reviewed' => 0, 'safe' => 0, 'unsafe' => 0],
            'medium' => ['total' => 0, 'reviewed' => 0, 'safe' => 0, 'unsafe' => 0],
            'low' => ['total' => 0, 'reviewed' => 0, 'safe' => 0, 'unsafe' => 0],
        ];
        
        foreach ($stats as $stat) {
            $level = $stat->risk_level;
            if (isset($result[$level])) {
                $result[$level]['total'] = intval($stat->total);
                $result[$level]['reviewed'] = intval($stat->reviewed);
                $result[$level]['safe'] = intval($stat->safe);
                $result[$level]['unsafe'] = intval($stat->unsafe);
            }
        }
        
        return $result;
    }
}
