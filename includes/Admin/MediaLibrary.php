<?php
namespace PixelPapa\Admin;

use PixelPapa\Queue\QueueManager;

class MediaLibrary {
    
    public function init() {
        // Add bulk actions
        add_filter('bulk_actions-upload', [$this, 'add_bulk_actions']);
        add_filter('handle_bulk_actions-upload', [$this, 'handle_bulk_actions'], 10, 3);
        
        // Add single image actions
        add_filter('media_row_actions', [$this, 'add_row_actions'], 10, 2);
        add_action('add_meta_boxes', [$this, 'add_attachment_meta_box']);
        
        // Handle single image action click
        add_action('admin_action_pixelpapa_single', [$this, 'handle_single_action']);
        
        // Auto-enhance on upload
        add_action('add_attachment', [$this, 'maybe_auto_enhance']);
        
        // Admin notices
        add_action('admin_notices', [$this, 'show_admin_notices']);
        
        // Enqueue media library scripts
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }
    
    /**
     * Handle single image action (redirect to new editor)
     */
    public function handle_single_action() {
        $attachment_id = intval($_GET['attachment_id'] ?? 0);
        $type = sanitize_text_field($_GET['type'] ?? 'enhance');
        
        // Redirect to new editor page
        wp_redirect(admin_url('admin.php?page=pixelpapa-editor&attachment_id=' . $attachment_id . '&type=' . $type));
        exit;
    }
    
    /**
     * Add bulk actions
     */
    public function add_bulk_actions($actions) {
        $actions['pixelpapa_enhance'] = __('AI Enhance Selected', 'pixelpapa');
        $actions['pixelpapa_upscale'] = __('AI Upscale Selected', 'pixelpapa');
        $actions['pixelpapa_video'] = __('Generate AI Video', 'pixelpapa');
        return $actions;
    }
    
    /**
     * Handle bulk actions
     */
    public function handle_bulk_actions($redirect_to, $action, $attachment_ids) {
        if (!in_array($action, ['pixelpapa_enhance', 'pixelpapa_upscale', 'pixelpapa_video'])) {
            return $redirect_to;
        }
        
        $queue = QueueManager::instance();
        $jobs_created = 0;
        
        $job_type = str_replace('pixelpapa_', '', $action);
        
        foreach ($attachment_ids as $attachment_id) {
            // Check if already has pending job
            $jobs = $queue->get_attachment_jobs($attachment_id);
            $has_pending = false;
            
            foreach ($jobs as $job) {
                if ($job['job_type'] === $job_type && in_array($job['status'], ['pending', 'processing'])) {
                    $has_pending = true;
                    break;
                }
            }
            
            if ($has_pending) {
                continue;
            }
            
            // Get operations based on job type
            $operations = $this->get_operations_for_type($job_type);
            
            $result = $queue->add_job(
                $attachment_id,
                $job_type,
                $operations,
                $job_type === 'video' ? 'video/generate' : 'image/edit/async'
            );
            
            if (!is_wp_error($result)) {
                $jobs_created++;
            }
        }
        
        $redirect_to = add_query_arg([
            'pixelpapa_bulk' => $job_type,
            'pixelpapa_count' => $jobs_created,
        ], $redirect_to);
        
        return $redirect_to;
    }
    
    /**
     * Get operations for job type
     */
    private function get_operations_for_type($type) {
        $defaults = json_decode(get_option('pixelpapa_default_operations'), true);
        
        switch ($type) {
            case 'upscale':
                return array_merge($defaults, [
                    'resizing' => ['width' => '200%', 'height' => '200%'],
                ]);
                
            case 'video':
                return [
                    'prompt' => ['generate' => true],
                    'duration' => 5,
                ];
                
            case 'enhance':
            default:
                return $defaults;
        }
    }
    
    /**
     * Add row actions
     */
    public function add_row_actions($actions, $post) {
        if (!wp_attachment_is_image($post->ID)) {
            return $actions;
        }
        
        // Link to editor page instead of direct action
        $enhance_url = admin_url('admin.php?page=pixelpapa-editor&attachment_id=' . $post->ID . '&type=enhance');
        $video_url = admin_url('admin.php?page=pixelpapa-editor&attachment_id=' . $post->ID . '&type=video');
        
        $actions['pixelpapa_enhance'] = sprintf(
            '<a href="%s" class="pixelpapa-enhance" data-id="%d">%s</a>',
            esc_url($enhance_url),
            $post->ID,
            __('AI Enhance', 'pixelpapa')
        );
        
        $actions['pixelpapa_video'] = sprintf(
            '<a href="%s" class="pixelpapa-video" data-id="%d">%s</a>',
            esc_url($video_url),
            $post->ID,
            __('Generate Video', 'pixelpapa')
        );
        
        return $actions;
    }
    
    /**
     * Add meta box to attachment edit screen
     */
    public function add_attachment_meta_box() {
        add_meta_box(
            'pixelpapa_meta_box',
            __('PixelPapa AI', 'pixelpapa'),
            [$this, 'render_meta_box'],
            'attachment',
            'side',
            'high'
        );
    }
    
