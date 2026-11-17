<?php
/**
 * admin-ajax handler (works when REST is disabled or filtered).
 *
 * @package Cbai
 * @license GPL-2.0-or-later
 */

defined('ABSPATH') || exit;

class Cbai_Ajax_Handler {

    const ACTION = 'copybara_ai_receive_post';

    public static function init() {
        add_action('wp_ajax_' . self::ACTION, [__CLASS__, 'handle']);
        add_action('wp_ajax_nopriv_' . self::ACTION, [__CLASS__, 'handle']);
        // Legacy action name for existing saved URLs.
        add_action('wp_ajax_copybara_receive_post', [__CLASS__, 'handle']);
        add_action('wp_ajax_nopriv_copybara_receive_post', [__CLASS__, 'handle']);
    }

    public static function get_url() {
        return admin_url('admin-ajax.php?action=' . self::ACTION);
    }

    public static function handle() {
        try {
            $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper(sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD']))) : '';
            if ($method !== 'POST') {
                self::send_json(['success' => false, 'error' => __('Только POST.', 'copybara-ai-integration')], 405);
            }

            $key = self::get_api_key_from_request();
            if (empty($key)) {
                self::send_json(['success' => false, 'error' => __('API ключ не указан. Используйте заголовок X-API-Key (рекомендуется).', 'copybara-ai-integration')], 401);
            }
            if (!Cbai_Settings::validate_api_key($key)) {
                self::send_json(['success' => false, 'error' => __('Неверный API ключ.', 'copybara-ai-integration')], 403);
            }

            $body = file_get_contents('php://input');
            $data = json_decode($body, true);
            if (!is_array($data)) {
                $hint = empty($body) ? __('Тело запроса пустое. Отправьте JSON с title и content.', 'copybara-ai-integration') : __('Некорректный JSON в теле запроса.', 'copybara-ai-integration');
                self::send_json(['success' => false, 'error' => $hint], 400);
            }

            $result = Cbai_Post_Creator::create($data);
            if (!$result['success']) {
                self::send_json($result, 400);
            }

            self::send_json($result, !empty($result['updated']) ? 200 : 201);
        } catch (Exception $e) {
            self::send_json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    protected static function get_api_key_from_request() {
        if (isset($_SERVER['HTTP_X_API_KEY'])) {
            return sanitize_text_field(wp_unslash($_SERVER['HTTP_X_API_KEY']));
        }
        $auth_raw = '';
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth_raw = sanitize_text_field(wp_unslash($_SERVER['HTTP_AUTHORIZATION']));
        } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $auth_raw = sanitize_text_field(wp_unslash($_SERVER['REDIRECT_HTTP_AUTHORIZATION']));
        }
        $auth_raw = is_string($auth_raw) ? $auth_raw : '';
        if (preg_match('/^Bearer\s+(.+)$/i', $auth_raw, $m)) {
            return sanitize_text_field(trim($m[1]));
        }
        return '';
    }

    protected static function send_json($data, $status = 200) {
        status_header($status);
        header('Content-Type: application/json; charset=' . get_option('blog_charset'));
        echo wp_json_encode($data);
        exit;
    }
}
