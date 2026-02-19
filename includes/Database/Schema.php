<?php
namespace PixelPapa\Database;

class Schema {
    
    private static $table_name = 'pixelpapa_jobs';
    
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::$table_name;
    }
    
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = self::get_table_name();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            attachment_id bigint(20) unsigned NOT NULL,
            job_type varchar(20) NOT NULL COMMENT 'enhance, upscale, video',
            status varchar(20) NOT NULL DEFAULT 'pending' COMMENT 'pending, processing, done, error, cancelled',
            api_job_id varchar(50) DEFAULT NULL COMMENT 'Claid API job ID',
            api_endpoint varchar(50) DEFAULT NULL COMMENT 'image/edit, image/edit/async, video/generate',
            operations longtext DEFAULT NULL COMMENT 'JSON of operations parameters',
            result_attachment_id bigint(20) unsigned DEFAULT NULL,
            result_url varchar(500) DEFAULT NULL,
            error_message text DEFAULT NULL,
            retry_count int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY attachment_id (attachment_id),
            KEY status (status),
            KEY api_job_id (api_job_id),
            KEY created_at (created_at)
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Store database version
        update_option('pixelpapa_db_version', PIXELPAPA_VERSION);
    }
    
    public static function drop_tables() {
        global $wpdb;
        $table_name = self::get_table_name();
        $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
        delete_option('pixelpapa_db_version');
    }
}
