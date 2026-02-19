<?php
namespace PixelPapa\Admin;

use PixelPapa\Queue\QueueManager;
use PixelPapa\API\ClaidClient;

class StatusPage {
    
    public function init() {
        add_action('admin_menu', [$this, 'add_status_page']);
        add_action('admin_init', [$this, 'handle_actions']);
    }
    
    public function add_status_page() {
        add_submenu_page(
            'options-general.php',
            'PixelPapa Status',
            'PixelPapa Status',
            'manage_options',
            'pixelpapa-status',
            [$this, 'render_page']
        );
    }
    
    public function handle_actions() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'pixelpapa-status') return;
        
        // Run queue manually
        if (isset($_GET['action']) && $_GET['action'] === 'run_queue' && wp_verify_nonce($_GET['_wpnonce'], 'run_queue')) {
            do_action('pixelpapa_process_queue');
            wp_redirect(admin_url('admin.php?page=pixelpapa-status&ran=1'));
            exit;
        }
        
        // Test API connection
        if (isset($_GET['action']) && $_GET['action'] === 'test_api' && wp_verify_nonce($_GET['_wpnonce'], 'test_api')) {
            $client = new ClaidClient();
            $valid = $client->validate_key();
            wp_redirect(admin_url('admin.php?page=pixelpapa-status&api_test=' . ($valid ? 'success' : 'fail')));
            exit;
        }
    }
    
    public function render_page() {
        $queue = QueueManager::instance();
        
        // Get job counts
        global $wpdb;
        $table_name = \PixelPapa\Database\Schema::get_table_name();
        
        $counts = $wpdb->get_row("
            SELECT 
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as errors
            FROM {$table_name}
        ");
        
        // Check cron
        $next_cron = wp_next_scheduled('pixelpapa_process_queue');
        $api_key = get_option('pixelpapa_api_key');
        ?>
        
        <div class="wrap">
            <h1>PixelPapa Status & Diagnostics</h1>
            
            <?php if (isset($_GET['ran'])): ?>
                <div class="notice notice-success"><p><strong>✅ Queue processed!</strong> Check job statuses below.</p></div>
            <?php endif; ?>
            
            <?php if (isset($_GET['api_test'])): ?>
                <?php if ($_GET['api_test'] === 'success'): ?>
                    <div class="notice notice-success"><p><strong>✅ API Key is valid!</strong> Connection to Claid.ai successful.</p></div>
                <?php else: ?>
003e
                    <div class="notice notice-error"><p><strong>❌ API Key invalid!</strong> Check your API key in Settings.</p></div>
                <?php endif; ?>
            <?php endif; ?>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">
                
                <!-- System Status -->
                <div class="postbox">
                    <h2 style="padding: 15px; border-bottom: 1px solid #ccd0d4; margin: 0;">🖥️ System Status</h2>
                    <div style="padding: 15px;">
                        <table class="widefat" style="border: none;">
                            <tr>
                                <td>API Key Configured:</td>
                                <td><strong style="color: <?php echo $api_key ? '#22c55e' : '#ef4444'; ?>">
                                    <?php echo $api_key ? '✅ Yes' : '❌ No - Configure in Settings'; ?>
                                </strong></td>
                            </tr>
                            <tr>
                                <td>Cron Scheduled:</td>
                                <td><strong style="color: <?php echo $next_cron ? '#22c55e' : '#ef4444'; ?>">
                                    <?php echo $next_cron ? '✅ Yes (Next: ' . human_time_diff($next_cron, time()) . ')' : '❌ No - Will be fixed on next page load'; ?>
                                </strong></td>
                            </tr>
                            <tr>
                                <td>Database Table:</td>
                                <td><strong style="color: #22c55e">✅ Created</strong></td>
                            </tr>
                        </table>
                        
                        <div style="margin-top: 15px;">
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=pixelpapa-status&action=test_api'), 'test_api'); ?>" 
                               class="button button-secondary">
                                🧪 Test API Connection
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Job Statistics -->
                <div class="postbox">
                    <h2 style="padding: 15px; border-bottom: 1px solid #ccd0d4; margin: 0;">📊 Job Statistics</h2>
                    <div style="padding: 15px;">
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                            <div style="text-align: center; padding: 15px; background: #f0f6fc; border-radius: 5px;">
                                <div style="font-size: 32px; font-weight: bold; color: #f59e0b;"><?php echo intval($counts->pending); ?></div>
                                <div>⏳ Pending</div>
                            </div>
                            
                            <div style="text-align: center; padding: 15px; background: #f0f6fc; border-radius: 5px;">
                                <div style="font-size: 32px; font-weight: bold; color: #3b82f6;"><?php echo intval($counts->processing); ?></div>
                                <div>🔄 Processing</div>
                            </div>
                            
                            <div style="text-align: center; padding: 15px; background: #f0fdf4; border-radius: 5px;">
                                <div style="font-size: 32px; font-weight: bold; color: #22c55e;"><?php echo intval($counts->completed); ?></div>
                                <div>✅ Completed</div>
                            </div>
                            
                            <div style="text-align: center; padding: 15px; background: #fef2f2; border-radius: 5px;">
                                <div style="font-size: 32px; font-weight: bold; color: #ef4444;"><?php echo intval($counts->errors); ?></div>
                                <div>❌ Errors</div>
                            </div>
                        </div>
                        
                        <div style="margin-top: 15px;">
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=pixelpapa-status&action=run_queue'), 'run_queue'); ?>" 
                               class="button button-primary button-hero" style="width: 100%; text-align: center;"
003e
                                🚀 Run Queue Now (Manual)
                            </a>
                        </div>
                    </div>
                </div>
                
            </div>
            
            <!-- Recent Jobs -->
            <div class="postbox" style="margin-top: 20px;">
                <h2 style="padding: 15px; border-bottom: 1px solid #ccd0d4; margin: 0;">📝 Recent Jobs</h2>
                <div style="padding: 15px;">
                    <?php
                    $recent_jobs = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY created_at DESC LIMIT 10");
                    if ($recent_jobs): 
                    ?>
                        <table class="widefat">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>API Job ID</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_jobs as $job): ?>
                                    <tr>
                                        <td><?php echo $job->id; ?></td>
                                        <td><?php echo esc_html($job->job_type); ?></td>
                                        <td>
                                            <span style="padding: 3px 8px; border-radius: 3px; font-size: 12px; font-weight: bold; 
                                                <?php
                                                $colors = [
                                                    'pending' => 'background: #fef3c7; color: #d97706;',
                                                    'processing' => 'background: #dbeafe; color: #2563eb;',
                                                    'completed' => 'background: #d1fae5; color: #059669;',
                                                    'error' => 'background: #fee2e2; color: #dc2626;'
                                                ];
                                                echo $colors[$job->status] ?? '';
                                                ?>"
                                            >
                                                <?php echo esc_html($job->status); ?>
                                            </span>
                                        </td>
                                        <td><?php echo human_time_diff(strtotime($job->created_at), current_time('timestamp')); ?> ago</td>
                                        <td><?php echo $job->api_job_id ? esc_html($job->api_job_id) : '-'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
003e
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No jobs found.</p>
                    <?php endif; ?>
                </div>
            </div>
            
        </div>
        <?php
    }
}
