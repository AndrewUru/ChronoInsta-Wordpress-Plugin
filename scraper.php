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
$api_url = add_query_arg(
    array('username' => $normalized_username),
    'https://www.instagram.com/api/v1/users/web_profile_info/'
);

$request_args = array(
    'timeout' => 12,
    'headers' => array(
        'User-Agent'     => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36',
        'X-IG-App-ID'    => '936619743392459',
        'Accept'         => 'application/json',
        'Accept-Language'=> 'es-ES,es;q=0.9,en;q=0.8',
    ),
);

$response = wp_remote_get($api_url, $request_args);

if (is_wp_error($response)) {
    wp_send_json(
        array('error' => 'Error al conectar con Instagram.'),
        502
    );
}

$status_code = wp_remote_retrieve_response_code($response);
$body = wp_remote_retrieve_body($response);

if ($status_code !== 200 || empty($body)) {
    wp_send_json(
        array('error' => 'La respuesta de Instagram no es valida.'),
        502
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

wp_send_json(
    array(
        'profile' => array(
            'username' => $normalized_username,
            'url'      => $profile_url,
        ),
        'images'  => $images,
    )
);
