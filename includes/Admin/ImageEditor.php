<?php
namespace PixelPapa\Admin;

use PixelPapa\Queue\QueueManager;

class ImageEditor {
    
    public function init() {
        add_action('admin_menu', [$this, 'add_editor_page']);
        add_action('admin_init', [$this, 'handle_requests']);
    }
    
    public function add_editor_page() {
        add_menu_page(
            'PixelPapa Editor',
            'PixelPapa',
            'manage_options',
            'pixelpapa-editor',
            [$this, 'render_page'],
            'dashicons-format-image',
            30
        );
    }
    
    public function render_page() {
        $attachment_id = intval($_GET['attachment_id'] ?? 0);
        $type = sanitize_text_field($_GET['type'] ?? 'enhance');
        
        if (!$attachment_id || !wp_attachment_is_image($attachment_id)) {
            wp_die('Invalid image.');
        }
        
        $image_url = wp_get_attachment_url($attachment_id);
        $image_meta = wp_get_attachment_metadata($attachment_id);
        $queue = QueueManager::instance();
        $jobs = $queue->get_attachment_jobs($attachment_id);
        
        $costs = ['enhance' => 1, 'upscale' => 2, 'background' => 3, 'video' => 35];
        $cost = $costs[$type] ?? 1;
        
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>PixelPapa Editor</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #0a0a0f; color: #fff; min-height: 100vh; }
                .header { background: #15151f; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #2a2a35; }
                .logo { display: flex; align-items: center; gap: 0.5rem; font-size: 1.25rem; font-weight: 600; color: #6366f1; }
                .nav-tabs { display: flex; gap: 0.5rem; background: #1e1e2d; padding: 0.25rem; border-radius: 0.75rem; }
                .nav-tab { padding: 0.625rem 1.25rem; border-radius: 0.5rem; text-decoration: none; color: #9ca3af; font-size: 0.875rem; transition: all 0.2s; }
                .nav-tab:hover { color: #fff; }
                .nav-tab.active { background: #6366f1; color: white; }
                .credits-badge { background: linear-gradient(135deg, #6366f1, #8b5cf6); padding: 0.5rem 1rem; border-radius: 2rem; font-size: 0.875rem; font-weight: 600; }
                .container { display: flex; height: calc(100vh - 65px); }
                .main { flex: 1; padding: 2rem; overflow-y: auto; }
                .sidebar { width: 360px; background: #15151f; border-left: 1px solid #2a2a35; padding: 1.5rem; overflow-y: auto; }
                .preview-box { background: #1e1e2d; border-radius: 1rem; padding: 2rem; text-align: center; margin-bottom: 1.5rem; }
                .preview-box img { max-width: 100%; max-height: 400px; border-radius: 0.5rem; }
                .image-info { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; padding: 1rem; background: #1e1e2d; border-radius: 0.75rem; }
                .info-item { text-align: center; }
                .info-label { font-size: 0.75rem; color: #6b7280; margin-bottom: 0.25rem; }
                .info-value { font-size: 0.875rem; font-weight: 500; }
                .panel { background: #1e1e2d; border-radius: 0.75rem; padding: 1.25rem; margin-bottom: 1rem; }
                .panel-title { font-size: 0.875rem; font-weight: 600; margin-bottom: 1rem; }
                .option-group { margin-bottom: 1.25rem; }
                .option-label { font-size: 0.75rem; color: #9ca3af; margin-bottom: 0.5rem; display: block; }
                .option-buttons { display: flex; gap: 0.5rem; flex-wrap: wrap; }
                .option-btn { padding: 0.5rem 1rem; border: 1px solid #2a2a35; background: transparent; color: #9ca3af; border-radius: 0.5rem; cursor: pointer; font-size: 0.875rem; }
                .option-btn.active { background: #6366f1; border-color: #6366f1; color: white; }
                .slider-container { margin-top: 1rem; }
                .slider-header { display: flex; justify-content: space-between; font-size: 0.75rem; margin-bottom: 0.5rem; }
                .slider { width: 100%; height: 4px; background: #2a2a35; border-radius: 2px; -webkit-appearance: none; }
                .slider::-webkit-slider-thumb { -webkit-appearance: none; width: 16px; height: 16px; background: #6366f1; border-radius: 50%; cursor: pointer; }
                .process-btn { width: 100%; padding: 1rem; background: linear-gradient(135deg, #6366f1, #8b5cf6); border: none; border-radius: 0.75rem; color: white; font-size: 1rem; font-weight: 600; cursor: pointer; display: flex; justify-content: center; align-items: center; gap: 0.5rem; }
                .credit-tag { background: rgba(255,255,255,0.2); padding: 0.25rem 0.75rem; border-radius: 1rem; font-size: 0.75rem; }
                .success-msg { background: rgba(34,197,94,0.1); border: 1px solid #22c55e; color: #22c55e; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; }
                .error-msg { background: rgba(239,68,68,0.1); border: 1px solid #ef4444; color: #ef4444; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; }
            </style>
        </head>
        <body>
            <header class="header">
                <a href="<?php echo admin_url('upload.php'); ?>" class="logo"><i class="fas fa-arrow-left"></i> PixelPapa</a>
                <nav class="nav-tabs">
                    <a href="<?php echo admin_url('admin.php?page=pixelpapa-editor&attachment_id=' . $attachment_id . '&type=enhance'); ?>" class="nav-tab <?php echo $type === 'enhance' ? 'active' : ''; ?>">Enhancer</a>
                    <a href="<?php echo admin_url('admin.php?page=pixelpapa-editor&attachment_id=' . $attachment_id . '&type=upscale'); ?>" class="nav-tab <?php echo $type === 'upscale' ? 'active' : ''; ?>">Upscale</a>
                    <a href="<?php echo admin_url('admin.php?page=pixelpapa-editor&attachment_id=' . $attachment_id . '&type=video'); ?>" class="nav-tab <?php echo $type === 'video' ? 'active' : ''; ?>">Video</a>
                </nav>
                <div class="credits-badge"><i class="fas fa-bolt"></i> <?php echo $cost; ?> credits</div>
            </header>
            
            <div class="container">
                <main class="main">
                    <?php if (isset($_GET['success'])): ?>
                        <div class="success-msg"><i class="fas fa-check-circle"></i> Processing started! Check back soon.</div>
                    <?php endif; ?>
                    
                    <?php if (isset($_GET['error'])): ?>
                        <div class="error-msg"><i class="fas fa-exclamation-circle"></i> <?php echo esc_html($_GET['error']); ?></div>
                    <?php endif; ?>
                    
                    <div class="preview-box">
                        <img src="<?php echo esc_url($image_url); ?>" alt="Preview">
                    </div>
                    
                    <div class="image-info">
                        <div class="info-item">
                            <div class="info-label">Dimensions</div>
                            <div class="info-value"><?php echo ($image_meta['width'] ?? '?') . ' × ' . ($image_meta['height'] ?? '?') . ' px'; ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Size</div>
                            <div class="info-value">
                                <?php 
                                $file = get_attached_file($attachment_id);
                                echo $file && file_exists($file) ? size_format(filesize($file)) : 'Unknown';
                                ?>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Format</div>
                            <div class="info-value"><?php echo strtoupper(pathinfo($image_url, PATHINFO_EXTENSION)); ?></div>
                        </div>
                    </div>
                </main>
                
                <aside class="sidebar">
                    <form method="post">
                        <?php wp_nonce_field('pixelpapa_process', 'pixelpapa_nonce'); ?>
                        <input type="hidden" name="attachment_id" value="<?php echo $attachment_id; ?>">
                        <input type="hidden" name="job_type" value="<?php echo esc_attr($type); ?>">
                        
                        <div class="panel">
                            <div class="panel-title">Operations</div>
                            
                            <?php if ($type === 'upscale'): ?>
                                <div class="option-group">
                                    <span class="option-label">AI Model</span>
                                    <div class="option-buttons">
                                        <button type="button" class="option-btn active">Prime</button>
                                        <button type="button" class="option-btn">Gentle</button>
                                    </div>
                                </div>
                                
                                <div class="option-group">
                                    <span class="option-label">Scale Factor</span>
                                    <div class="option-buttons">
                                        <button type="button" class="option-btn">2×</button>
                                        <button type="button" class="option-btn active">4×</button>
                                        <button type="button" class="option-btn">8×</button>
                                    </div>
                                </div>
                                
                            <?php elseif ($type === 'video'): ?>
                                <div class="option-group">
                                    <span class="option-label">Duration</span>
                                    <div class="option-buttons">
                                        <button type="button" class="option-btn active">5 sec (35 cr)</button>
                                        <button type="button" class="option-btn">10 sec (70 cr)</button>
                                    </div>
                                    <input type="hidden" name="duration" value="5">
                                </div>
                                
                            <?php else: ?>
                                <div class="option-group">
                                    <span class="option-label">HDR Enhancement</span>
                                    <div class="slider-container">
                                        <div class="slider-header">
                                            <span>Intensity</span>
                                            <span>60%</span>
                                        </div>
                                        <input type="range" class="slider" name="hdr" min="0" max="100" value="60">
                                    </div>
                                </div>
                                
                                <div class="option-group">
                                    <span class="option-label">Options</span>
                                    <label style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.5rem;">
                                        <span>Color Correction</span>
                                        <input type="checkbox" name="color_correction" checked>
                                    </label>
                                    <label style="display:flex;align-items:center;justify-content:space-between;">
                                        <span>Deblur</span>
                                        <input type="checkbox" name="deblur" checked>
                                    </label>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <button type="submit" name="pixelpapa_process" class="process-btn">
                            <?php if ($type === 'upscale'): ?>
                                <i class="fas fa-expand"></i> Upscale Image
                            <?php elseif ($type === 'video'): ?>
                                <i class="fas fa-video"></i> Generate Video
                            <?php else: ?>
                                <i class="fas fa-magic"></i> Enhance Image
                            <?php endif; ?>
                            <span class="credit-tag"><?php echo $cost; ?> cr</span>
                        </button>
                    </form>
                </aside>
            </div>
        </body>
        </html>
        <?php
    }
    
    public function handle_requests() {
        if (!isset($_POST['pixelpapa_process']) || !isset($_POST['pixelpapa_nonce'])) return;
        
        if (!wp_verify_nonce($_POST['pixelpapa_nonce'], 'pixelpapa_process')) {
            wp_die('Security check failed.');
        }
        
        $attachment_id = intval($_POST['attachment_id'] ?? 0);
        $job_type = sanitize_text_field($_POST['job_type'] ?? 'enhance');
        
        if (!$attachment_id || !wp_attachment_is_image($attachment_id)) {
            wp_die('Invalid image.');
        }
        
        $operations = [];
        if ($job_type === 'enhance') {
            $operations = [
                'hdr' => intval($_POST['hdr'] ?? 60),
                'color_correction' => isset($_POST['color_correction']),
                'deblur' => isset($_POST['deblur']),
            ];
        } elseif ($job_type === 'upscale') {
            $operations = ['upscale' => 'smart_enhance'];
        } elseif ($job_type === 'video') {
            $operations = ['duration' => intval($_POST['duration'] ?? 5)];
        }
        
        $queue = QueueManager::instance();
        
        $jobs = $queue->get_attachment_jobs($attachment_id);
        foreach ($jobs as $job) {
            if ($job['job_type'] === $job_type && in_array($job['status'], ['pending', 'processing'])) {
                wp_redirect(admin_url('admin.php?page=pixelpapa-editor&attachment_id=' . $attachment_id . '&type=' . $job_type . '&error=already_processing'));
                exit;
            }
        }
        
        $result = $queue->add_job($attachment_id, $job_type, $operations, $job_type === 'video' ? 'video/generate' : 'image/edit/async');
        
        if (is_wp_error($result)) {
            wp_redirect(admin_url('admin.php?page=pixelpapa-editor&attachment_id=' . $attachment_id . '&type=' . $job_type . '&error=' . urlencode($result->get_error_message())));
            exit;
        }
        
        wp_redirect(admin_url('admin.php?page=pixelpapa-editor&attachment_id=' . $attachment_id . '&type=' . $job_type . '&success=1'));
        exit;
    }
}
