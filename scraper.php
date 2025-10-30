<?php

// Cargar WordPress
require_once $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php';

header('Content-Type: application/json; charset=utf-8');

$raw_username = '';
if (isset($_GET['username'])) {
    $raw_username = sanitize_text_field(wp_unslash($_GET['username']));
}

if ($raw_username === '') {
    wp_send_json(
        array('error' => 'No se proporciono nombre de usuario'),
        400
    );
}
$normalized_username = trim(ltrim($raw_username, '@'));

if ($normalized_username === '') {
    wp_send_json(
        array('error' => 'El nombre de usuario no es valido'),
        400
    );
}

$profile_url = 'https://www.instagram.com/' . rawurlencode($normalized_username) . '/';

$cache_key = 'chrono_insta_feed_' . md5($normalized_username);
$cache_ttl = (int) apply_filters('chrono_insta_cache_ttl', 15 * MINUTE_IN_SECONDS);
if ($cache_ttl < MINUTE_IN_SECONDS) {
    $cache_ttl = MINUTE_IN_SECONDS;
}
$force_refresh = isset($_GET['refresh']) && $_GET['refresh'] === '1';
$cached_payload = get_transient($cache_key);

if ($cached_payload && !$force_refresh) {
    $cached_payload['cached'] = true;
    wp_send_json($cached_payload);
}

$api_url = add_query_arg(
    array('username' => $normalized_username),
    'https://www.instagram.com/api/v1/users/web_profile_info/'
);

$csrf_token = bin2hex(random_bytes(16));
$ig_did = wp_generate_uuid4();
$mid = strtoupper(bin2hex(random_bytes(8)));

$cookie_header = 'csrftoken=' . $csrf_token . '; ';
$cookie_header .= 'ig_did=' . $ig_did . '; ';
$cookie_header .= 'mid=' . $mid . '; ';
$cookie_header .= 'rur="NA"; ';

$request_args = array(
    'timeout' => 12,
    'httpversion' => '1.1',
    'headers' => array(
        'User-Agent'     => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36',
        'X-IG-App-ID'    => '936619743392459',
        'Accept'         => 'application/json',
        'Accept-Language'=> 'es-ES,es;q=0.9,en;q=0.8',
        'Referer'        => $profile_url,
        'Origin'         => 'https://www.instagram.com',
        'X-Requested-With' => 'XMLHttpRequest',
        'X-CSRFToken'    => $csrf_token,
        'Cookie'         => $cookie_header,
        'Sec-Fetch-Site' => 'same-origin',
        'Sec-Fetch-Mode' => 'cors',
        'Sec-Fetch-Dest' => 'empty',
    ),
);

$response = wp_remote_get($api_url, $request_args);

if (is_wp_error($response)) {
    $fallback_args = $request_args;
    $fallback_args['sslverify'] = false;
    $response = wp_remote_get($api_url, $fallback_args);

    if (is_wp_error($response)) {
        wp_send_json(
            array('error' => 'Error al conectar con Instagram.'),
            502
        );
    }
}

$status_code = wp_remote_retrieve_response_code($response);
$body = wp_remote_retrieve_body($response);

if ($status_code !== 200 || empty($body)) {
    $api_error = json_decode($body, true);
    $message = '';

    if (is_array($api_error) && !empty($api_error['message'])) {
        $message = $api_error['message'];
    }

    if (empty($message) && $status_code) {
        $message = 'Instagram respondio con el codigo ' . $status_code;
    }

    if (empty($message)) {
        $message = 'La respuesta de Instagram no es valida.';
    }

    if ($cached_payload) {
        $cached_payload['cached'] = true;
        $cached_payload['notice'] = $message;
        wp_send_json($cached_payload);
    }

    wp_send_json(
        array('error' => $message),
        $status_code === 0 ? 502 : $status_code
    );
}

$data = json_decode($body, true);

if (!is_array($data)) {
    wp_send_json(
        array('error' => 'Formato de datos de Instagram no reconocido.'),
        502
    );
}

$edges = $data['data']['user']['edge_owner_to_timeline_media']['edges'] ?? array();

if (empty($edges)) {
    wp_send_json(
        array('error' => 'No se encontraron imagenes.'),
        404
    );
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
    wp_send_json(
        array('error' => 'No se pudieron procesar las imagenes.'),
        404
    );
}

$payload = array(
    'profile' => array(
        'username' => $normalized_username,
        'url'      => $profile_url,
    ),
    'images'  => $images,
);

set_transient($cache_key, $payload, $cache_ttl);

wp_send_json($payload);
