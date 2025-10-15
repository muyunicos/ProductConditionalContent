<?php
/**
 * Funciones Helper para Admin
 * Compatible con WordPress 6.8.3, PHP 8.2, WooCommerce 10.2.2
 * 
 * @package ProductConditionalContent
 * @since 5.0.1
 */

if (!defined('ABSPATH')) exit;

final class GDM_Admin_Helpers {
    
    /**
     * Validar nonce y permisos para guardar metabox
     * 
     * @param int $post_id ID del post
     * @param WP_Post $post Objeto del post
     * @param string $nonce_field Nombre del campo nonce
     * @param string $nonce_action Acción del nonce
     * @param string $post_type Tipo de post esperado
     * @return bool True si la validación pasa, false si debe abortar
     */
    public static function validate_metabox_save($post_id, $post, $nonce_field, $nonce_action, $post_type) {
        // Verificar nonce
        if (!isset($_POST[$nonce_field]) || !wp_verify_nonce($_POST[$nonce_field], $nonce_action)) {
            return false;
        }
        
        // Verificar permisos
        if (!current_user_can('edit_post', $post_id)) {
            return false;
        }
        
        // Verificar autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return false;
        }
        
        // Verificar tipo de post
        if ($post->post_type !== $post_type) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Sanitizar array de enteros
     * 
     * @param array|mixed $array Array a sanitizar
     * @return array Array sanitizado
     */
    public static function sanitize_int_array($array) {
        if (!is_array($array)) {
            return [];
        }
        return array_map('intval', array_filter($array));
    }
    
    /**
     * Sanitizar array de strings
     * 
     * @param array|mixed $array Array a sanitizar
     * @return array Array sanitizado
     */
    public static function sanitize_text_array($array) {
        if (!is_array($array)) {
            return [];
        }
        return array_map('sanitize_text_field', array_filter($array));
    }
    
    /**
     * Obtener opciones disponibles (CPT gdm_opcion)
     * 
     * @param bool $force_refresh Forzar recarga desde BD
     * @return array Array de WP_Post
     */
    public static function get_available_opciones($force_refresh = false) {
        static $cache = null;
        
        if ($cache !== null && !$force_refresh) {
            return $cache;
        }
        
        $cache = get_posts([
            'post_type' => 'gdm_opcion',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC',
            'suppress_filters' => true,
        ]);
        
        return $cache;
    }
    
    /**
     * Obtener reglas disponibles (CPT gdm_regla)
     * 
     * @param int $exclude_id ID a excluir (opcional)
     * @param bool $only_reusable Solo reglas reutilizables
     * @return array Array de WP_Post
     */
    public static function get_available_reglas($exclude_id = 0, $only_reusable = false) {
        $args = [
            'post_type' => 'gdm_regla',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC',
            'suppress_filters' => true,
        ];
        
        if ($exclude_id > 0) {
            $args['exclude'] = [$exclude_id];
        }
        
        $reglas = get_posts($args);
        
        // Filtrar solo reutilizables si se solicita
        if ($only_reusable) {
            $reglas = array_filter($reglas, function($regla) {
                $aplicar_a = get_post_meta($regla->ID, '_gdm_aplicar_a', true) ?: [];
                return in_array('reutilizable', $aplicar_a);
            });
        }
        
        return $reglas;
    }
    
    /**
     * Renderizar campo select múltiple con WooCommerce enhanced select
     * 
     * @param string $id ID del campo
     * @param string $name Nombre del campo (sin [])
     * @param array $options Opciones [id => titulo]
     * @param array $selected IDs seleccionados
     * @param string $description Descripción del campo
     * @param array $args Argumentos adicionales (placeholder, class, etc)
     */
    public static function render_enhanced_select($id, $name, $options, $selected = [], $description = '', $args = []) {
        $defaults = [
            'placeholder' => __('Seleccionar...', 'product-conditional-content'),
            'class' => 'wc-enhanced-select',
            'style' => 'width:50%;',
            'multiple' => true,
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        ?>
        <select id="<?php echo esc_attr($id); ?>" 
                name="<?php echo esc_attr($name); ?>[]" 
                class="<?php echo esc_attr($args['class']); ?>" 
                <?php echo $args['multiple'] ? 'multiple="multiple"' : ''; ?>
                style="<?php echo esc_attr($args['style']); ?>"
                <?php if (!empty($args['placeholder'])): ?>
                    data-placeholder="<?php echo esc_attr($args['placeholder']); ?>"
                <?php endif; ?>>
            <?php foreach ($options as $option_id => $option_title): ?>
                <option value="<?php echo esc_attr($option_id); ?>" 
                        <?php selected(in_array($option_id, $selected)); ?>>
                    <?php echo esc_html($option_title); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if ($description): ?>
            <span class="description"><?php echo wp_kses_post($description); ?></span>
        <?php endif; ?>
        <?php
    }
    
    /**
     * Obtener categorías de producto para select
     * 
     * @return array [term_id => name]
     */
    public static function get_product_categories() {
        $categories = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
        ]);
        
        if (is_wp_error($categories)) {
            return [];
        }
        
        $result = [];
        foreach ($categories as $cat) {
            $result[$cat->term_id] = $cat->name;
        }
        
        return $result;
    }
    
    /**
     * Obtener tags de producto para select
     * 
     * @return array [term_id => name]
     */
    public static function get_product_tags() {
        $tags = get_terms([
            'taxonomy' => 'product_tag',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
        ]);
        
        if (is_wp_error($tags)) {
            return [];
        }
        
        $result = [];
        foreach ($tags as $tag) {
            $result[$tag->term_id] = $tag->name;
        }
        
        return $result;
    }
}