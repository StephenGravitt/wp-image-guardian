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
        
        $image_url = wp_get_attachment_url($attachment_id);
        $image_hash = $this->generate_image_hash($image_url);
        
        $risk_level = $this->calculate_risk_level($results);
        
        $data = [
            'attachment_id' => $attachment_id,
            'image_url' => $image_url,
            'image_hash' => $image_hash,
            'search_id' => $results['search_id'] ?? null,
            'status' => 'completed',
            'results_count' => $results['total_results'] ?? 0,
            'results_data' => json_encode($results),
            'risk_level' => $risk_level,
            'checked_at' => current_time('mysql'),
        ];
        
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$this->table_name} WHERE attachment_id = %d",
            $attachment_id
        ));
        
        if ($existing) {
            $wpdb->update(
                $this->table_name,
                $data,
                ['attachment_id' => $attachment_id],
                ['%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s'],
                ['%d']
            );
            return $existing->id;
        } else {
            $wpdb->insert($this->table_name, $data);
            return $wpdb->insert_id;
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
        
        return $wpdb->update(
            $this->table_name,
            ['user_decision' => 'safe', 'updated_at' => current_time('mysql')],
            ['attachment_id' => $attachment_id],
            ['%s', '%s'],
            ['%d']
        );
    }
    
    public function mark_image_unsafe($attachment_id) {
        global $wpdb;
        
        return $wpdb->update(
            $this->table_name,
            ['user_decision' => 'unsafe', 'updated_at' => current_time('mysql')],
            ['attachment_id' => $attachment_id],
            ['%s', '%s'],
            ['%d']
        );
    }
    
    public function get_unchecked_images($hours = 24) {
        global $wpdb;
        
        $date = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT p.ID, p.post_title, p.post_date 
             FROM {$wpdb->posts} p 
             LEFT JOIN {$this->table_name} ig ON p.ID = ig.attachment_id 
             WHERE p.post_type = 'attachment' 
             AND p.post_mime_type LIKE 'image/%' 
             AND p.post_date >= %s 
             AND ig.id IS NULL 
             ORDER BY p.post_date DESC",
            $date
        ));
    }
    
    public function get_checked_images($limit = 50, $offset = 0) {
        global $wpdb;
        
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
        
        if ($total_results === 0) {
            return 'safe';
        } elseif ($total_results <= 3) {
            return 'warning';
        } else {
            return 'danger';
        }
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
        if (empty($attachment_ids)) {
            return [];
        }
        
        global $wpdb;
        
        $placeholders = implode(',', array_fill(0, count($attachment_ids), '%d'));
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE attachment_id IN ($placeholders)",
            $attachment_ids
        ));
    }
}
