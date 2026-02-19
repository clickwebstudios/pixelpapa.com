<?php
namespace PixelPapa\Admin;

use PixelPapa\API\ClaidClient;

class Settings {
    
    public function init() {
        add_action('admin_menu', [$this, 'add_menu_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }
    
    /**
     * Add menu page
     */
    public function add_menu_page() {
        add_options_page(
            __('PixelPapa AI Settings', 'pixelpapa'),
            __('PixelPapa AI', 'pixelpapa'),
            'manage_options',
            'pixelpapa-settings',
            [$this, 'render_settings_page']
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('pixelpapa_settings', 'pixelpapa_api_key', [
            'sanitize_callback' => [$this, 'sanitize_api_key'],
        ]);
        
        register_setting('pixelpapa_settings', 'pixelpapa_webhook_secret');
        register_setting('pixelpapa_settings', 'pixelpapa_auto_enhance', 'boolval');
        register_setting('pixelpapa_settings', 'pixelpapa_default_operations');
        
        add_settings_section(
            'pixelpapa_api_section',
            __('API Configuration', 'pixelpapa'),
            [$this, 'render_api_section'],
            'pixelpapa-settings'
        );
        
        add_settings_field(
            'pixelpapa_api_key',
            __('Claid.ai API Key', 'pixelpapa'),
            [$this, 'render_api_key_field'],
            'pixelpapa-settings',
            'pixelpapa_api_section'
        );
        
        add_settings_field(
            'pixelpapa_webhook_secret',
            __('Webhook Secret', 'pixelpapa'),
            [$this, 'render_webhook_secret_field'],
            'pixelpapa-settings',
            'pixelpapa_api_section'
        );
        
        add_settings_section(
            'pixelpapa_general_section',
            __('General Settings', 'pixelpapa'),
            null,
            'pixelpapa-settings'
        );
        
        add_settings_field(
            'pixelpapa_auto_enhance',
            __('Auto-Enhance on Upload', 'pixelpapa'),
            [$this, 'render_auto_enhance_field'],
            'pixelpapa-settings',
            'pixelpapa_general_section'
        );
    }
    
    /**
     * Sanitize and validate API key
     */
    public function sanitize_api_key($value) {
        $value = sanitize_text_field($value);
        
        if (empty($value)) {
            return '';
        }
        
        // Validate key
        $client = new ClaidClient($value);
        if (!$client->validate_key()) {
            add_settings_error(
                'pixelpapa_api_key',
                'invalid_api_key',
                __('Invalid API key. Please check and try again.', 'pixelpapa')
            );
            return get_option('pixelpapa_api_key'); // Keep old value
        }
        
        add_settings_error(
            'pixelpapa_api_key',
            'valid_api_key',
            __('API key validated successfully!', 'pixelpapa'),
            'success'
        );
        
        return $value;
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('pixelpapa_settings'); ?>
                <?php do_settings_sections('pixelpapa-settings'); ?>
                <?php submit_button(); ?>
            </form>
            
            <h2><?php _e('Webhook URL', 'pixelpapa'); ?></h2>
            <p>
                <?php _e('Configure this URL in your Claid.ai account webhook settings:', 'pixelpapa'); ?>
            </p>
            <code><?php echo esc_url(rest_url('pixelpapa/v1/webhook')); ?></code>
        </div>
        
        <?php
    }
    
    /**
     * Render API section
     */
    public function render_api_section() {
        echo '<p>' . __('Enter your Claid.ai API credentials below.', 'pixelpapa') . '</p>';
        echo '<p>' . sprintf(
            __('Get your API key from %s', 'pixelpapa'),
            '<a href="https://claid.ai/account/api" target="_blank">claid.ai</a>'
        ) . '</p>';
    }
    
    /**
     * Render API key field
     */
    public function render_api_key_field() {
        $value = get_option('pixelpapa_api_key');
        ?>
        <input 
            type="password" 
            name="pixelpapa_api_key" 
            value="<?php echo esc_attr($value); ?>"
            class="regular-text"
        />
        <?php if ($value): ?>
            <span class="dashicons dashicons-yes-alt" style="color: green;"></span>
            <span style="color: green;"><?php _e('Configured', 'pixelpapa'); ?></span>
        <?php endif; ?>
        <?php
    }
    
    /**
     * Render webhook secret field
     */
    public function render_webhook_secret_field() {
        $value = get_option('pixelpapa_webhook_secret');
        ?>
        <input 
            type="text" 
            name="pixelpapa_webhook_secret" 
            value="<?php echo esc_attr($value); ?>"
            class="regular-text"
        />
        <p class="description">
            <?php _e('Optional. Used to verify webhook requests from Claid.ai.', 'pixelpapa'); ?>
        </p>
        <?php
    }
    
    /**
     * Render auto-enhance field
     */
    public function render_auto_enhance_field() {
        $value = get_option('pixelpapa_auto_enhance', false);
        ?>
        <label>
            <input 
                type="checkbox" 
                name="pixelpapa_auto_enhance" 
                value="1"
                <?php checked($value); ?>
            />
            <?php _e('Automatically enhance images on upload', 'pixelpapa'); ?>
        </label>
        <?php
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_assets($hook) {
        if ('settings_page_pixelpapa-settings' !== $hook) {
            return;
        }
        
        wp_enqueue_style(
            'pixelpapa-admin',
            PIXELPAPA_PLUGIN_URL . 'assets/css/admin.css',
            [],
            PIXELPAPA_VERSION
        );
    }
}