    /**
     * Render meta box
     */
    public function render_meta_box($post) {
        if (!wp_attachment_is_image($post->ID)) {
            echo '<p>' . __('AI enhancement is only available for images.', 'pixelpapa') . '</p>';
            return;
        }
        
        $queue = QueueManager::instance();
        $jobs = $queue->get_attachment_jobs($post->ID);
        
        // Show existing jobs
        if (!empty($jobs)) {
            echo '<h4>' . __('Processing History', 'pixelpapa') . '</h4>';
            echo '<ul>';
            foreach ($jobs as $job) {
                $status_class = '';
                switch ($job['status']) {
                    case 'done': $status_class = 'green'; break;
                    case 'error': $status_class = 'red'; break;
                    case 'processing': $status_class = 'orange'; break;
                    default: $status_class = 'gray';
                }
                
                printf(
                    '<li>%s - <span style="color: %s;">%s</span>%s</li>',
                    esc_html(ucfirst($job['job_type'])),
                    esc_attr($status_class),
                    esc_html(ucfirst($job['status'])),
                    $job['status'] === 'done' && $job['result_attachment_id'] 
                        ? ' (<a href="' . esc_url(get_edit_post_link($job['result_attachment_id'])) . '" target="_blank">View</a>)'
                        : ''
                );
            }
            echo '</ul>';
        }
        
        // Action buttons - link to editor
        echo '<h4>' . __('Actions', 'pixelpapa') . '</h4>';
        
        $enhance_url = admin_url('admin.php?page=pixelpapa-editor&attachment_id=' . $post->ID . '&type=enhance');
        $video_url = admin_url('admin.php?page=pixelpapa-editor&attachment_id=' . $post->ID . '&type=video');
        $upscale_url = admin_url('admin.php?page=pixelpapa-editor&attachment_id=' . $post->ID . '&type=upscale');
        
        printf(
            '<a href="%s" class="button">%s</a> ',
            esc_url($enhance_url),
            __('Enhance Image', 'pixelpapa')
        );
        
        printf(
            '<a href="%s" class="button">%s</a> ',
            esc_url($video_url),
            __('Generate Video', 'pixelpapa')
        );
        
        printf(
            '<a href="%s" class="button">%s</a>',
            esc_url($upscale_url),
            __('Upscale 2x', 'pixelpapa')
        );
    }
    
    /**
     * Auto-enhance on upload
     */
    public function maybe_auto_enhance($attachment_id) {
        if (!get_option('pixelpapa_auto_enhance', false)) {
            return;
        }
        
        if (!wp_attachment_is_image($attachment_id)) {
            return;
        }
        
        $queue = QueueManager::instance();
        $operations = json_decode(get_option('pixelpapa_default_operations'), true);
        
        $queue->add_job(
            $attachment_id,
            'enhance',
            $operations,
            'image/edit/async'
        );
    }
    
    /**
     * Show admin notices
     */
    public function show_admin_notices() {
        // Bulk action notice
        if (isset($_GET['pixelpapa_bulk'])) {
            $count = intval($_GET['pixelpapa_count'] ?? 0);
            $type = sanitize_text_field($_GET['pixelpapa_bulk']);
            
            printf(
                '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                sprintf(
                    _n(
                        '%d job queued for %s.',
                        '%d jobs queued for %s.',
                        $count,
                        'pixelpapa'
                    ),
                    $count,
                    esc_html($type)
                )
            );
        }
        
        // Single action success notice
        if (isset($_GET['pixelpapa_single']) && $_GET['pixelpapa_single'] === 'success') {
            $type = sanitize_text_field($_GET['pixelpapa_type'] ?? 'enhance');
            
            printf(
                '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                sprintf(
                    __('AI %s job queued successfully! The image will be processed in the background.', 'pixelpapa'),
                    esc_html(ucfirst($type))
                )
            );
        }
        
        // Single action error notice
        if (isset($_GET['pixelpapa_error'])) {
            $error = sanitize_text_field($_GET['pixelpapa_error']);
            
            if ($error === 'already_processing') {
                $message = __('This image is already being processed. Please wait for it to complete.', 'pixelpapa');
            } else {
                $message = $error;
            }
            
            printf(
                '<div class="notice notice-error is-dismissible"><p>%s</p></div>',
                esc_html($message)
            );
        }
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_assets($hook) {
        if (!in_array($hook, ['upload.php', 'post.php', 'post-new.php'])) {
            return;
        }
        
        wp_enqueue_script(
            'pixelpapa-media',
            PIXELPAPA_PLUGIN_URL . 'assets/js/media.js',
            ['jquery'],
            PIXELPAPA_VERSION,
            true
        );
        
        wp_localize_script('pixelpapa-media', 'pixelpapa', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'strings' => [
                'confirm' => __('Are you sure you want to process this image?', 'pixelpapa'),
                'processing' => __('Processing...', 'pixelpapa'),
            ],
        ]);
    }
}
