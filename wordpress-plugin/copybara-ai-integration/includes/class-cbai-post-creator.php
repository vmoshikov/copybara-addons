<?php
/**
 * Shared post create/update logic for REST and admin-ajax endpoints.
 *
 * @package Cbai
 * @license GPL-2.0-or-later
 */

defined('ABSPATH') || exit;

class Cbai_Post_Creator {

    const META_COPYBARA_ID = '_cbai_copybara_id';
    const COVER_ATTR       = 'data-cbai-cover';

    /**
     * Sanitize request payload after JSON decode (admin-ajax, etc.).
     *
     * @param array $raw Decoded array from json_decode( ..., true ).
     * @return array
     */
    protected static function prepare_incoming_data(array $raw) {
        $out = [];
        if (isset($raw['title']) && is_string($raw['title'])) {
            $out['title'] = sanitize_text_field($raw['title']);
        }
        if (isset($raw['content']) && is_string($raw['content'])) {
            $out['content'] = $raw['content'];
        }
        if (isset($raw['content_type']) && is_string($raw['content_type'])) {
            $ct = sanitize_key($raw['content_type']);
            if (in_array($ct, ['html', 'markdown'], true)) {
                $out['content_type'] = $ct;
            }
        }
        if (isset($raw['status']) && is_string($raw['status'])) {
            $st = sanitize_key($raw['status']);
            if (in_array($st, ['draft', 'publish'], true)) {
                $out['status'] = $st;
            }
        }
        if (isset($raw['post_type']) && is_string($raw['post_type']) && $raw['post_type'] !== '') {
            $out['post_type'] = sanitize_key($raw['post_type']);
        }
        if (isset($raw['excerpt']) && is_string($raw['excerpt'])) {
            $out['excerpt'] = sanitize_textarea_field($raw['excerpt']);
        }
        if (isset($raw['categories']) && is_array($raw['categories'])) {
            $cats = array_map('absint', $raw['categories']);
            $out['categories'] = array_values(array_filter($cats));
        }
        if (isset($raw['tags']) && is_array($raw['tags'])) {
            $tags = [];
            foreach ($raw['tags'] as $t) {
                if (is_string($t) && $t !== '') {
                    $tags[] = sanitize_text_field($t);
                }
            }
            $out['tags'] = $tags;
        }
        if (isset($raw['featured_image']) && is_array($raw['featured_image'])) {
            $fi = $raw['featured_image'];
            $img = [];
            if (isset($fi['url']) && is_string($fi['url']) && $fi['url'] !== '') {
                $img['url'] = esc_url_raw($fi['url']);
            }
            if (isset($fi['data']) && is_string($fi['data']) && $fi['data'] !== '') {
                $img['data'] = $fi['data'];
            }
            if (isset($fi['mime']) && is_string($fi['mime'])) {
                $img['mime'] = sanitize_text_field($fi['mime']);
            }
            if (isset($fi['filename']) && is_string($fi['filename'])) {
                $img['filename'] = sanitize_file_name($fi['filename']);
            }
            if (!empty($img)) {
                $out['featured_image'] = $img;
            }
        }
        if (isset($raw['image_placement']) && is_string($raw['image_placement'])) {
            $placement = sanitize_key($raw['image_placement']);
            if (in_array($placement, ['featured', 'body', 'both'], true)) {
                $out['image_placement'] = $placement;
            }
        }
        if (isset($raw['copybara_id']) && is_string($raw['copybara_id']) && $raw['copybara_id'] !== '') {
            $out['copybara_id'] = sanitize_text_field($raw['copybara_id']);
        }
        return $out;
    }

    /**
     * Resolve image placement: request → plugin option → featured.
     *
     * @param array $data Prepared data.
     * @return string featured|body|both
     */
    protected static function resolve_image_placement(array $data) {
        if (!empty($data['image_placement'])) {
            return $data['image_placement'];
        }
        $options = Cbai_Settings::get_options();
        $from_opts = $options['image_placement'] ?? 'featured';
        if (in_array($from_opts, ['featured', 'body', 'both'], true)) {
            return $from_opts;
        }
        return 'featured';
    }

    /**
     * Find existing post by Copybara material UUID meta.
     *
     * @param string $copybara_id Material UUID.
     * @return int Post ID or 0.
     */
    protected static function find_post_by_copybara_id($copybara_id) {
        if ($copybara_id === '') {
            return 0;
        }
        $q = new WP_Query([
            'post_type'              => 'any',
            'post_status'            => 'any',
            'posts_per_page'         => 1,
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'meta_key'               => self::META_COPYBARA_ID,
            'meta_value'             => $copybara_id,
        ]);
        if (!empty($q->posts[0])) {
            return (int) $q->posts[0];
        }
        return 0;
    }

