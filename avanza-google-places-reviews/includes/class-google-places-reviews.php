<?php
class Google_Places_Reviews {
    private $api_key;
    private $cache_time = 21600; // 6 horas

    public function init() {
        $this->api_key = get_option('gpr_api_key');
        add_shortcode('google_reviews', array($this, 'render_reviews'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
    }

    public function enqueue_styles() {
        wp_enqueue_style(
            'google-places-reviews',
            AGPR_PLUGIN_URL . 'assets/css/style.css',
            array(),
            AGPR_VERSION
        );
    }

    public function render_reviews($atts = array()) {
        // Permitir que el shortcode acepte un place_id como atributo
        $atts = shortcode_atts(array(
            'place_id' => '',
        ), $atts, 'google_reviews');
        
        $post_id = get_the_ID();
        $place_id = !empty($atts['place_id']) ? $atts['place_id'] : get_post_meta($post_id, 'google_place_id', true);
        
        if (empty($place_id)) {
            return '<p class="gpr-error">Place ID no encontrado</p>';
        }
        
        // Validar que el place_id tenga el formato correcto (típicamente empieza con "ChI")
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $place_id)) {
            return '<p class="gpr-error">Formato de Place ID inválido: ' . esc_html($place_id) . '</p>';
        }
        
        // Obtener datos almacenados
        $reviews_data = get_post_meta($post_id, 'google_reviews_data', true);
        
        // Si hay un place_id específico en el shortcode o no hay datos almacenados, obtenerlos
        if (!empty($atts['place_id']) || empty($reviews_data)) {
            $reviews_data = $this->fetch_google_reviews($place_id);
            
            if (!is_wp_error($reviews_data)) {
                // Solo almacenar si estamos usando el place_id del post
                if (empty($atts['place_id'])) {
                    update_post_meta($post_id, 'google_reviews_data', $reviews_data);
                }
            } else {
                // Mostrar el error específico de la API para facilitar la depuración
                return '<p class="gpr-error">Error al obtener reseñas: ' . esc_html($reviews_data->get_error_message()) . '</p>';
            }
        }
        
        return $this->generate_reviews_html($reviews_data);
    }

    public function fetch_google_reviews($place_id) {
        // Verificar que tenemos una API key
        if (empty($this->api_key)) {
            return new WP_Error('api_key_missing', 'API key de Google Places no configurada');
        }
        
        $url = add_query_arg(
            array(
                'place_id' => $place_id,
                'fields' => 'rating,reviews,user_ratings_total',
                'key' => $this->api_key,
                'language' => 'es-ES',
            ),
            'https://maps.googleapis.com/maps/api/place/details/json'
        );
        
        $response = wp_remote_get($url, array(
            'timeout' => 15,
            'sslverify' => true
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // Comprobar si hay un error en la respuesta de la API
        if (empty($data)) {
            return new WP_Error('api_error', 'Respuesta vacía de la API de Google Places');
        }
        
        if (isset($data['error_message'])) {
            return new WP_Error('api_error', $data['error_message']);
        }
        
        if (!isset($data['result']) || !isset($data['result']['rating'])) {
            // Guardar la respuesta para depuración y análisis
            update_option('gpr_last_error_response', json_encode($data));
            return new WP_Error('invalid_place_id', 'El ID de lugar proporcionado no devolvió datos válidos: ' . $place_id);
        }
        
        return $data;
    }

    private function generate_reviews_html($data) {
        if (is_wp_error($data)) {
            return '<p class="gpr-error">' . esc_html($data->get_error_message()) . '</p>';
        }
    
        ob_start();
        ?>
        <div class="gpr-container">
            <div class="gpr-average-rating">
                <div class="gpr-rating-wrapper">
                    <span class="gpr-rating"><?php echo esc_html($data['result']['rating']); ?> / 5</span>
                    <span class="gpr-stars">
                        <?php echo str_repeat('<span class="gpr-star">★</span>', round($data['result']['rating'])); ?>
                    </span>
                    <span class="gpr-total-reviews">
                        <?php echo esc_html($data['result']['user_ratings_total']); ?> reseñas
                    </span>
                </div>
            </div>
            
            <div class="gpr-reviews-list">
                <?php 
                // Filtrar reseñas para mostrar solo las de 4 o más estrellas
                $filtered_reviews = array();
                foreach ($data['result']['reviews'] as $review) {
                    if ($review['rating'] >= 4) {
                        $filtered_reviews[] = $review;
                    }
                }
                
                // Si no hay reseñas de 4 o más estrellas, mostrar mensaje
                if (empty($filtered_reviews)) {
                    echo '<p class="gpr-notice">Todavía no hay reseñas disponibles.</p>';
                } else {
                    // Mostrar hasta 3 reseñas de las filtradas
                    $reviews_to_show = array_slice($filtered_reviews, 0, 3);
                    
                    foreach ($reviews_to_show as $review): 
                        // Corregir la conversión de timestamp a fecha
                        // El formato correcto debe ser 'time' o 'relative_time_description' en lugar de cambiar el formato
                        if (isset($review['relative_time_description'])) {
                            $fecha = $review['relative_time_description']; // Usar el texto relativo que proporciona Google
                        } else {
                            // Como alternativa, si no existe relative_time_description, convertir correctamente el timestamp
                            $timestamp = isset($review['time']) ? intval($review['time']) : 0;
                            $fecha = date_i18n('d/m/Y', $timestamp);
                        }
                ?>
                    <div class="gpr-review-item">
                        <div class="gpr-reviewer">
                            <img loading="lazy" src="<?php echo esc_url($review['profile_photo_url']); ?>" 
                                 alt="<?php echo esc_attr($review['author_name']); ?>">
                            <div class="gpr-reviewer-info">
                                <span class="gpr-author"><?php echo esc_html($review['author_name']); ?></span>
                                <span class="gpr-date"><?php echo $fecha; ?></span>
                            </div>
                        </div>
                        <div class="gpr-rating">
                            <?php echo str_repeat('<span class="gpr-star">★</span>', $review['rating']); ?>
                        </div>
                        <div class="gpr-text"><?php echo esc_html($review['text']); ?></div>
                    </div>
                <?php 
                    endforeach;
                }
                ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}