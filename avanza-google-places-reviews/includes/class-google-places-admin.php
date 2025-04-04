<?php
class Google_Places_Admin {
    public function __construct() {
        // Asegurarnos de que esto se ejecute solo en el admin
        if (is_admin()) {
            add_action('admin_menu', array($this, 'add_admin_menu'));
            add_action('admin_init', array($this, 'register_settings'));
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
