<?php
/*
Plugin Name: Avanza Google Places Reviews
Plugin URI: https://avanzafibra.com
Description: Plugin personalizado para mostrar reseñas de Google Places en las tiendas de Avanza
Version: 1.0.0
Author: pmalave
Author URI: https://avanzafibra.com
Text Domain: avanza-google-reviews
Domain Path: /languages
License: Proprietary
*/

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Verificar versión mínima de PHP
if (version_compare(PHP_VERSION, '7.0.0', '<')) {
    deactivate_plugins(plugin_basename(__FILE__));
    wp_die('Este plugin requiere PHP 7.0 o superior.');
}

// Definir constantes de forma segura
if (!defined('AGPR_PLUGIN_PATH')) {
    define('AGPR_PLUGIN_PATH', plugin_dir_path(__FILE__));
}

if (!defined('AGPR_PLUGIN_URL')) {
    define('AGPR_PLUGIN_URL', plugin_dir_url(__FILE__));
}

if (!defined('AGPR_VERSION')) {
    define('AGPR_VERSION', '1.0.0');
}

// Verificar que los archivos existen antes de incluirlos
$required_files = array(
    'includes/class-google-places-reviews.php',
    'includes/class-google-places-admin.php'
);

foreach ($required_files as $file) {
    if (!file_exists(AGPR_PLUGIN_PATH . $file)) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('Error: Archivo requerido no encontrado: ' . $file);
    }
}

// Cargar clases
require_once AGPR_PLUGIN_PATH . 'includes/class-google-places-reviews.php';
require_once AGPR_PLUGIN_PATH . 'includes/class-google-places-admin.php';

// Inicializar el plugin
function agpr_init() {
    if (class_exists('Google_Places_Reviews')) {
        $plugin = new Google_Places_Reviews();
        $plugin->init();
    }
}
add_action('plugins_loaded', 'agpr_init');

// Prevenir actualizaciones automáticas
add_filter('site_transient_update_plugins', 'agpr_prevent_update_check');
function agpr_prevent_update_check($transient) {
    if (isset($transient->response[plugin_basename(__FILE__)])) {
        unset($transient->response[plugin_basename(__FILE__)]);
    }
    return $transient;
}
