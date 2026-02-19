<?php
namespace PixelPapa\API;

class ClaidClient {
    
    private $api_key;
    private $base_url = 'https://api.claid.ai/v1';
    private $timeout = 60;
    
    public function __construct($api_key = null) {
        $this->api_key = $api_key ?: get_option('pixelpapa_api_key');
    }
    
    /**
     * Validate API key
     */
    public function validate_key() {
        // Send a simple test request
        $response = $this->enhance_image(
            'https://claid.ai/doc-samples/bag.jpeg',
            ['resizing' => ['width' => 100]],
            true // test mode
        );
        
        return !is_wp_error($response);
    }
    
    /**
     * Enhance/Edit image (sync)
     */
    public function enhance_image($image_url, $operations = [], $test_mode = false) {
        $payload = [
            'input' => $image_url,
            'operations' => $this->build_operations($operations),
        ];
        
        if ($test_mode) {
            $payload['output'] = ['format' => ['type' => 'jpeg', 'quality' => 50]];
        }
        
        return $this->request('POST', '/image/edit', $payload);
    }
    
    /**
     * Async image edit
     */
    public function enhance_image_async($image_url, $operations = []) {
        $payload = [
            'input' => $image_url,
            'operations' => $this->build_operations($operations),
        ];
        
        return $this->request('POST', '/image/edit/async', $payload);
    }
    
    /**
     * Generate video from image (async)
     */
    public function generate_video($image_url, $options = []) {
        $defaults = [
            'prompt' => ['generate' => true],
            'duration' => 5,
            'guidance_scale' => 0.5,
        ];
        
        $options = wp_parse_args($options, $defaults);
        
        $payload = [
            'input' => $image_url,
            'options' => $options,
        ];
        
        return $this->request('POST', '/video/generate', $payload);
    }
    
    /**
     * Get async job status
     */
    public function get_job_status($job_id, $type = 'image') {
        $endpoint = $type === 'video' 
            ? "/video/generate/{$job_id}"
            : "/image/edit/async/{$job_id}";
            
        return $this->request('GET', $endpoint);
    }
    
    /**
     * Build operations payload
     */
    private function build_operations($operations) {
        $defaults = [
            'restorations' => [
                'upscale' => 'smart_enhance',
                'decompress' => 'auto',
            ],
            'adjustments' => [
                'hdr' => 60,
                'sharpness' => 40,
            ],
        ];
        
        return wp_parse_args($operations, $defaults);
    }
    
    /**
     * Make HTTP request
     */
    private function request($method, $endpoint, $body = null) {
        if (empty($this->api_key)) {
            return new \WP_Error('no_api_key', __('API key not configured', 'pixelpapa'));
        }
        
        $url = $this->base_url . $endpoint;
        
        $args = [
            'method' => $method,
            'timeout' => $this->timeout,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ],
        ];
        
        if ($body !== null) {
            $args['body'] = json_encode($body);
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($status_code >= 400) {
            $error_message = isset($data['error_message']) 
                ? $data['error_message'] 
                : __('API request failed', 'pixelpapa');
                
            return new \WP_Error(
                'api_error',
                $error_message,
                ['status_code' => $status_code, 'response' => $data]
            );
        }
        
        return $data;
    }
}
