<?php
namespace PixelPapa\API;

use PixelPapa\Queue\QueueManager;

class WebhookHandler {
    
    private $queue;
    
    public function __construct() {
        $this->queue = QueueManager::instance();
    }
    
    /**
     * Register REST endpoint
     */
    public function register_routes() {
        register_rest_route('pixelpapa/v1', '/webhook', [
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'handle_webhook'],
                'permission_callback' => [$this, 'verify_signature'],
            ],
        ]);
    }
    
    /**
     * Verify webhook signature
     */
    public function verify_signature($request) {
        $secret = get_option('pixelpapa_webhook_secret');
        
        // If no secret set, accept all (not recommended for production)
        if (empty($secret)) {
            return true;
        }
        
        $signature = $request->get_header('X-Claid-Hmac-SHA256');
        
        if (empty($signature)) {
            return new \WP_Error(
                'missing_signature',
                __('Webhook signature missing', 'pixelpapa'),
                ['status' => 401]
            );
        }
        
        $body = $request->get_body();
        $expected = 'sha256=' . hash_hmac('sha256', $body, $secret);
        
        if (!hash_equals($expected, $signature)) {
            return new \WP_Error(
                'invalid_signature',
                __('Webhook signature invalid', 'pixelpapa'),
                ['status' => 401]
            );
        }
        
        return true;
    }
    
    /**
     * Handle incoming webhook
     */
    public function handle_webhook($request) {
        $body = $request->get_json_params();
        
        if (empty($body['data'])) {
            return new \WP_Error(
                'invalid_payload',
                __('Invalid webhook payload', 'pixelpapa'),
                ['status' => 400]
            );
        }
        
        $data = $body['data'];
        $job_id = $data['id'] ?? null;
        $status = $data['status'] ?? null;
        
        if (!$job_id || !$status) {
            return new \WP_Error(
                'missing_data',
                __('Missing job ID or status', 'pixelpapa'),
                ['status' => 400]
            );
        }
        
        // Find job in our database
        $job = $this->queue->get_job_by_api_id($job_id);
        
        if (!$job) {
            return new \WP_Error(
                'job_not_found',
                __('Job not found', 'pixelpapa'),
                ['status' => 404]
            );
        }
        
        // Process based on status
        switch ($status) {
            case 'DONE':
                $this->handle_success($job, $data);
                break;
                
            case 'ERROR':
                $this->handle_error($job, $data);
                break;
                
            default:
                // Update status for intermediate states
                $this->queue->update_job($job['id'], [
                    'status' => strtolower($status),
                ]);
        }
        
        return new \WP_REST_Response(['success' => true], 200);
    }
    
    /**
     * Handle successful job
     */
    private function handle_success($job, $data) {
        $result = $data['result'] ?? [];
        $output = $result['output_object'] ?? [];
        
        $tmp_url = $output['tmp_url'] ?? null;
        
        if (!$tmp_url) {
            $this->queue->update_job($job['id'], [
                'status' => 'error',
                'error_message' => __('No output URL in response', 'pixelpapa'),
            ]);
            return;
        }
        
        // Download and save the result
        $handler = $job['job_type'] === 'video' 
            ? new \PixelPapa\Video\VideoHandler()
            : new \PixelPapa\Media\ImageHandler();
            
        $result_attachment_id = $handler->save_result($job['attachment_id'], $tmp_url);
        
        if (is_wp_error($result_attachment_id)) {
            $this->queue->update_job($job['id'], [
                'status' => 'error',
                'error_message' => $result_attachment_id->get_error_message(),
            ]);
            return;
        }
        
        $this->queue->update_job($job['id'], [
            'status' => 'done',
            'result_attachment_id' => $result_attachment_id,
            'result_url' => $tmp_url,
        ]);
        
        // Trigger action for extensions
        do_action('pixelpapa_job_completed', $job['id'], $result_attachment_id);
    }
    
    /**
     * Handle failed job
     */
    private function handle_error($job, $data) {
        $errors = $data['errors'] ?? [];
        $error_message = !empty($errors) 
            ? $errors[0]['error'] 
            : __('Unknown error', 'pixelpapa');
            
        $this->queue->update_job($job['id'], [
            'status' => 'error',
            'error_message' => $error_message,
        ]);
        
        do_action('pixelpapa_job_failed', $job['id'], $error_message);
    }
}
