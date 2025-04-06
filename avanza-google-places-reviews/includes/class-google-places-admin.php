<?php
class Google_Places_Admin {
    public function __construct() {
        // Asegurarnos de que esto se ejecute solo en el admin
        if (is_admin()) {
            add_action('admin_menu', array($this, 'add_admin_menu'));
            add_action('admin_init', array($this, 'register_settings'));
            add_action('admin_notices', array($this, 'admin_notices'));
        }
    }

    public function add_admin_menu() {
        // Cambiar 'manage_options' por la capacidad correcta
        $capability = 'manage_options';
        
        // Registrar la página en el menú de ajustes
        add_submenu_page(
            'options-general.php',          // Parent slug
            'Google Places Reviews',        // Título de la página
            'Google Reviews',              // Título del menú
            $capability,                   // Capacidad requerida
            'google-places-reviews',       // Slug del menú
            array($this, 'render_admin_page')
        );
    }

    public function register_settings() {
        // Registrar la configuración
        register_setting(
            'gpr_settings',              // Grupo de opciones
            'gpr_api_key',               // Nombre de la opción
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => ''
            )
        );

        // Añadir sección de configuración
        add_settings_section(
            'gpr_main_section',
            'Configuración de Google Places Reviews',
            array($this, 'section_callback'),
            'gpr_settings'
        );

        // Añadir campo para API Key
        add_settings_field(
            'gpr_api_key',
            'API Key',
            array($this, 'api_key_callback'),
            'gpr_settings',
            'gpr_main_section'
        );

        // Añadir botón de actualización
        add_settings_field(
            'gpr_update_reviews',
            'Actualizar Reseñas',
            array($this, 'update_button_callback'),
            'gpr_settings',
            'gpr_main_section'
        );
        
        // Añadir campo para probar un Place ID específico
        add_settings_field(
            'gpr_test_place_id',
            'Probar Place ID',
            array($this, 'test_place_id_callback'),
            'gpr_settings',
            'gpr_main_section'
        );

        // Registrar acción para el botón
        add_action('admin_post_update_reviews', array($this, 'handle_update_reviews'));
        
