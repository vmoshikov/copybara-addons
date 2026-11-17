<?php
/**
 * Plugin Name: copybara.ai Integration
 * Plugin URI: https://copybara.ai/docs/publishing
 * Description: Bidirectional Copybara ↔ WordPress: publish/update posts (image placement), and Copybara as WordPress 7 AI provider (OpenRouter proxy, billed tokens).
 * Version: 1.1.0
 * Author: Copybara
 * Author URI: https://copybara.ai
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: copybara-ai-integration
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package Cbai
 */

defined('ABSPATH') || exit;

define('CBAI_VERSION', '1.1.0');
define('CBAI_PATH', plugin_dir_path(__FILE__));
define('CBAI_URL', plugin_dir_url(__FILE__));
// Admin settings screen slug (must match "Text Domain" / WordPress.org plugin slug).
define('CBAI_SLUG', 'copybara-ai-integration');
// i18n: use the literal string 'copybara-ai-integration' in __(), esc_html_e(), etc. (must match the Text Domain header; Plugin Check disallows variables).

require_once CBAI_PATH . 'includes/class-cbai-settings.php';
require_once CBAI_PATH . 'includes/class-cbai-post-creator.php';
require_once CBAI_PATH . 'includes/class-cbai-rest-controller.php';
require_once CBAI_PATH . 'includes/class-cbai-ajax-handler.php';
require_once CBAI_PATH . 'includes/class-cbai-ai-bootstrap.php';

class Cbai_Plugin {

    public static function init() {
        Cbai_Settings::migrate_from_legacy();
        add_action('rest_api_init', [__CLASS__, 'register_rest_routes']);
        Cbai_Settings::init();
        Cbai_Ajax_Handler::init();
        Cbai_Ai_Bootstrap::init();
    }

    public static function register_rest_routes() {
        $controller = new Cbai_REST_Controller();
        $controller->register_routes();
    }

    public static function activate() {
        Cbai_Settings::migrate_from_legacy();
        Cbai_Settings::maybe_create_api_key();
        flush_rewrite_rules();
    }

    public static function deactivate() {
        flush_rewrite_rules();
    }
}

add_action('plugins_loaded', [Cbai_Plugin::class, 'init']);
register_activation_hook(__FILE__, [Cbai_Plugin::class, 'activate']);
register_deactivation_hook(__FILE__, [Cbai_Plugin::class, 'deactivate']);
