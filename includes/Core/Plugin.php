<?php
namespace PixelPapa\Core;

use PixelPapa\API\WebhookHandler;
use PixelPapa\Admin\Settings;
use PixelPapa\Admin\MediaLibrary;
use PixelPapa\Queue\QueueManager;

class Plugin {
    
    private static $instance = null;
    
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {}
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Load text domain
        add_action('init', [$this, 'load_textdomain']);
        
        // Register REST routes
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        
        // Admin hooks
        if (is_admin()) {
            $this->init_admin();
        }
        
        // Register cron job
        add_action('pixelpapa_process_queue', [$this, 'process_queue']);
        if (!wp_next_scheduled('pixelpapa_process_queue')) {
            wp_schedule_event(time(), 'five_minutes', 'pixelpapa_process_queue');
        }
        
        // Add custom cron schedule
        add_filter('cron_schedules', [$this, 'add_cron_schedules']);
    }
    
    /**
     * Load text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'pixelpapa',
            false,
            dirname(PIXELPAPA_PLUGIN_BASENAME) . '/languages'
        );
    }
    
    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        $webhook = new WebhookHandler();
        $webhook->register_routes();
    }
    
    /**
     * Initialize admin functionality
     */
    private function init_admin() {
        $settings = new Settings();
        $settings->init();
        
        $media = new MediaLibrary();
        $media->init();
    }
    
    /**
     * Add custom cron schedules
     */
    public function add_cron_schedules($schedules) {
        $schedules['five_minutes'] = [
            'interval' => 300,
            'display' => __('Every 5 Minutes', 'pixelpapa'),
        ];
        return $schedules;
    }
    
    /**
     * Process queue (cron job)
     */
    public function process_queue() {
        $queue = QueueManager::instance();
        $jobs = $queue->get_pending_jobs(5);
        
        if (empty($jobs)) {
            return;
        }
        
        $client = new \PixelPapa\API\ClaidClient();
        
        foreach ($jobs as $job) {
            $this->process_job($job, $client);
        }
    }
    
    /**
     * Process single job
     */
    private function process_job($job, $client) {
        $queue = QueueManager::instance();
        
        // Update status to processing
        $queue->update_job($job['id'], ['status' => 'processing']);
        
        // Get image URL
        $image_url = wp_get_attachment_url($job['attachment_id']);
        
        if (!$image_url) {
            $queue->update_job($job['id'], [
                'status' => 'error',
                'error_message' => __('Image URL not found', 'pixelpapa'),
            ]);
            return;
        }
        
        $operations = json_decode($job['operations'], true);
        
        // Call API based on job type
        switch ($job['job_type']) {
            case 'enhance':
            case 'upscale':
                $response = $client->enhance_image_async($image_url, $operations);
                break;
                
            case 'video':
                $response = $client->generate_video($image_url, $operations);
                break;
                
            default:
                $queue->update_job($job['id'], [
                    'status' => 'error',
                    'error_message' => __('Unknown job type', 'pixelpapa'),
                ]);
                return;
        }
        
        if (is_wp_error($response)) {
            $queue->update_job($job['id'], [
                'status' => 'error',
                'error_message' => $response->get_error_message(),
            ]);
            return;
        }
        
        // Store API job ID
        $api_job_id = $response['data']['id'] ?? null;
        
        if ($api_job_id) {
            $queue->update_job($job['id'], [
                'api_job_id' => $api_job_id,
                'status' => 'processing',
            ]);
        } else {
            $queue->update_job($job['id'], [
                'status' => 'error',
                'error_message' => __('No job ID received', 'pixelpapa'),
            ]);
        }
    }
}
