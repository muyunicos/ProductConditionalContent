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
     * @param array $array Array a sanitizar
     * @return array Array sanitizado
     */
    public static function sanitize_int_array($array) {
        if (!is_array($array)) {
            return [];
        }
        return array_map('intval', $array);
    }
    
    /**
     * Sanitizar array de strings
     * 
     * @param array $array Array a sanitizar
     * @return array Array sanitizado
     */
    public static function sanitize_text_array($array) {
        if (!is_array($array)) {
            return [];
        }
        return array_map('sanitize_text_field', $array);
    }
    
    /**
     * Obtener opciones disponibles (CPT gdm_opcion)
     * 
     * @return array Array de WP_Post
     */
    public static function get_available_opciones() {
        static $cache = null;
        
        if ($cache !== null) {
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
     * @return array Array de WP_Post
     */
    public static function get_available_reglas($exclude_id = 0) {
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
        
        return get_posts($args);
    }
    
    /**
     * Renderizar campo select múltiple con WooCommerce enhanced select
     * 
     * @param string $id ID del campo
     * @param string $name Nombre del campo
     * @param array $options Opciones [id => titulo]
     * @param array $selected IDs seleccionados
     * @param string $description Descripción del campo
     */
    public static function render_enhanced_select($id, $name, $options, $selected = [], $description = '') {
        ?>
        <select id="<?php echo esc_attr($id); ?>" 
                name="<?php echo esc_attr($name); ?>[]" 
                class="wc-enhanced-select" 
                multiple="multiple" 
                style="width:50%;">
            <?php foreach ($options as $option_id => $option_title): ?>
                <option value="<?php echo esc_attr($option_id); ?>" 
                        <?php selected(in_array($option_id, $selected)); ?>>
                    <?php echo esc_html($option_title); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if ($description): ?>
            <span class="description"><?php echo esc_html($description); ?></span>
        <?php endif; ?>
        <?php
    }
}