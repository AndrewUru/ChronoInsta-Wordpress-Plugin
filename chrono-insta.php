<?php
/**
 * Plugin Name: ChronoInsta
 * Plugin URI: https://tusitio.com/chrono-insta
 * Description: Muestra el feed de Instagram solo con el nombre de usuario o URL del perfil.
 * Version: 1.3.1
 * Author: Tu Nombre
 * Text Domain: chrono-insta
 */

if (!defined('ABSPATH')) exit;

// Crear opción de configuración para el nombre de usuario
function chrono_insta_register_settings() {
    add_option('chrono_insta_username', '');
    register_setting('chrono_insta_options_group', 'chrono_insta_username');
}
add_action('admin_init', 'chrono_insta_register_settings');

// Crear página de ajustes en el admin
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

// Contenido de la página de ajustes
function chrono_insta_options_page() {
?>
    <div class="wrap">
        <h2>Configuración de ChronoInsta</h2>
        <p>Introduce tu nombre de usuario de Instagram para mostrar tu feed.</p>
        <?php if (isset($_GET['settings-updated'])) { ?>
            <div class="updated"><p><strong>Configuración guardada.</strong></p></div>
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

function chrono_insta_shortcode() {

    $username = trim(get_option('chrono_insta_username'));

    if (!$username) {
        return '<p>Por favor, configura tu nombre de usuario de Instagram en Ajustes > ChronoInsta.</p>';
    }

    $normalized_username = ltrim($username, '@');
    $normalized_username = trim($normalized_username, " \t\n\r\0\x0B/");
    $profile_url = esc_url('https://www.instagram.com/' . $normalized_username . '/');

    ob_start(); ?>

    <div class="chrono-insta-wrapper">
        <div
            id="chrono-insta-feed"
            class="chrono-insta-feed js-chrono-insta-feed"
            data-username="<?php echo esc_attr($normalized_username); ?>"
            data-profile-url="<?php echo $profile_url; ?>"
            data-limit="9"
            aria-live="polite"
            aria-busy="true"
        >
            <div class="chrono-insta-feedback">Cargando publicaciones de Instagram...</div>
        </div>
        <a class="chrono-insta-cta" href="<?php echo $profile_url; ?>" target="_blank" rel="noopener noreferrer">
            Ver perfil en Instagram
        </a>
    </div>

    <?php
    return ob_get_clean();
}

add_shortcode('chrono_insta_feed', 'chrono_insta_shortcode');

// Enqueue JS
function chrono_insta_enqueue_scripts() {
    $version = '1.3.1';

    wp_enqueue_style('chrono-insta-css', plugin_dir_url(__FILE__) . 'css/style.css', array(), $version);
    wp_enqueue_script('chrono-insta-js', plugin_dir_url(__FILE__) . 'js/chrono-insta.js', array(), $version, true);

    wp_localize_script(
        'chrono-insta-js',
        'ChronoInstaSettings',
        array(
            'scraperUrl'      => plugins_url('scraper.php', __FILE__),
            'profileBaseUrl'  => 'https://www.instagram.com/',
        )
    );
}
add_action('wp_enqueue_scripts', 'chrono_insta_enqueue_scripts');
