<?php
namespace PixelPapa\Core;

class Deactivator {
    
    public static function deactivate() {
        // Clear scheduled cron jobs
        wp_clear_scheduled_hook('pixelpapa_process_queue');
        
        // Note: We don't delete database tables or options on deactivation
        // That should happen on uninstall
    }
}