    /**
     * Strip previously injected Copybara cover markup from content.
     *
     * @param string $content HTML content.
     * @return string
     */
    protected static function strip_cbai_cover($content) {
        if ($content === '' || strpos($content, self::COVER_ATTR) === false) {
            return $content;
        }
        // Remove <p>…</p> or <figure>…</figure> blocks that contain data-cbai-cover.
        $pattern = '/<(?:p|figure)\b[^>]*>\s*(?:<(?:a|span)\b[^>]*>\s*)?<img\b[^>]*\b' . preg_quote(self::COVER_ATTR, '/') . '\b[^>]*>\s*(?:<\/(?:a|span)>\s*)?<\/(?:p|figure)>\s*/iu';
        $stripped = preg_replace($pattern, '', $content);
        if (!is_string($stripped)) {
            return $content;
        }
        // Fallback: strip bare <img data-cbai-cover …>.
        $stripped = preg_replace('/<img\b[^>]*\b' . preg_quote(self::COVER_ATTR, '/') . '\b[^>]*>\s*/iu', '', $stripped);
        return is_string($stripped) ? $stripped : $content;
    }

    /**
     * Build cover HTML for body placement (local media URL).
     *
     * @param int    $attachment_id Attachment ID.
     * @param string $alt           Alt text.
     * @return string
     */
    protected static function build_cover_html($attachment_id, $alt = '') {
        $url = wp_get_attachment_url($attachment_id);
        if (!$url) {
            return '';
        }
        $alt_attr = esc_attr($alt);
        $url_attr = esc_url($url);
        $attr     = self::COVER_ATTR;
        return '<figure class="cbai-cover"><img src="' . $url_attr . '" alt="' . $alt_attr . '" ' . $attr . '="1" /></figure>';
    }

    /**
     * Create or update post from parsed data array.
     *
     * @param array $data Keys: title, content, content_type, status, post_type, excerpt, categories, tags, featured_image, image_placement, copybara_id
     * @return array{success: bool, id?: int, url?: string, edit_url?: string, updated?: bool, error?: string}
     */
    public static function create($data) {
        $data = self::prepare_incoming_data(is_array($data) ? $data : []);
        $options = Cbai_Settings::get_options();
        $status = !empty($data['status']) ? $data['status'] : ($options['default_status'] ?? 'draft');
        $author_id = $options['default_author'] ?? get_current_user_id();
        $post_type = !empty($data['post_type']) ? $data['post_type'] : ($options['post_type'] ?? 'post');
        $placement = self::resolve_image_placement($data);
        $copybara_id = isset($data['copybara_id']) ? $data['copybara_id'] : '';

        $allowed_types = array_keys(Cbai_Settings::get_available_post_types());
        if (!in_array($post_type, $allowed_types, true)) {
            $post_type = 'post';
        }

        $title = isset($data['title']) ? $data['title'] : '';
        $content = isset($data['content']) ? $data['content'] : '';
        $content_type = !empty($data['content_type']) ? $data['content_type'] : 'html';

        if ($title === '' || $content === '') {
            return ['success' => false, 'error' => __('Title and content are required.', 'copybara-ai-integration')];
        }

        if ($content_type === 'markdown') {
            if (function_exists('wpmarkdown_markdown_to_html')) {
                $content = wpmarkdown_markdown_to_html($content);
            } else {
                $content = wp_kses_post($content);
            }
        } else {
            $content = wp_kses_post($content);
        }

        // Never leave previous cover inject when re-publishing.
        $content = self::strip_cbai_cover($content);

        $existing_id = self::find_post_by_copybara_id($copybara_id);
        $is_update   = $existing_id > 0;

        $post_data = [
            'post_title'   => $title,
            'post_content' => $content,
            'post_status'  => in_array($status, ['draft', 'publish'], true) ? $status : 'draft',
            'post_type'    => $post_type,
        ];

        $excerpt = isset($data['excerpt']) ? $data['excerpt'] : '';
        if (is_string($excerpt) && $excerpt !== '') {
            $post_data['post_excerpt'] = sanitize_textarea_field($excerpt);
        }

        if ($is_update) {
            $post_data['ID'] = $existing_id;
            // Keep existing author on update unless defaults force a change via request later.
            $post_id = wp_update_post($post_data, true);
        } else {
            $post_data['post_author'] = $author_id;
            $post_id = wp_insert_post($post_data, true);
        }

        if (is_wp_error($post_id)) {
            return ['success' => false, 'error' => $post_id->get_error_message()];
        }
        $post_id = (int) $post_id;

        if ($copybara_id !== '') {
            update_post_meta($post_id, self::META_COPYBARA_ID, $copybara_id);
        }

        $saved_type = get_post_type($post_id);
        if ($saved_type === 'post') {
            $categories = isset($data['categories']) && is_array($data['categories']) ? $data['categories'] : [];
            if (!empty($categories)) {
                wp_set_post_categories($post_id, array_map('absint', $categories));
            }
            $tags = isset($data['tags']) && is_array($data['tags']) ? $data['tags'] : [];
            if (!empty($tags)) {
                wp_set_post_tags($post_id, $tags);
            }
        }

        $featured_image = isset($data['featured_image']) && is_array($data['featured_image']) ? $data['featured_image'] : null;
        if ($featured_image) {
            $attachment_id = self::handle_featured_image($post_id, $featured_image);
            if ($attachment_id && !is_wp_error($attachment_id)) {
                $alt = '';
                if (!empty($featured_image['filename'])) {
                    $alt = pathinfo($featured_image['filename'], PATHINFO_FILENAME);
                }
                self::apply_image_placement($post_id, (int) $attachment_id, $placement, $alt);
            }
        } elseif ($is_update) {
            // No new image: still clean placement artifacts when switching modes via settings/request.
            self::clear_placement_artifacts($post_id, $placement);
        }

        return [
            'success'  => true,
            'id'       => $post_id,
            'url'      => get_permalink($post_id),
            'edit_url' => get_edit_post_link($post_id, 'raw'),
            'updated'  => $is_update,
        ];
    }

