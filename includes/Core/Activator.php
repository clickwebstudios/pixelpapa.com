<?php
namespace PixelPapa\Core;

use PixelPapa\Database\Schema;

class Activator {
    
    public static function activate() {
        // Create database tables
        Schema::create_tables();
        
        // Set default options
        add_option('pixelpapa_api_key', '');
        add_option('pixelpapa_webhook_secret', '');
        add_option('pixelpapa_auto_enhance', false);
        add_option('pixelpapa_default_operations', json_encode([
            'restorations' => ['upscale' => 'smart_enhance', 'decompress' => 'auto'],
            'adjustments' => ['hdr' => 60, 'sharpness' => 40],
        ]));
        
        // Schedule cron job
        if (!wp_next_scheduled('pixelpapa_process_queue')) {
            wp_schedule_event(time(), 'five_minutes', 'pixelpapa_process_queue');
        }
        
        // Flush rewrite rules for REST API
        flush_rewrite_rules();
    }
}
