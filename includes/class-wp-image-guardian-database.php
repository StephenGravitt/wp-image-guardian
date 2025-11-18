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
            user_decision varchar(20) DEFAULT NULL,
            checked_at datetime DEFAULT CURRENT_TIMESTAMP,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY attachment_id (attachment_id),
            KEY status (status),
            KEY risk_level (risk_level),
            KEY checked_at (checked_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
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
        
        $image_url = wp_get_attachment_url($attachment_id);
        if (!$image_url) {
            return false;
        }
        
        // Sanitize image URL
        $image_url = esc_url_raw($image_url);
        $image_hash = $this->generate_image_hash($image_url);
        
        $risk_level = $this->calculate_risk_level($results);
        
        // Validate risk level
        $allowed_risk_levels = ['safe', 'warning', 'danger', 'unknown'];
        if (!in_array($risk_level, $allowed_risk_levels, true)) {
            $risk_level = 'unknown';
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
        
        $data = [
            'attachment_id' => $attachment_id,
            'image_url' => $image_url,
            'image_hash' => $image_hash,
            'search_id' => $search_id,
            'status' => 'completed',
            'results_count' => $results_count,
            'results_data' => $results_data,
            'risk_level' => $risk_level,
            'checked_at' => current_time('mysql'),
        ];
        
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$this->table_name} WHERE attachment_id = %d",
            $attachment_id
        ));
        
        if ($existing) {
            $updated = $wpdb->update(
                $this->table_name,
                $data,
                ['attachment_id' => $attachment_id],
                ['%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s'],
                ['%d']
            );
            return $updated !== false ? $existing->id : false;
        } else {
            $inserted = $wpdb->insert($this->table_name, $data, ['%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s']);
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
        
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT status, risk_level, user_decision, checked_at FROM {$this->table_name} WHERE attachment_id = %d",
            $attachment_id
        ));
        
        return $result;
    }
    
    public function mark_image_safe($attachment_id) {
        global $wpdb;
        
        // Validate attachment ID
        $attachment_id = absint($attachment_id);
        if ($attachment_id <= 0) {
            return false;
        }
        
        return $wpdb->update(
            $this->table_name,
            ['user_decision' => 'safe', 'updated_at' => current_time('mysql')],
            ['attachment_id' => $attachment_id],
            ['%s', '%s'],
            ['%d']
        ) !== false;
    }
    
    public function mark_image_unsafe($attachment_id) {
        global $wpdb;
        
        // Validate attachment ID
        $attachment_id = absint($attachment_id);
        if ($attachment_id <= 0) {
            return false;
        }
        
        return $wpdb->update(
            $this->table_name,
            ['user_decision' => 'unsafe', 'updated_at' => current_time('mysql')],
            ['attachment_id' => $attachment_id],
            ['%s', '%s'],
            ['%d']
        ) !== false;
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
        $total_results = $results['total_results'] ?? 0;
        
        $risk_level = 'unknown';
        if ($total_results === 0) {
            $risk_level = 'safe';
        } elseif ($total_results <= 3) {
            $risk_level = 'warning';
        } else {
            $risk_level = 'danger';
        }
        
        // Apply filter to allow modification of risk level
        return apply_filters('wp_image_guardian_risk_level', $risk_level, $results, $total_results);
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
}
