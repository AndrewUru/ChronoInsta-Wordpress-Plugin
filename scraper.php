<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php';

header('Content-Type: application/json; charset=utf-8');

$raw_username = '';
if (isset($_GET['username'])) {
    $raw_username = sanitize_text_field(wp_unslash($_GET['username']));
}

if ($raw_username === '') {
    wp_send_json(
        array('error' => 'No se proporciono nombre de usuario.'),
        400
    );
}

$force_refresh = isset($_GET['refresh']) && $_GET['refresh'] === '1';

$payload = chrono_insta_fetch_feed_payload(
    $raw_username,
    array('force_refresh' => $force_refresh)
);

if (is_wp_error($payload)) {
    $status = (int) $payload->get_error_data('status');

    if ($status < 100) {
        $status = 502;
    }

    wp_send_json(
        array('error' => $payload->get_error_message()),
        $status
    );
}

if (empty($payload['profile']['username'])) {
    $payload['profile']['username'] = chrono_insta_normalize_username($raw_username);
}

wp_send_json($payload);

