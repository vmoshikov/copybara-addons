<?php
/**
 * Admin settings: copybara.ai integration.
 *
 * @package Cbai
 * @license GPL-2.0-or-later
 */

defined('ABSPATH') || exit;

class Cbai_Settings {

    const OPTION_GROUP = 'cbai_settings';
    const OPTION_NAME = 'cbai_options';
    const API_KEY_OPTION = 'cbai_api_key';

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_menu_page']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('admin_init', [__CLASS__, 'handle_regenerate_key']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_settings_script']);
    }

    /**
     * Clipboard helpers on the settings screen only (replaces inline &lt;script&gt;).
     */
    public static function enqueue_settings_script($hook_suffix) {
        if ('settings_page_' . CBAI_SLUG !== $hook_suffix) {
            return;
        }
        wp_register_script('cbai-settings-clipboard', false, [], CBAI_VERSION, true);
        wp_enqueue_script('cbai-settings-clipboard');
        $copied = esc_js(__('Скопировано!', 'copybara-ai-integration'));
        $js     = "(function () {\n  function bind(btnId, textId) {\n    var btn = document.getElementById(btnId);\n    var el = document.getElementById(textId);\n    if (!btn || !el) return;\n    btn.addEventListener('click', function () {\n      var orig = btn.textContent;\n      navigator.clipboard.writeText(el.textContent).then(function () {\n        btn.textContent = '{$copied}';\n        setTimeout(function () { btn.textContent = orig; }, 2000);\n      });\n    });\n  }\n  bind('cbai-copy-key', 'cbai-api-key');\n  bind('cbai-copy-endpoint', 'cbai-endpoint-url');\n})();";
        wp_add_inline_script('cbai-settings-clipboard', $js);
    }

    public static function add_menu_page() {
        add_options_page(
            __('copybara.ai Integration', 'copybara-ai-integration'),
            __('copybara.ai', 'copybara-ai-integration'),
            'manage_options',
            CBAI_SLUG,
            [__CLASS__, 'render_settings_page']
        );
    }

    public static function register_settings() {
        register_setting(self::OPTION_GROUP, self::OPTION_NAME, [
            'type'              => 'object',
            'sanitize_callback' => [__CLASS__, 'sanitize_options'],
        ]);

        add_settings_section(
            'cbai_main',
            __('Integration settings', 'copybara-ai-integration'),
            [__CLASS__, 'render_section'],
            CBAI_SLUG
        );

        add_settings_field(
            'post_type',
            __('Post type', 'copybara-ai-integration'),
            [__CLASS__, 'render_post_type_field'],
            CBAI_SLUG,
            'cbai_main',
            ['label_for' => 'cbai_post_type']
        );

        add_settings_field(
            'default_status',
            __('Default post status', 'copybara-ai-integration'),
            [__CLASS__, 'render_status_field'],
            CBAI_SLUG,
            'cbai_main',
            ['label_for' => 'cbai_default_status']
        );

        add_settings_field(
            'default_author',
            __('Default author', 'copybara-ai-integration'),
            [__CLASS__, 'render_author_field'],
            CBAI_SLUG,
            'cbai_main',
            ['label_for' => 'cbai_default_author']
        );

        add_settings_field(
            'image_placement',
            __('Image placement', 'copybara-ai-integration'),
            [__CLASS__, 'render_image_placement_field'],
            CBAI_SLUG,
            'cbai_main',
            ['label_for' => 'cbai_image_placement']
        );
    }

    public static function sanitize_options($input) {
        $sanitized                 = [];
        $allowed_types             = array_keys(self::get_available_post_types());
        $sanitized['post_type']    = isset($input['post_type']) && in_array($input['post_type'], $allowed_types, true)
            ? $input['post_type']
            : 'post';
        $sanitized['default_status'] = isset($input['default_status']) && in_array($input['default_status'], ['draft', 'publish'], true)
            ? $input['default_status']
            : 'draft';
        $sanitized['default_author'] = isset($input['default_author']) ? absint($input['default_author']) : get_current_user_id();
        $sanitized['image_placement'] = isset($input['image_placement']) && in_array($input['image_placement'], ['featured', 'body', 'both'], true)
            ? $input['image_placement']
            : 'featured';
        if (!get_option(self::API_KEY_OPTION)) {
            self::regenerate_api_key();
        }
        return $sanitized;
    }

    public static function render_section() {
        echo '<p>' . esc_html__('Настройте, как сервис copybara.ai создаёт записи на сайте.', 'copybara-ai-integration') . '</p>';
        echo '<p class="description" style="margin-top:6px;">' . esc_html__(
            'Official WordPress integration for copybara.ai — receive content from your copybara.ai account.',
            'copybara-ai-integration'
        ) . '</p>';
    }

    public static function get_available_post_types() {
        $types  = get_post_types(['public' => true], 'objects');
        $result = [];
        foreach ($types as $slug => $obj) {
            $result[$slug] = $obj->labels->singular_name ?: $slug;
        }
        return $result;
    }

    public static function render_post_type_field() {
        $options   = self::get_options();
        $post_type = $options['post_type'] ?? 'post';
        $types     = self::get_available_post_types();
        ?>
        <select id="cbai_post_type" name="<?php echo esc_attr(self::OPTION_NAME); ?>[post_type]">
            <?php foreach ($types as $slug => $label) : ?>
                <option value="<?php echo esc_attr($slug); ?>" <?php selected($post_type, $slug); ?>><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
        <p class="description"><?php esc_html_e('Тип контента, в который будут создаваться записи (записи, страницы или кастомные типы).', 'copybara-ai-integration'); ?></p>
        <?php
    }

    public static function render_status_field() {
        $options = self::get_options();
        $status  = $options['default_status'] ?? 'draft';
        ?>
        <select id="cbai_default_status" name="<?php echo esc_attr(self::OPTION_NAME); ?>[default_status]">
            <option value="draft" <?php selected($status, 'draft'); ?>><?php esc_html_e('Черновик', 'copybara-ai-integration'); ?></option>
            <option value="publish" <?php selected($status, 'publish'); ?>><?php esc_html_e('Опубликовано', 'copybara-ai-integration'); ?></option>
        </select>
        <p class="description"><?php esc_html_e('В каком статусе создавать записи по умолчанию.', 'copybara-ai-integration'); ?></p>
        <?php
    }

    public static function render_author_field() {
        $options   = self::get_options();
        $author_id = $options['default_author'] ?? get_current_user_id();
        wp_dropdown_users([
            'name'     => self::OPTION_NAME . '[default_author]',
            'id'       => 'cbai_default_author',
            'selected' => $author_id,
            'who'      => 'authors',
        ]);
        ?>
        <p class="description"><?php esc_html_e('Пользователь, от имени которого будут создаваться записи.', 'copybara-ai-integration'); ?></p>
        <?php
    }

    public static function render_image_placement_field() {
        $options   = self::get_options();
        $placement = $options['image_placement'] ?? 'featured';
        ?>
        <select id="cbai_image_placement" name="<?php echo esc_attr(self::OPTION_NAME); ?>[image_placement]">
            <option value="featured" <?php selected($placement, 'featured'); ?>><?php esc_html_e('Обложка (Featured Image)', 'copybara-ai-integration'); ?></option>
            <option value="body" <?php selected($placement, 'body'); ?>><?php esc_html_e('В тело записи', 'copybara-ai-integration'); ?></option>
            <option value="both" <?php selected($placement, 'both'); ?>><?php esc_html_e('Обложка и тело (оба)', 'copybara-ai-integration'); ?></option>
        </select>
        <p class="description"><?php esc_html_e('Куда помещать изображение из Copybara по умолчанию. Запрос может переопределить. Режим «оба» — только при явном выборе (иначе фото не дублируется).', 'copybara-ai-integration'); ?></p>
        <?php
    }

    public static function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $api_key  = self::get_api_key();
        $options  = self::get_options();
        $rest_url = rest_url('copybara-ai/v1/posts');
        $ajax_url = Cbai_Ajax_Handler::get_url();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div class="card" style="max-width: 600px; margin: 20px 0; padding: 20px;">
                <h2><?php esc_html_e('API ключ', 'copybara-ai-integration'); ?></h2>
                <p><?php esc_html_e('Используйте этот ключ в Copybara для авторизации при отправке контента.', 'copybara-ai-integration'); ?></p>
                <?php if ($api_key) : ?>
                    <p>
                        <code id="cbai-api-key" style="display: block; padding: 10px; background: #f0f0f1; word-break: break-all;"><?php echo esc_html($api_key); ?></code>
                        <button type="button" class="button button-secondary" id="cbai-copy-key"><?php esc_html_e('Копировать', 'copybara-ai-integration'); ?></button>
                    </p>
                    <form method="post" action="" style="margin-top: 10px;">
                        <?php wp_nonce_field('cbai_regenerate_key', 'cbai_regenerate_nonce'); ?>
                        <input type="hidden" name="cbai_action" value="regenerate_key" />
                        <button type="submit" class="button" onclick="return confirm('<?php echo esc_js(__('Сгенерировать новый ключ? Старый перестанет работать.', 'copybara-ai-integration')); ?>');">
                            <?php esc_html_e('Сгенерировать новый ключ', 'copybara-ai-integration'); ?>
                        </button>
                    </form>
                <?php else : ?>
                    <p><strong><?php esc_html_e('Ключ не создан. Сохраните настройки ниже.', 'copybara-ai-integration'); ?></strong></p>
                <?php endif; ?>
            </div>

            <div class="card" style="max-width: 600px; margin: 20px 0; padding: 20px;">
                <h2><?php esc_html_e('URL для Copybara', 'copybara-ai-integration'); ?></h2>
                <p><?php esc_html_e('Скопируйте этот URL и вставьте в настройках Copybara (Настройки → Публикация → На сайте).', 'copybara-ai-integration'); ?></p>
                <p>
                    <code id="cbai-endpoint-url" style="display: block; padding: 10px; background: #f0f0f1; word-break: break-all;"><?php echo esc_html($ajax_url); ?></code>
                    <button type="button" class="button button-secondary" id="cbai-copy-endpoint"><?php esc_html_e('Копировать URL', 'copybara-ai-integration'); ?></button>
                </p>
                <p class="description"><?php esc_html_e('Рекомендуется admin-ajax — работает даже при отключённом REST API. Альтернатива: REST API', 'copybara-ai-integration'); ?> <code><?php echo esc_html($rest_url); ?></code></p>
            </div>

            <div class="card" style="max-width: 600px; margin: 20px 0; padding: 20px;">
                <h2><?php esc_html_e('Отправка изображений', 'copybara-ai-integration'); ?></h2>
                <p><?php esc_html_e('Изображение передаётся в поле featured_image в теле JSON-запроса. Поддерживаются два способа:', 'copybara-ai-integration'); ?></p>
                <ol style="list-style: decimal; margin-left: 20px;">
                    <li><strong><?php esc_html_e('По URL:', 'copybara-ai-integration'); ?></strong> <code>{"featured_image": {"url": "https://example.com/image.jpg"}}</code> — WordPress скачает файл по ссылке и загрузит в медиабиблиотеку.</li>
                    <li><strong><?php esc_html_e('Base64:', 'copybara-ai-integration'); ?></strong> <code>{"featured_image": {"data": "base64...", "mime": "image/jpeg", "filename": "cover.jpg"}}</code> — бинарные данные в base64 (удобно, если изображение уже в Copybara).</li>
                </ol>
                <p><?php esc_html_e('Расположение задаётся полем image_placement (featured / body / both) или настройкой ниже. По умолчанию — только обложка, без дубля в теле.', 'copybara-ai-integration'); ?></p>
            </div>

            <div class="card" style="max-width: 600px; margin: 20px 0; padding: 20px;">
                <h2><?php esc_html_e('AI (WordPress 7+)', 'copybara-ai-integration'); ?></h2>
                <p><?php esc_html_e('Copybara может выступать AI-провайдером сайта (посредник OpenRouter). Добавьте API-ключ аккаунта Copybara (cbk_live_…) в Настройки → Connectors. Токены списываются с баланса Copybara. Ключ плагина (cb_…) нужен только для входящей публикации.', 'copybara-ai-integration'); ?></p>
            </div>

            <form action="options.php" method="post">
                <?php
                settings_fields(self::OPTION_GROUP);
                do_settings_sections(CBAI_SLUG);
                submit_button(__('Сохранить настройки', 'copybara-ai-integration'));
                ?>
            </form>
        </div>
        <?php
    }

    public static function handle_regenerate_key() {
        if (!isset($_POST['cbai_action'])) {
            return;
        }
        $action = sanitize_text_field(wp_unslash($_POST['cbai_action']));
        if ('regenerate_key' !== $action) {
            return;
        }
        if (!current_user_can('manage_options')) {
            return;
        }
        if (!isset($_POST['cbai_regenerate_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cbai_regenerate_nonce'])), 'cbai_regenerate_key')) {
            return;
        }
        self::regenerate_api_key();
        wp_safe_redirect(add_query_arg('settings-updated', '1', admin_url('options-general.php?page=' . rawurlencode(CBAI_SLUG))));
        exit;
    }

    /**
     * Data from earlier plugin folders / option keys.
     */
    public static function migrate_from_legacy() {
        if (!get_option(self::API_KEY_OPTION)) {
            foreach (['vmcrc_api_key', 'copybara_ai_api_key', 'copybara_receiver_api_key'] as $legacy) {
                $v = get_option($legacy, '');
                if ($v) {
                    update_option(self::API_KEY_OPTION, $v);
                    break;
                }
            }
        }
        if (get_option(self::OPTION_NAME) === false) {
            if (get_option('vmcrc_options') !== false) {
                update_option(self::OPTION_NAME, get_option('vmcrc_options'));
            } elseif (get_option('copybara_ai_options') !== false) {
                update_option(self::OPTION_NAME, get_option('copybara_ai_options'));
            } elseif (get_option('copybara_receiver_options') !== false) {
                update_option(self::OPTION_NAME, get_option('copybara_receiver_options'));
            }
        }
    }

    public static function maybe_create_api_key() {
        if (!get_option(self::API_KEY_OPTION)) {
            self::regenerate_api_key();
        }
    }

    public static function regenerate_api_key() {
        $key = 'cb_' . bin2hex(random_bytes(32));
        update_option(self::API_KEY_OPTION, $key);
        return $key;
    }

    public static function get_api_key() {
        return get_option(self::API_KEY_OPTION, '');
    }

    public static function get_options() {
        $defaults = [
            'post_type'        => 'post',
            'default_status'   => 'draft',
            'default_author'   => get_current_user_id(),
            'image_placement'  => 'featured',
        ];
        $opts = get_option(self::OPTION_NAME, []);
        return wp_parse_args(is_array($opts) ? $opts : [], $defaults);
    }

    public static function validate_api_key($key) {
        $stored = self::get_api_key();
        return $stored && hash_equals($stored, $key);
    }
}
