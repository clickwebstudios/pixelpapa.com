<?php
/**
 * Plugin Name: PixelPapa
 * Description: AI-powered image enhancement and video generation using Claid.ai API
 * Version: 1.0.0
 * Author: PixelPapa Team
 * Requires at least: 5.9
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: pixelpapa
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('PIXELPAPA_VERSION', '1.0.0');
define('PIXELPAPA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PIXELPAPA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PIXELPAPA_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'PixelPapa\\';
    $base_dir = PIXELPAPA_PLUGIN_DIR . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Activation hook
register_activation_hook(__FILE__, function () {
    require_once PIXELPAPA_PLUGIN_DIR . 'includes/Core/Activator.php';
    PixelPapa\Core\Activator::activate();
});

// Deactivation hook
register_deactivation_hook(__FILE__, function () {
    require_once PIXELPAPA_PLUGIN_DIR . 'includes/Core/Deactivator.php';
    PixelPapa\Core\Deactivator::deactivate();
});

// Initialize plugin
add_action('plugins_loaded', function () {
    require_once PIXELPAPA_PLUGIN_DIR . 'includes/Core/Plugin.php';
    PixelPapa\Core\Plugin::instance()->init();
});
