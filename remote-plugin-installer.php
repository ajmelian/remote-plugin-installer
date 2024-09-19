<?php
/**
 * Plugin Name: Remote Plugin Manager
 * Description: <strong>Gestor de plugins remoto</strong> que permite la instalación, activación, desactivación y listado de plugins en un sitio WordPress a través de una API/Rest protegida mediante autenticación por token. Este plugin es útil para desarrolladores que quieran automatizar o gestionar plugins de forma remota en sitios WordPress sin tener que acceder al panel de administración manualmente.
 * Version: 1.0
 * Created: 2024-09-06
 * Author: Aythami Melián Perdomo
 * Author URI: mailto:ajmelper@gmail.com
 * License: GPLv3
 * Requires PHP: 5.6
 * Requires WP: 4.7
 */

if (!defined('ABSPATH')) {
    exit; // Evitar acceso directo
}
// Asegúrate de que se cargue el archivo que contiene WP_Upgrader_Skin
require_once(ABSPATH . 'wp-admin/includes/class-wp-upgrader.php');

class Custom_Upgrader_Skin extends WP_Upgrader_Skin {
    public function feedback($string, ...$args) {
        if (!empty($args)) {
            $string = vsprintf($string, $args);
        }
        // Puedes manejar o almacenar este feedback como lo necesites
    }

    public function error($error) {
        if (is_wp_error($error)) {
            $error = $error->get_error_message();
        }
        $this->feedback($error);
    }

    public function show_message($message) {
        $this->feedback($message);
    }
}


/**
 * Verifica si el usuario actual tiene permisos adecuados para gestionar plugins.
 *
 * @return bool True si el usuario tiene permisos, False en caso contrario.
 */
function rpiVerifyUser() {
    $user = wp_get_current_user();
    
    // Depuración
    if (!$user || $user->ID == 0) {
        return false;
    }
    
    // Verifica si el usuario tiene permisos para manage_options
    if (!current_user_can('manage_options')) {
        return false;
    }

    return true;
}

/**
 * Sube, instala y activa un plugin desde un archivo ZIP.
 *
 * @param WP_REST_Request $request La solicitud HTTP enviada a la API REST.
 * @return WP_REST_Response JSON con el resultado de la instalación y activación del plugin.
 */
function rpi_installPlugin(WP_REST_Request $request) {
    $zipFile = $request->get_file_params()['zipFile'];

    if (!$zipFile || !is_uploaded_file($zipFile['tmp_name'])) {
        return new WP_REST_Response([
            'code' => 'invalid_file',
            'message' => 'No se ha subido ningún archivo válido.'
        ], 400);
    }

    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/plugin.php');
    require_once(ABSPATH . 'wp-admin/includes/class-wp-upgrader-skin.php');
    require_once(ABSPATH . 'wp-admin/includes/class-wp-upgrader.php');


    $overrides = ['test_form' => false];
    $file = wp_handle_upload($zipFile, $overrides);

    if (isset($file['error'])) {
        return new WP_REST_Response([
            'code' => 'upload_error',
            'message' => 'Error al subir el archivo: ' . $file['error']
        ], 500);
    }

    $pluginFile = $file['file'];
    $skin = new Custom_Upgrader_Skin();
    $upgrader = new Plugin_Upgrader($skin);
    $result = $upgrader->install($pluginFile,['clear_update_cache'=>true]);

    if (is_wp_error($result)) {
        return new WP_REST_Response([
            'code' => 'install_error',
            'message' => 'Error al instalar el plugin: ' . $result->get_error_message()
        ], 500);
    }

    // Activa el plugin
    $toggleActivate = activate_plugin($upgrader->plugin_info());

    if (is_wp_error($toggleActivate)) {
        return new WP_REST_Response([
            'code' => 'activation_error',
            'message' => 'Error al activar el plugin: ' . $toggleActivate->get_error_message()
        ], 500);
    }

    return new WP_REST_Response([
        'code' => 'success',
        'message' => 'Plugin instalado y activado con éxito.',
        'plugin' => $upgrader->plugin_info()
    ], 200);
}

/**
 * Activa o desactiva un plugin especificado.
 *
 * @param WP_REST_Request $request La solicitud HTTP enviada a la API REST.
 * @return WP_REST_Response JSON con el resultado de la operación.
 */
function rpi_togglePlugin(WP_REST_Request $request) {
    require_once(ABSPATH . 'wp-admin/includes/plugin.php');
    
    $slug = $request->get_param('slug');
    $action = $request->get_param('action');

    if (!$slug || !in_array($action, ['activate', 'deactivate'])) {
        return new WP_REST_Response([
            'code' => 'invalid_parameters',
            'message' => 'Faltan parámetros o son inválidos.'
        ], 400);
    }

    if ($action === 'activate') {
        $result = activate_plugin($slug);
    } else {
        deactivate_plugins($slug);
        $result = is_plugin_active($slug) ? new WP_Error('deactivation_failed', 'La desactivación del plugin falló.') : true;
    }

    if (is_wp_error($result)) {
        return new WP_REST_Response([
            'code' => $result->get_error_code(),
            'message' => $result->get_error_message()
        ], 500);
    }

    return new WP_REST_Response([
        'code' => 'success',
        'message' => "Plugin {$action} con éxito.",
        'plugin' => $slug
    ], 200);
}

/**
 * Lista los plugins disponibles en el sitio, incluyendo su estado de activación.
 *
 * @return WP_REST_Response JSON con el listado de plugins.
 */
function rpi_listPlugins(WP_REST_Request $request) {
    require_once(ABSPATH . 'wp-admin/includes/plugin.php');
    
    $plugins = get_plugins();
    $pluginsActivated = get_option('active_plugins', []);

    $pluginsList = [];

    foreach ($plugins as $pluginFile => $pluginData) {
        $pluginsList[] = [
            'plugin_file' => $pluginFile,
            'plugin_name' => $pluginData['Name'],
            'is_active' => in_array($pluginFile, $pluginsActivated),
        ];
    }

    return new WP_REST_Response($pluginsList, 200);
}

/**
 * Registra los endpoints del plugin en la API REST de WordPress.
 */
function rpi_register_routes() {
    register_rest_route('remote-plugin-installer/v1', '/install-plugin', [
        'methods' => 'POST',
        'callback' => 'rpi_installPlugin',
        'permission_callback' => 'rpiVerifyUser', // Autenticación
    ]);

    register_rest_route('remote-plugin-installer/v1', '/toggle-plugin', [
        'methods' => 'POST',
        'callback' => 'rpi_togglePlugin',
        'permission_callback' => 'rpiVerifyUser', // Autenticación
    ]);

    register_rest_route('remote-plugin-installer/v1', '/list-plugins', [
        'methods' => 'GET',
        'callback' => 'rpi_listPlugins',
        'permission_callback' => 'rpiVerifyUser', // Autenticación
    ]);
}

add_action('rest_api_init', 'rpi_register_routes');