        // Registrar acción para el botón de prueba
        add_action('admin_post_test_place_id', array($this, 'handle_test_place_id'));
    }

    public function section_callback() {
        echo '<p>Introduce tu API Key de Google Places.</p>';
    }

    public function api_key_callback() {
        $api_key = get_option('gpr_api_key');
        ?>
        <input type="text" 
               name="gpr_api_key" 
               value="<?php echo esc_attr($api_key); ?>" 
               class="regular-text">
        <p class="description">
            Obtén tu API Key desde la <a href="https://console.cloud.google.com/apis/credentials" target="_blank">Google Cloud Console</a>
        </p>
        <?php
    }

    public function update_button_callback() {
        $update_url = admin_url('admin-post.php?action=update_reviews');
        $update_url = wp_nonce_url($update_url, 'update_reviews_nonce');
        ?>
        <a href="<?php echo esc_url($update_url); ?>" class="button button-secondary">
            Actualizar todas las reseñas
        </a>
        <p class="description">
            Haz clic para obtener las reseñas más recientes de Google Places.
        </p>
        <?php
    }
    
    public function test_place_id_callback() {
        ?>
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <input type="hidden" name="action" value="test_place_id">
            <?php wp_nonce_field('test_place_id_nonce'); ?>
            <input type="text" name="place_id" placeholder="Introduce el Place ID para probar" class="regular-text">
            <input type="submit" class="button button-secondary" value="Probar ID">
        </form>
        <p class="description">
            Introduce un Place ID para verificar si funciona correctamente con la API.
        </p>
        <?php
    }

    public function handle_update_reviews() {
        if (!wp_verify_nonce($_REQUEST['_wpnonce'], 'update_reviews_nonce')) {
            wp_die('Acción no autorizada');
        }

        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos para realizar esta acción');
        }

        global $wpdb;
        // Eliminar todos los transients de reseñas
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%google_reviews_%'");

        // Obtener todas las tiendas
        $posts = get_posts(array(
            'post_type' => 'any',
            'posts_per_page' => -1,
            'meta_key' => 'google_place_id', // Cambiar 'id_google_maps' por 'google_place_id'
            'meta_value' => '',
            'meta_compare' => '!='
        ));

        $reviews_updated = 0;
        $errors = array();
        $google_places = new Google_Places_Reviews();
        $google_places->init();

        foreach ($posts as $post) {
            $place_id = get_post_meta($post->ID, 'google_place_id', true); // Cambiar 'id_google_maps' por 'google_place_id'
            if ($place_id) {
                // Forzar actualización de reseñas
                $reviews_data = $google_places->fetch_google_reviews($place_id);
                if (!is_wp_error($reviews_data)) {
                    update_post_meta($post->ID, 'google_reviews_data', $reviews_data);
                    $reviews_updated++;
                } else {
                    // Guardar errores para mostrarlos después
                    $errors[] = 'Error en ' . $post->post_title . ' (ID: ' . $post->ID . '): ' . 
                               $reviews_data->get_error_message() . ' - Place ID: ' . $place_id;
                }
            }
        }

        // Guardar errores para mostrarlos
        if (!empty($errors)) {
            update_option('gpr_update_errors', $errors);
        } else {
            delete_option('gpr_update_errors');
        }

        // Redirigir de vuelta con mensaje
        $redirect_url = add_query_arg(
            array(
                'page' => 'google-places-reviews',
                'updated' => $reviews_updated,
                'errors' => !empty($errors) ? 1 : 0
            ),
            admin_url('options-general.php')
        );

        wp_redirect($redirect_url);
        exit;
    }
    
    public function handle_test_place_id() {
        if (!wp_verify_nonce($_REQUEST['_wpnonce'], 'test_place_id_nonce')) {
            wp_die('Acción no autorizada');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos para realizar esta acción');
        }
        
        $place_id = isset($_POST['place_id']) ? sanitize_text_field($_POST['place_id']) : '';
        
        if (empty($place_id)) {
            wp_die('El Place ID es obligatorio');
        }
        
        $google_places = new Google_Places_Reviews();
        $google_places->init();
        
        $result = $google_places->fetch_google_reviews($place_id);
        
        echo '<h2>Resultado de la prueba</h2>';
        
        if (is_wp_error($result)) {
            echo '<p>Error: ' . $result->get_error_message() . '</p>';
        } else {
            echo '<p>Éxito! El Place ID es válido.</p>';
            echo '<pre>';
            print_r($result);
            echo '</pre>';
        }
        
        echo '<p><a href="' . admin_url('options-general.php?page=google-places-reviews') . '">Volver a la configuración</a></p>';
        exit;
    }

    // Añadir mensaje de actualización
    public function admin_notices() {
        if (isset($_GET['page']) && $_GET['page'] === 'google-places-reviews') {
            // Mensaje de actualización exitosa
            if (isset($_GET['updated'])) {
                $count = intval($_GET['updated']);
                ?>
                <div class="notice notice-success is-dismissible">
                    <p>Se han actualizado las reseñas de <?php echo $count; ?> tiendas.</p>
                </div>
                <?php
            }
            
            // Mostrar errores si los hay
            if (isset($_GET['errors']) && $_GET['errors'] == 1) {
                $errors = get_option('gpr_update_errors', array());
                if (!empty($errors)) {
                    ?>
                    <div class="notice notice-error is-dismissible">
                        <p><strong>Se encontraron algunos errores:</strong></p>
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo esc_html($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php
                }
            }
        }
    }

    public function render_admin_page() {
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes suficientes permisos para acceder a esta página.'));
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('gpr_settings');
                do_settings_sections('gpr_settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}

// Inicializar la clase admin solo si estamos en el admin
if (is_admin()) {
    new Google_Places_Admin();
}