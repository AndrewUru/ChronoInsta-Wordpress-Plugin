<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Chrono_Insta')) {
    require_once plugin_dir_path(__FILE__) . 'chrono-insta.php';
}

$settings = Chrono_Insta::get_settings();

$username = isset($_GET['username'])
    ? Chrono_Insta::normalize_username(wp_unslash($_GET['username']))
    : $settings['username'];

$limit = isset($_GET['limit'])
    ? Chrono_Insta::sanitize_limit($_GET['limit'])
    : $settings['limit'];

$refresh = !empty($_GET['refresh']);

$result = Chrono_Insta::get_feed_items($username, $limit, $refresh);

if (is_wp_error($result)) {
    wp_send_json_error(
        array(
            'code' => $result->get_error_code(),
            'message' => $result->get_error_message(),
        ),
        400
    );
}

wp_send_json_success($result);
