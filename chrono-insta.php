<?php
/**
 * Plugin Name: ChronoInsta
 * Description: Muestra las ultimas imagenes publicas de un perfil de Instagram mediante un shortcode y un endpoint JSON opcional.
 * Version: 1.7.0
 * Author: Andres Tobon
 * Text Domain: chrono-insta
 * Requires at least: 5.5
 * Requires PHP: 7.4
 * License: GPLv2 or later
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Chrono_Insta {
    const VERSION = '1.7.0';
    const OPTION_KEY = 'chrono_insta_settings';
    const CACHE_PREFIX = 'chrono_insta_feed_';
    const CACHE_INDEX_OPTION = 'chrono_insta_cache_index';
    const DEFAULT_USERNAME = 'sonidosancestrales8';
    const DEFAULT_LIMIT = 9;
    const MIN_LIMIT = 1;
    const MAX_LIMIT = 12;

    public static function init() {
        add_action('plugins_loaded', array(__CLASS__, 'load_textdomain'));
        add_action('admin_init', array(__CLASS__, 'handle_manual_actions'));
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_action('admin_menu', array(__CLASS__, 'register_menu'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
        add_action('rest_api_init', array(__CLASS__, 'register_rest_route'));
        add_shortcode('chrono_insta_feed', array(__CLASS__, 'render_shortcode'));
    }

    public static function load_textdomain() {
        load_plugin_textdomain('chrono-insta', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public static function register_settings() {
        register_setting(
            'chrono_insta',
            self::OPTION_KEY,
            array(
                'sanitize_callback' => array(__CLASS__, 'sanitize_settings'),
                'default' => self::get_default_settings(),
            )
        );

        add_settings_section(
            'chrono_insta_general',
            __('Configuracion general', 'chrono-insta'),
            '__return_false',
            'chrono-insta'
        );

        add_settings_field(
            'username',
            __('Usuario de Instagram', 'chrono-insta'),
            array(__CLASS__, 'render_username_field'),
            'chrono-insta',
            'chrono_insta_general'
        );

        add_settings_field(
            'limit',
            __('Limite por shortcode', 'chrono-insta'),
            array(__CLASS__, 'render_limit_field'),
            'chrono-insta',
            'chrono_insta_general'
        );

        add_settings_field(
            'cache_ttl',
            __('Cache (segundos)', 'chrono-insta'),
            array(__CLASS__, 'render_cache_ttl_field'),
            'chrono-insta',
            'chrono_insta_general'
        );
    }

    public static function register_menu() {
        add_options_page(
            __('ChronoInsta', 'chrono-insta'),
            __('ChronoInsta', 'chrono-insta'),
            'manage_options',
            'chrono-insta',
            array(__CLASS__, 'render_settings_page')
        );
    }

    public static function handle_manual_actions() {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (!isset($_POST['chrono_insta_action'])) {
            return;
        }

        $action = sanitize_key(wp_unslash($_POST['chrono_insta_action']));
        if ($action !== 'clear_cache') {
            return;
        }

        check_admin_referer('chrono_insta_clear_cache');

        self::clear_cache();
        add_settings_error(
            'chrono_insta_messages',
            'cache_cleared',
            __('La cache de ChronoInsta ha sido vaciada.', 'chrono-insta'),
            'updated'
        );
    }

    public static function enqueue_assets() {
        wp_enqueue_style(
            'chrono-insta-style',
            plugin_dir_url(__FILE__) . 'assets/css/style.css',
            array(),
            self::VERSION
        );
    }

    public static function render_shortcode($atts) {
        $settings = self::get_settings();

        $atts = shortcode_atts(
            array(
                'username' => $settings['username'],
                'limit' => $settings['limit'],
                'refresh' => false,
            ),
            $atts,
            'chrono_insta_feed'
        );

        $username = self::normalize_username($atts['username']);
        $limit = self::sanitize_limit($atts['limit']);
        $force_refresh = filter_var($atts['refresh'], FILTER_VALIDATE_BOOLEAN);

        if (empty($username)) {
            return '<div class="chrono-insta-feedback is-error">' . esc_html__('No se especifico un usuario valido.', 'chrono-insta') . '</div>';
        }

        $result = self::get_feed_items($username, $limit, $force_refresh);

        if (is_wp_error($result)) {
            return '<div class="chrono-insta-feedback is-error">' . esc_html($result->get_error_message()) . '</div>';
        }

        return self::render_feed_html($username, $result);
    }

    public static function register_rest_route() {
        register_rest_route(
            'chrono-insta/v1',
            '/feed',
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array(__CLASS__, 'rest_feed'),
                'permission_callback' => '__return_true',
                'args' => array(
                    'username' => array(
                        'required' => false,
                        'type' => 'string',
                        'sanitize_callback' => array(__CLASS__, 'normalize_username'),
                    ),
                    'limit' => array(
                        'required' => false,
                        'type' => 'integer',
                        'sanitize_callback' => array(__CLASS__, 'sanitize_limit'),
                    ),
                    'refresh' => array(
                        'required' => false,
                        'type' => 'boolean',
                    ),
                ),
            )
        );
    }

    public static function rest_feed(WP_REST_Request $request) {
        $settings = self::get_settings();
        $username = $request->get_param('username');
        $limit = $request->get_param('limit');
        $refresh = $request->get_param('refresh');

        $username = $username ? self::normalize_username($username) : $settings['username'];
        $limit = $limit ? self::sanitize_limit($limit) : $settings['limit'];
        $force_refresh = (bool) $refresh;

        if (empty($username)) {
            return new WP_Error('invalid_username', __('El parametro username es obligatorio.', 'chrono-insta'), array('status' => 400));
        }

        $result = self::get_feed_items($username, $limit, $force_refresh);

        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response($result);
    }

    public static function get_feed_items($username, $limit, $force_refresh = false) {
        $username = self::normalize_username($username);
        $limit = self::sanitize_limit($limit);

        if (empty($username)) {
            return new WP_Error('invalid_username', __('El usuario de Instagram no es valido.', 'chrono-insta'), array('status' => 400));
        }

        $cache_key = self::get_cache_key($username, $limit);
        $cached = get_transient($cache_key);

        if (!$force_refresh && is_array($cached) && isset($cached['items'])) {
            $cached['from_cache'] = true;
            $cached['username'] = isset($cached['username']) ? $cached['username'] : $username;
            $cached['limit'] = isset($cached['limit']) ? $cached['limit'] : $limit;
            return $cached;
        }

        $remote = self::fetch_remote_feed($username);

        if (is_wp_error($remote)) {
            if (is_array($cached) && isset($cached['items'])) {
                $cached['from_cache'] = true;
                $cached['username'] = isset($cached['username']) ? $cached['username'] : $username;
                $cached['limit'] = isset($cached['limit']) ? $cached['limit'] : $limit;
                $cached['notice'] = sprintf(
                    __('No se pudo actualizar el feed (%s). Mostrando la ultima version almacenada.', 'chrono-insta'),
                    $remote->get_error_message()
                );
                return $cached;
            }

            return $remote;
        }

        $items = self::parse_feed($remote['body'], $username);

        if (empty($items)) {
            if (is_array($cached) && isset($cached['items'])) {
                $cached['from_cache'] = true;
                $cached['username'] = isset($cached['username']) ? $cached['username'] : $username;
                $cached['limit'] = isset($cached['limit']) ? $cached['limit'] : $limit;
                $cached['notice'] = __('Instagram no devolvio imagenes. Mostrando la ultima version almacenada.', 'chrono-insta');
                return $cached;
            }

            return new WP_Error('empty_feed', __('No se encontraron imagenes publicas para este perfil.', 'chrono-insta'), array('status' => 404));
        }

        $items = array_slice($items, 0, $limit);

        $payload = array(
            'items' => $items,
            'from_cache' => false,
            'fetched_at' => $remote['fetched_at'],
            'username' => $username,
            'limit' => $limit,
        );

        $ttl = self::get_cache_ttl();
        set_transient($cache_key, $payload, $ttl);
        self::remember_cache_key($cache_key);

        return $payload;
    }

    private static function fetch_remote_feed($username) {
        $profile_url = sprintf('https://www.instagram.com/%s/', rawurlencode($username));

        $response = wp_remote_get(
            $profile_url,
            array(
                'timeout' => 12,
                'headers' => array(
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept-Language' => 'es-ES,es;q=0.9,en-US;q=0.8,en;q=0.7',
                ),
            )
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return new WP_Error('http_error', sprintf(__('Instagram devolvio el codigo HTTP %d.', 'chrono-insta'), $code), array('status' => 502));
        }

        $body = wp_remote_retrieve_body($response);

        if (!is_string($body) || $body === '') {
            return new WP_Error('empty_body', __('Instagram devolvio una respuesta vacia.', 'chrono-insta'), array('status' => 502));
        }

        return array(
            'body' => $body,
            'fetched_at' => time(),
        );
    }

    private static function parse_feed($body, $username) {
        $items = array();

        $image_pattern = '/"display_url":"([^"]+)"/';
        preg_match_all($image_pattern, $body, $image_matches);

        if (empty($image_matches[1])) {
            return $items;
        }

        $caption_pattern = '/"accessibility_caption":"([^"]*)"/';
        preg_match_all($caption_pattern, $body, $caption_matches);

        foreach ($image_matches[1] as $index => $raw_url) {
            $image_url = self::decode_source_string($raw_url);
            if (empty($image_url)) {
                continue;
            }

            $item = array(
                'image_url' => esc_url_raw($image_url),
                'permalink' => sprintf('https://www.instagram.com/%s/', rawurlencode($username)),
            );

            if (!empty($caption_matches[1][$index])) {
                $decoded_caption = self::decode_source_string($caption_matches[1][$index]);
                if ($decoded_caption) {
                    $item['alt'] = $decoded_caption;
                }
            }

            $items[] = $item;
        }

        return $items;
    }

    private static function render_feed_html($username, array $result) {
        $items = isset($result['items']) ? $result['items'] : array();
        if (empty($items)) {
            return '<div class="chrono-insta-feedback is-error">' . esc_html__('No hay imagenes disponibles en este momento.', 'chrono-insta') . '</div>';
        }

        $notice_markup = '';
        if (!empty($result['notice'])) {
            $notice_markup = '<div class="chrono-insta-feedback is-warning">' . esc_html($result['notice']) . '</div>';
        }

        $cards = '';
        foreach ($items as $item) {
            $image_url = isset($item['image_url']) ? esc_url($item['image_url']) : '';
            if (!$image_url) {
                continue;
            }

            $permalink = isset($item['permalink']) ? esc_url($item['permalink']) : sprintf('https://www.instagram.com/%s/', rawurlencode($username));
            $alt = isset($item['alt']) ? esc_attr($item['alt']) : sprintf(esc_html__('Publicacion de %s', 'chrono-insta'), $username);

            $cards .= '<a class="chrono-insta-card" href="' . $permalink . '" target="_blank" rel="noopener noreferrer">';
            $cards .= '<img src="' . $image_url . '" alt="' . $alt . '" loading="lazy" decoding="async" />';
            $cards .= '</a>';
        }

        if ($cards === '') {
            return '<div class="chrono-insta-feedback is-error">' . esc_html__('No fue posible generar el feed en este momento.', 'chrono-insta') . '</div>';
        }

        $cta = sprintf(
            '<a class="chrono-insta-cta" href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
            esc_url(sprintf('https://www.instagram.com/%s/', rawurlencode($username))),
            esc_html__('Ver perfil en Instagram', 'chrono-insta')
        );

        $markup  = '<div class="chrono-insta-wrapper">';
        $markup .= $notice_markup;
        $markup .= '<div class="chrono-insta-feed">' . $cards . '</div>';
        $markup .= $cta;
        $markup .= '</div>';

        return $markup;
    }

    public static function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = self::get_settings();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('ChronoInsta', 'chrono-insta'); ?></h1>
            <?php settings_errors('chrono_insta_messages'); ?>
            <form action="options.php" method="post">
                <?php
                settings_fields('chrono_insta');
                do_settings_sections('chrono-insta');
                submit_button(__('Guardar cambios', 'chrono-insta'));
                ?>
            </form>
            <form action="" method="post" style="margin-top:16px;">
                <?php wp_nonce_field('chrono_insta_clear_cache'); ?>
                <input type="hidden" name="chrono_insta_action" value="clear_cache" />
                <?php submit_button(__('Vaciar cache', 'chrono-insta'), 'secondary', 'submit', false); ?>
                <p class="description"><?php echo esc_html__('Elimina todas las respuestas almacenadas para forzar una nueva solicitud en la proxima visita.', 'chrono-insta'); ?></p>
            </form>
            <p class="description">
                <?php
                printf(
                    esc_html__('Shortcode por defecto: %s. Usa %s para controlar el numero de imagenes y %s para refrescar el cache bajo demanda.', 'chrono-insta'),
                    '<code>[chrono_insta_feed]</code>',
                    '<code>[chrono_insta_feed limit="6"]</code>',
                    '<code>[chrono_insta_feed refresh="1"]</code>'
                );
                ?>
            </p>
        </div>
        <?php
    }

    public static function render_username_field() {
        $settings = self::get_settings();
        ?>
        <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[username]" value="<?php echo esc_attr($settings['username']); ?>" class="regular-text" />
        <p class="description"><?php esc_html_e('Debe ser un perfil publico. Ejemplo: midestinoartesanal', 'chrono-insta'); ?></p>
        <?php
    }

    public static function render_limit_field() {
        $settings = self::get_settings();
        ?>
        <input type="number" min="<?php echo esc_attr(self::MIN_LIMIT); ?>" max="<?php echo esc_attr(self::MAX_LIMIT); ?>" name="<?php echo esc_attr(self::OPTION_KEY); ?>[limit]" value="<?php echo esc_attr($settings['limit']); ?>" />
        <p class="description"><?php esc_html_e('Numero de imagenes que se muestran cuando no se especifica el parametro limit en el shortcode.', 'chrono-insta'); ?></p>
        <?php
    }

    public static function render_cache_ttl_field() {
        $settings = self::get_settings();
        ?>
        <input type="number" min="60" step="60" name="<?php echo esc_attr(self::OPTION_KEY); ?>[cache_ttl]" value="<?php echo esc_attr($settings['cache_ttl']); ?>" />
        <p class="description"><?php esc_html_e('Tiempo en segundos que se conserva el resultado antes de solicitarlo de nuevo. Valor sugerido: 900 (15 minutos).', 'chrono-insta'); ?></p>
        <?php
    }

    public static function sanitize_settings($input) {
        $sanitized = self::get_default_settings();

        if (isset($input['username'])) {
            $username = self::normalize_username($input['username']);
            if (!empty($username)) {
                $sanitized['username'] = $username;
            }
        }

        if (isset($input['limit'])) {
            $sanitized['limit'] = self::sanitize_limit($input['limit']);
        }

        if (isset($input['cache_ttl'])) {
            $ttl = absint($input['cache_ttl']);
            $sanitized['cache_ttl'] = max(60, $ttl);
        }

        return $sanitized;
    }

    public static function get_settings() {
        $options = get_option(self::OPTION_KEY, array());
        $defaults = self::get_default_settings();

        $settings = wp_parse_args($options, $defaults);
        $settings['limit'] = self::sanitize_limit($settings['limit']);
        $settings['cache_ttl'] = max(60, absint($settings['cache_ttl']));
        $settings['username'] = self::normalize_username($settings['username']);

        return $settings;
    }

    private static function get_default_settings() {
        return array(
            'username' => self::DEFAULT_USERNAME,
            'limit' => self::DEFAULT_LIMIT,
            'cache_ttl' => 900,
        );
    }

    public static function normalize_username($username) {
        $username = is_string($username) ? strtolower($username) : '';
        $username = preg_replace('/[^a-z0-9_.]/', '', $username);

        return trim($username);
    }

    public static function sanitize_limit($value) {
        $value = absint($value);
        if ($value < self::MIN_LIMIT) {
            $value = self::MIN_LIMIT;
        }

        if ($value > self::MAX_LIMIT) {
            $value = self::MAX_LIMIT;
        }

        return $value;
    }

    private static function decode_source_string($value) {
        if (!is_string($value)) {
            return '';
        }

        $value = preg_replace('/\\\\u([0-9a-fA-F]{4})/', '&#x$1;', $value);
        $value = str_replace(array('\\/', '\\\\'), array('/', '\\'), $value);

        $decoded = html_entity_decode($value, ENT_QUOTES, 'UTF-8');

        return is_string($decoded) ? $decoded : '';
    }

    private static function get_cache_key($username, $limit) {
        return self::CACHE_PREFIX . md5($username . '|' . $limit);
    }

    private static function get_cache_ttl() {
        $settings = self::get_settings();
        $ttl = max(60, absint($settings['cache_ttl']));

        return (int) apply_filters('chrono_insta_cache_ttl', $ttl);
    }

    private static function remember_cache_key($key) {
        $keys = get_option(self::CACHE_INDEX_OPTION, array());

        if (!in_array($key, $keys, true)) {
            $keys[] = $key;
            update_option(self::CACHE_INDEX_OPTION, $keys, false);
        }
    }

    public static function clear_cache() {
        $keys = get_option(self::CACHE_INDEX_OPTION, array());

        if (empty($keys)) {
            return;
        }

        foreach ($keys as $key) {
            delete_transient($key);
        }

        delete_option(self::CACHE_INDEX_OPTION);
    }
}

Chrono_Insta::init();

