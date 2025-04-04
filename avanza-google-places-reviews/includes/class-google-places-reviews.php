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

    public function render_reviews() {
        $post_id = get_the_ID();
        $place_id = get_post_meta($post_id, 'google_place_id', true);
        
        if (empty($place_id)) {
            return '<p class="gpr-error">Place ID no encontrado</p>';
        }

        $cache_key = 'google_reviews_' . $place_id;
        $reviews_data = get_transient($cache_key);

        if (false === $reviews_data) {
            $reviews_data = $this->fetch_google_reviews($place_id);
            
            if (!is_wp_error($reviews_data)) {
                set_transient($cache_key, $reviews_data, $this->cache_time);
            }
        }

        return $this->generate_reviews_html($reviews_data);
    }

    private function fetch_google_reviews($place_id) {
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

        if (empty($data) || isset($data['error_message'])) {
            return new WP_Error('api_error', $data['error_message'] ?? 'Error al obtener reseñas');
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
                $reviews = array_slice($data['result']['reviews'], 0, 3);
                foreach ($reviews as $review): 
                    // Convertir el timestamp a fecha legible
                    $fecha = date_i18n('d/m/Y', strtotime($review['time']));
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
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