    /**
     * Apply featured / body / both placement without unwanted duplication.
     *
     * @param int    $post_id       Post ID.
     * @param int    $attachment_id Attachment ID.
     * @param string $placement     featured|body|both.
     * @param string $alt           Alt text.
     */
    protected static function apply_image_placement($post_id, $attachment_id, $placement, $alt = '') {
        $post = get_post($post_id);
        if (!$post) {
            return;
        }
        $content = self::strip_cbai_cover($post->post_content);

        $use_featured = in_array($placement, ['featured', 'both'], true);
        $use_body     = in_array($placement, ['body', 'both'], true);

        if ($use_featured) {
            set_post_thumbnail($post_id, $attachment_id);
        } else {
            delete_post_thumbnail($post_id);
        }

        if ($use_body) {
            $cover_html = self::build_cover_html($attachment_id, $alt);
            if ($cover_html !== '') {
                $content = $cover_html . "\n" . $content;
            }
        }

        wp_update_post([
            'ID'           => $post_id,
            'post_content' => $content,
        ]);
    }

    /**
     * When no new image is sent on update, still remove wrong-mode artifacts.
     *
     * @param int    $post_id   Post ID.
     * @param string $placement Target placement.
     */
    protected static function clear_placement_artifacts($post_id, $placement) {
        $post = get_post($post_id);
        if (!$post) {
            return;
        }
        $content = self::strip_cbai_cover($post->post_content);
        $changed = ($content !== $post->post_content);

        if ($placement === 'body') {
            delete_post_thumbnail($post_id);
        }

        // featured-only: ensure no leftover cover markup (already stripped above).
        if ($changed) {
            wp_update_post([
                'ID'           => $post_id,
                'post_content' => $content,
            ]);
        }
    }

    protected static function handle_featured_image($post_id, $image) {
        if (isset($image['url']) && is_string($image['url']) && $image['url'] !== '') {
            return self::sideload_from_url($image['url'], $post_id);
        }
        if (isset($image['data']) && is_string($image['data']) && $image['data'] !== '') {
            $mime = isset($image['mime']) ? $image['mime'] : 'image/jpeg';
            $filename = isset($image['filename']) ? $image['filename'] : 'image.' . self::mime_to_ext($mime);
            return self::upload_from_base64($image['data'], $post_id, $filename, $mime);
        }
        return null;
    }

    protected static function sideload_from_url($url, $post_id) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $tmp = download_url($url);
        if (is_wp_error($tmp)) {
            return $tmp;
        }
        $path = wp_parse_url($url, PHP_URL_PATH);
        $basename = (is_string($path) && $path !== '') ? basename($path) : 'image.jpg';
        $file_array = [
            'name' => $basename ?: 'image.jpg',
            'tmp_name' => $tmp,
        ];
        $id = media_handle_sideload($file_array, $post_id);
        if (is_wp_error($id)) {
            wp_delete_file($tmp);
            return $id;
        }
        return $id;
    }

    protected static function upload_from_base64($data, $post_id, $filename, $mime) {
        $decoded = base64_decode($data, true);
        if ($decoded === false) {
            return new WP_Error('copybara_invalid_image', __('Invalid base64 image data.', 'copybara-ai-integration'));
        }

        $upload = wp_upload_bits($filename, null, $decoded);
        if (!empty($upload['error'])) {
            return new WP_Error('copybara_upload_failed', $upload['error']);
        }

        $attachment = [
            'post_mime_type' => $mime,
            'post_title' => sanitize_file_name(pathinfo($filename, PATHINFO_FILENAME)),
            'post_content' => '',
            'post_status' => 'inherit',
        ];
        $attachment_id = wp_insert_attachment($attachment, $upload['file'], $post_id);
        if (is_wp_error($attachment_id)) {
            wp_delete_file($upload['file']);
            return $attachment_id;
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        $meta = wp_generate_attachment_metadata($attachment_id, $upload['file']);
        if (!empty($meta)) {
            wp_update_attachment_metadata($attachment_id, $meta);
        }

        return $attachment_id;
    }

    protected static function mime_to_ext($mime) {
        $map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];
        return $map[$mime] ?? 'jpg';
    }
}
