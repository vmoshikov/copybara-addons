<?php
/**
 * REST API controller for receiving posts from Copybara.
 *
 * @package Cbai
 * @license GPL-2.0-or-later
 */

defined('ABSPATH') || exit;

class Cbai_REST_Controller extends WP_REST_Controller {

    protected $namespace = 'copybara-ai/v1';

    public function register_routes() {
        $route = [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_post'],
                'permission_callback' => [$this, 'check_api_key'],
                'args' => $this->get_collection_params(),
            ],
        ];
        register_rest_route($this->namespace, '/posts', $route);
        // Legacy namespace (same behaviour) for existing Copybara URLs.
        register_rest_route('copybara/v1', '/posts', $route);
    }

    /**
     * Validate API key from X-API-Key header or Authorization: Bearer.
     */
    public function check_api_key($request) {
        $key = sanitize_text_field((string) $request->get_header('X-API-Key'));
        if ($key === '') {
            $auth = $request->get_header('Authorization');
            if (preg_match('/^Bearer\s+(.+)$/i', $auth ?: '', $m)) {
                $key = sanitize_text_field(trim($m[1]));
            }
        }
        if (empty($key)) {
            return new WP_Error('copybara_missing_key', __('API ключ не указан. Используйте заголовок X-API-Key или Authorization: Bearer.', 'copybara-ai-integration'), ['status' => 401]);
        }
        if (!Cbai_Settings::validate_api_key($key)) {
            return new WP_Error('copybara_invalid_key', __('Неверный API ключ.', 'copybara-ai-integration'), ['status' => 403]);
        }
        return true;
    }

    public function get_collection_params() {
        return [
            'title' => [
                'required' => true,
                'type' => 'string',
                'description' => __('Заголовок записи', 'copybara-ai-integration'),
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'content' => [
                'required' => true,
                'type' => 'string',
                'description' => __('Содержимое записи (HTML или Markdown)', 'copybara-ai-integration'),
            ],
            'content_type' => [
                'type' => 'string',
                'default' => 'html',
                'enum' => ['html', 'markdown'],
                'description' => __('Формат контента', 'copybara-ai-integration'),
            ],
            'status' => [
                'type' => 'string',
                'enum' => ['draft', 'publish'],
                'description' => __('Переопределить статус (опционально)', 'copybara-ai-integration'),
            ],
            'post_type' => [
                'type' => 'string',
                'description' => __('Переопределить тип записи (опционально)', 'copybara-ai-integration'),
            ],
            'featured_image' => [
                'type' => 'object',
                'description' => __('Изображение для записи. url — URL изображения, или data — base64 + mime', 'copybara-ai-integration'),
                'properties' => [
                    'url' => ['type' => 'string', 'format' => 'uri'],
                    'data' => ['type' => 'string', 'description' => 'Base64-encoded image data'],
                    'mime' => ['type' => 'string', 'description' => 'MIME type when using data (e.g. image/jpeg)'],
                    'filename' => ['type' => 'string', 'description' => 'Filename for the attachment'],
                ],
            ],
            'image_placement' => [
                'type' => 'string',
                'enum' => ['featured', 'body', 'both'],
                'description' => __('Куда разместить изображение: обложка, тело записи или оба (явный opt-in)', 'copybara-ai-integration'),
            ],
            'copybara_id' => [
                'type' => 'string',
                'description' => __('UUID материала Copybara для upsert (обновление существующей записи)', 'copybara-ai-integration'),
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'excerpt' => [
                'type' => 'string',
                'description' => __('Краткое описание (excerpt)', 'copybara-ai-integration'),
            ],
            'categories' => [
                'type' => 'array',
                'items' => ['type' => 'integer'],
                'description' => __('ID категорий', 'copybara-ai-integration'),
            ],
            'tags' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => __('Теги (имена)', 'copybara-ai-integration'),
            ],
        ];
    }

    /**
     * Create or update a post from Copybara request.
     */
    public function create_post($request) {
        $data = [
            'title' => $request->get_param('title'),
            'content' => $request->get_param('content'),
            'content_type' => $request->get_param('content_type'),
            'status' => $request->get_param('status'),
            'post_type' => $request->get_param('post_type'),
            'excerpt' => $request->get_param('excerpt'),
            'categories' => $request->get_param('categories'),
            'tags' => $request->get_param('tags'),
            'featured_image' => $request->get_param('featured_image'),
            'image_placement' => $request->get_param('image_placement'),
            'copybara_id' => $request->get_param('copybara_id'),
        ];

        $result = Cbai_Post_Creator::create($data);
        if (!$result['success']) {
            return new WP_REST_Response($result, 500);
        }
        $code = !empty($result['updated']) ? 200 : 201;
        return new WP_REST_Response($result, $code);
    }
}
