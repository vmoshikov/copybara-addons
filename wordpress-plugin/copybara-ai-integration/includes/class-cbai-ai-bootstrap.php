<?php
/**
 * Bootstrap Copybara as WordPress 7 AI Client provider (OpenRouter proxy).
 *
 * @package Cbai
 * @license GPL-2.0-or-later
 */

defined('ABSPATH') || exit;

class Cbai_Ai_Bootstrap {

    public static function init() {
        add_action('init', [__CLASS__, 'register_provider'], 5);
        require_once CBAI_PATH . 'includes/class-cbai-ai-rewrite.php';
        Cbai_Ai_Rewrite::init();
    }

    public static function register_provider() {
        if (!class_exists('\WordPress\AiClient\AiClient')) {
            return;
        }

        require_once CBAI_PATH . 'includes/ai/class-cbai-ai-model-metadata-directory.php';
        require_once CBAI_PATH . 'includes/ai/class-cbai-ai-text-model.php';
        require_once CBAI_PATH . 'includes/ai/class-cbai-ai-provider.php';

        try {
            $registry = \WordPress\AiClient\AiClient::defaultRegistry();
            if (method_exists($registry, 'hasProvider') && $registry->hasProvider(Cbai_Ai_Provider::class)) {
                return;
            }
            if (method_exists($registry, 'registerProvider')) {
                $registry->registerProvider(Cbai_Ai_Provider::class);
            }
        } catch (Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('Cbai AI provider registration failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Base URL for OpenAI-compatible Copybara AI proxy.
     */
    public static function get_ai_base_url() {
        $default = 'https://api.copybara.ai/v1/ai';
        /**
         * Filter Copybara AI provider base URL (no trailing slash).
         *
         * @param string $url Base URL ending with /v1/ai.
         */
        return rtrim((string) apply_filters('cbai_ai_base_url', $default), '/');
    }
}
