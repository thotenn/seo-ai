<?php
/**
 * Plugin Name: SEO AI
 * Plugin URI:  https://github.com/thotenn/seo-ai
 * Description: Comprehensive SEO plugin with AI-powered optimization. Supports multiple AI providers including Ollama for local/free usage.
 * Version:     0.6.0
 * Author:      Thotenn
 * Author URI:  https://thotenn.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: seo-ai
 * Domain Path: /languages
 * Requires PHP: 8.0
 * Requires at least: 6.4
 */

defined('ABSPATH') || exit;

// Plugin constants
define('SEO_AI_VERSION', '0.6.0');
define('SEO_AI_FILE', __FILE__);
define('SEO_AI_PATH', plugin_dir_path(__FILE__));
define('SEO_AI_URL', plugin_dir_url(__FILE__));
define('SEO_AI_BASENAME', plugin_basename(__FILE__));
define('SEO_AI_SLUG', 'seo-ai');

// Autoloader
spl_autoload_register(function (string $class): void {
    $prefix = 'SeoAi\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    // Convert namespace to file path: SeoAi\Admin\Settings_Page → includes/admin/class-settings-page.php
    $parts = explode('\\', $relative);
    $class_name = array_pop($parts);
    // Convert CamelCase/Under_Score to kebab-case
    $file_name = 'class-' . strtolower(str_replace('_', '-', $class_name)) . '.php';
    // Namespace parts to directory path
    $dir = strtolower(implode('/', array_map(fn($p) => str_replace('_', '-', $p), $parts)));
    $path = SEO_AI_PATH . 'includes/' . ($dir ? $dir . '/' : '') . $file_name;

    if (file_exists($path)) {
        require_once $path;
    }
});

// Boot
function seo_ai(): SeoAi\Plugin {
    return SeoAi\Plugin::instance();
}

// Activation / Deactivation
register_activation_hook(__FILE__, [SeoAi\Activator::class, 'activate']);
register_deactivation_hook(__FILE__, [SeoAi\Deactivator::class, 'deactivate']);

// Initialize
add_action('plugins_loaded', function () {
    seo_ai();
}, 10);
