<?php
/**
 * Plugin Name: ChronoInsta
 * Plugin URI: https://tusitio.com/chrono-insta
 * Description: Muestra el feed de Instagram solo con el nombre de usuario o URL del perfil.
 * Version: 1.6.0
 * Author: Andrés Tobío
 * Text Domain: chrono-insta
 */

if (!defined('ABSPATH')) {
    exit;
}

// Crear opcion de configuracion para el nombre de usuario.
function chrono_insta_register_settings() {
    add_option('chrono_insta_username', '');
    register_setting('chrono_insta_options_group', 'chrono_insta_username');
}
add_action('admin_init', 'chrono_insta_register_settings');

// Crear pagina de ajustes en el admin.
function chrono_insta_register_options_page() {
    add_options_page(
        'ChronoInsta Settings',
        'ChronoInsta',
        'manage_options',
        'chrono-insta',
        'chrono_insta_options_page'
    );
}
add_action('admin_menu', 'chrono_insta_register_options_page');

// Contenido de la pagina de ajustes.
function chrono_insta_options_page() {
    ?>
    <div class="wrap">
        <h2>Configuracion de ChronoInsta</h2>
        <p>Introduce tu nombre de usuario de Instagram para mostrar tu feed.</p>
        <?php if (isset($_GET['settings-updated'])) { ?>
            <div class="updated"><p><strong>Configuracion guardada.</strong></p></div>
        <?php } ?>

        <form method="post" action="options.php">
            <?php settings_fields('chrono_insta_options_group'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Nombre de usuario de Instagram</th>
                    <td>
                        <input type="text" name="chrono_insta_username" value="<?php echo esc_attr(get_option('chrono_insta_username')); ?>" />
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

/**
 * Normaliza un nombre de usuario de Instagram.
 */
function chrono_insta_normalize_username($raw_username) {
    if (!is_string($raw_username)) {
        return '';
    }

    $normalized = trim($raw_username);
    if ($normalized === '') {
        return '';
    }

    $normalized = ltrim($normalized, '@');
    $normalized = trim($normalized, " \t\n\r\0\x0B/");

    return $normalized;
}

/**
 * Genera un valor hexadecimal pseudoaleatorio con un largo determinado.
 */
function chrono_insta_random_hex($bytes = 16) {
    $bytes = max(1, (int) $bytes);

    try {
        return bin2hex(random_bytes($bytes));
    } catch (Exception $e) {
        $fallback = '';
        for ($i = 0; $i < $bytes; $i += 1) {
            $fallback .= sprintf('%02x', wp_rand(0, 255));
        }

        return $fallback;
    }
}

/**
 * Recupera y procesa el feed publico de Instagram.
 *
 * @param string $raw_username  Nombre de usuario o enlace al perfil.
 * @param array  $args          { force_refresh => bool }.
 *
 * @return array|WP_Error
 */
function chrono_insta_fetch_feed_payload($raw_username, $args = array()) {
    $username = chrono_insta_normalize_username($raw_username);

    if ($username === '') {
        return new WP_Error('chrono_insta_invalid_username', 'El nombre de usuario no es valido.');
    }

    $args = wp_parse_args($args, array(
        'force_refresh' => false,
    ));

    $profile_url = 'https://www.instagram.com/' . rawurlencode($username) . '/';
    $cache_key = 'chrono_insta_feed_' . md5($username);
    $cache_ttl = (int) apply_filters('chrono_insta_cache_ttl', 15 * MINUTE_IN_SECONDS);

    if ($cache_ttl < MINUTE_IN_SECONDS) {
        $cache_ttl = MINUTE_IN_SECONDS;
    }

    $cached_payload = get_transient($cache_key);

    if ($cached_payload && empty($args['force_refresh'])) {
        $cached_payload['cached'] = true;
        return $cached_payload;
    }

    $api_url = add_query_arg(
        array('username' => $username),
        'https://www.instagram.com/api/v1/users/web_profile_info/'
    );

    $csrf_token = chrono_insta_random_hex(16);
    $ig_did = wp_generate_uuid4();
    $mid = strtoupper(chrono_insta_random_hex(8));

    $cookie_header = 'csrftoken=' . $csrf_token . '; ';
    $cookie_header .= 'ig_did=' . $ig_did . '; ';
    $cookie_header .= 'mid=' . $mid . '; ';
    $cookie_header .= 'rur="NA"; ';

    $request_args = array(
        'timeout' => 12,
        'httpversion' => '1.1',
        'headers' => array(
            'User-Agent'       => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36',
            'X-IG-App-ID'      => '936619743392459',
            'Accept'           => 'application/json',
            'Accept-Language'  => 'es-ES,es;q=0.9,en;q=0.8',
            'Referer'          => $profile_url,
            'Origin'           => 'https://www.instagram.com',
            'X-Requested-With' => 'XMLHttpRequest',
            'X-CSRFToken'      => $csrf_token,
            'Cookie'           => $cookie_header,
            'Sec-Fetch-Site'   => 'same-origin',
            'Sec-Fetch-Mode'   => 'cors',
            'Sec-Fetch-Dest'   => 'empty',
        ),
    );

    $response = wp_remote_get($api_url, $request_args);

    if (is_wp_error($response)) {
        $request_args['sslverify'] = false;
        $response = wp_remote_get($api_url, $request_args);
    }

    if (is_wp_error($response)) {
        if ($cached_payload) {
            $cached_payload['cached'] = true;
            $cached_payload['notice'] = 'Se mostro contenido en cache por un error al conectar con Instagram.';
            return $cached_payload;
        }

        return new WP_Error('chrono_insta_connection_error', 'Error al conectar con Instagram.');
    }

    $status_code = (int) wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);

    if ($status_code !== 200 || empty($body)) {
        $message = 'La respuesta de Instagram no es valida.';
        $api_error = json_decode($body, true);

        if (is_array($api_error) && !empty($api_error['message'])) {
            $message = $api_error['message'];
        } elseif ($status_code) {
            $message = 'Instagram respondio con el codigo ' . $status_code . '.';
        }

        if ($cached_payload) {
            $cached_payload['cached'] = true;
            $cached_payload['notice'] = $message;
            return $cached_payload;
        }

        return new WP_Error('chrono_insta_bad_response', $message, array('status' => $status_code));
    }

    $data = json_decode($body, true);

    if (!is_array($data)) {
        return new WP_Error('chrono_insta_unexpected_format', 'Formato de datos de Instagram no reconocido.');
    }

    $edges = $data['data']['user']['edge_owner_to_timeline_media']['edges'] ?? array();

    if (empty($edges)) {
        return new WP_Error('chrono_insta_no_images', 'No se encontraron imagenes.');
    }

    $images = array();

    foreach ($edges as $edge) {
        if (empty($edge['node']['display_url'])) {
            continue;
        }

        $node = $edge['node'];
        $image_url = $node['display_url'];
        $shortcode = $node['shortcode'] ?? '';
        $permalink = $shortcode
            ? 'https://www.instagram.com/p/' . $shortcode . '/'
            : $profile_url;

        $alt_text = '';

        if (!empty($node['accessibility_caption'])) {
            $alt_text = $node['accessibility_caption'];
        } elseif (!empty($node['edge_media_to_caption']['edges'][0]['node']['text'])) {
            $alt_text = wp_trim_words($node['edge_media_to_caption']['edges'][0]['node']['text'], 20);
        }

        $images[] = array(
            'url'       => $image_url,
            'permalink' => $permalink,
            'alt'       => $alt_text,
        );
    }

    if (empty($images)) {
        return new WP_Error('chrono_insta_empty_images', 'No se pudieron procesar las imagenes.');
    }

    $payload = array(
        'profile' => array(
            'username' => $username,
            'url'      => $profile_url,
        ),
        'images'  => $images,
    );

    set_transient($cache_key, $payload, $cache_ttl);

    return $payload;
}

function chrono_insta_shortcode($shortcode_atts = array()) {
    $username = trim(get_option('chrono_insta_username'));

    if ($username === '') {
        return '<p>Por favor, configura tu nombre de usuario de Instagram en Ajustes > ChronoInsta.</p>';
    }

    $atts = shortcode_atts(
        array(
            'limit' => 9,
        ),
        $shortcode_atts,
        'chrono_insta_feed'
    );

    $limit = (int) $atts['limit'];
    if ($limit < 1) {
        $limit = 1;
    }
    if ($limit > 12) {
        $limit = 12;
    }

    $payload = chrono_insta_fetch_feed_payload($username);

    if (is_wp_error($payload)) {
        return '<div class="chrono-insta-feedback is-error">' . esc_html($payload->get_error_message()) . '</div>';
    }

    $images = array_slice($payload['images'], 0, $limit);

    if (empty($images)) {
        return '<div class="chrono-insta-feedback is-error">No hay imagenes disponibles en este momento.</div>';
    }

    $profile_url = esc_url($payload['profile']['url']);
    $display_name = $payload['profile']['username'];
    $aria_label = sprintf('Abrir publicacion de %s en Instagram', $display_name);
    $notice = !empty($payload['notice']) ? esc_html($payload['notice']) : '';

    ob_start();
    ?>
    <div class="chrono-insta-wrapper">
        <div class="chrono-insta-feed" aria-live="polite" aria-busy="false">
            <?php foreach ($images as $item) :
                if (empty($item['url'])) {
                    continue;
                }

                $image_url = esc_url($item['url']);
                $permalink = !empty($item['permalink']) ? esc_url($item['permalink']) : $profile_url;
                $alt_text = !empty($item['alt'])
                    ? $item['alt']
                    : 'Publicacion reciente de ' . $display_name;
            ?>
            <a class="chrono-insta-card is-ready" href="<?php echo $permalink; ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr($aria_label); ?>">
                <img src="<?php echo $image_url; ?>" alt="<?php echo esc_attr($alt_text); ?>" loading="lazy" decoding="async" />
            </a>
            <?php endforeach; ?>
        </div>
        <a class="chrono-insta-cta" href="<?php echo $profile_url; ?>" target="_blank" rel="noopener noreferrer">Ver perfil en Instagram</a>
        <?php if ($notice) { ?>
        <p class="chrono-insta-feedback"><?php echo $notice; ?></p>
        <?php } ?>
    </div>
    <?php
    return ob_get_clean();
}

add_shortcode('chrono_insta_feed', 'chrono_insta_shortcode');

// Enqueue estilos del plugin.
function chrono_insta_enqueue_assets() {
    wp_enqueue_style(
        'chrono-insta-style',
        plugin_dir_url(__FILE__) . 'assets/css/style.css',
        array(),
        '1.6'
    );
}
add_action('wp_enqueue_scripts', 'chrono_insta_enqueue_assets');
