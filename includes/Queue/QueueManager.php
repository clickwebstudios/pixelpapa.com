<?php
namespace PixelPapa\Queue;

use PixelPapa\Database\Schema;

class QueueManager {
    
    private static $instance = null;
    private $table_name;
    
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->table_name = Schema::get_table_name();
    }
    
    /**
     * Add job to queue
     */
    public function add_job($attachment_id, $job_type, $operations = [], $api_endpoint = '') {
        global $wpdb;
        
        $result = $wpdb->insert(
            $this->table_name,
            [
                'attachment_id' => $attachment_id,
                'job_type' => $job_type,
                'status' => 'pending',
                'operations' => json_encode($operations),
                'api_endpoint' => $api_endpoint,
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s']
        );
        
        if ($result === false) {
            return new \WP_Error('db_error', $wpdb->last_error);
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get pending jobs
     */
    public function get_pending_jobs($limit = 10) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
            WHERE status = 'pending' 
            AND retry_count < 3
            ORDER BY created_at ASC
            LIMIT %d",
            $limit
        ), ARRAY_A);
    }
    
    /**
     * Update job status
     */
    public function update_job($job_id, $data) {
        global $wpdb;
        
        if (isset($data['status']) && in_array($data['status'], ['done', 'error'])) {
            $data['completed_at'] = current_time('mysql');
        }
        
        $data['updated_at'] = current_time('mysql');
        
        return $wpdb->update(
            $this->table_name,
            $data,
            ['id' => $job_id],
            null,
            ['%d']
        );
    }
    
    /**
     * Get job by ID
     */
    public function get_job($job_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $job_id
        ), ARRAY_A);
    }
    
    /**
     * Get job by API job ID
     */
    public function get_job_by_api_id($api_job_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE api_job_id = %s",
            $api_job_id
        ), ARRAY_A);
    }
    
    /**
     * Increment retry count
     */
    public function increment_retry($job_id) {
        global $wpdb;
        
        return $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table_name} 
            SET retry_count = retry_count + 1,
                updated_at = NOW()
            WHERE id = %d",
            $job_id
        ));
    }
    
    /**
     * Get jobs for attachment
     */
    public function get_attachment_jobs($attachment_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
            WHERE attachment_id = %d
            ORDER BY created_at DESC",
            $attachment_id
        ), ARRAY_A);
    }
}
