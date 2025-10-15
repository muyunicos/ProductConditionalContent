<?php
/**
 * Funciones Helper para Admin v6.2.3 MEJORADO
 * Compatible con WordPress 6.8.3, PHP 8.2, WooCommerce 10.2.2
 * 
 * MEJORAS v6.2.3:
 * - Método validate_metabox_save() centralizado
 * - Debug configurable vía WP_DEBUG
 * - Sanitización mejorada
 * 
 * @package ProductConditionalContent
 * @since 6.2.3
 * @date 2025-10-15
 */

if (!defined('ABSPATH')) exit;

final class GDM_Admin_Helpers {
    
    /**
     * ✅ NUEVO: Validación centralizada de guardado de metaboxes
     * 
     * @param int $post_id ID del post
     * @param WP_Post $post Objeto del post
     * @param string $nonce_field Nombre del campo nonce
     * @param string $nonce_action Acción del nonce
     * @param string $post_type Post type esperado
     * @param bool $log_errors Si debe registrar errores en log (default: true si WP_DEBUG está activo)
     * @return bool True si todas las validaciones pasaron, false si debe abortar
     */
    public static function validate_metabox_save($post_id, $post, $nonce_field, $nonce_action, $post_type, $log_errors = null) {
        // Determinar si debe hacer logging (por defecto solo en debug)
        if ($log_errors === null) {
            $log_errors = defined('WP_DEBUG') && WP_DEBUG;
        }
        
        // ✅ VALIDACIÓN 1: Evitar autosave (PRIMERO)
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            if ($log_errors) {
                error_log(sprintf(
                    '⚠️ GDM: Autosave detectado, saltando guardado (post_id: %d, post_type: %s)',
                    $post_id,
                    $post_type
                ));
            }
            return false;
        }
        
        // ✅ VALIDACIÓN 2: Verificar que es el post type correcto
        if ($post->post_type !== $post_type) {
            if ($log_errors) {
                error_log(sprintf(
                    '⚠️ GDM: Post type incorrecto (esperado: %s, recibido: %s, post_id: %d)',
                    $post_type,
                    $post->post_type,
                    $post_id
                ));
            }
            return false;
        }
        
        // ✅ VALIDACIÓN 3: Verificar que existe el nonce
        if (!isset($_POST[$nonce_field])) {
            if ($log_errors) {
                error_log(sprintf(
                    '❌ GDM: Campo nonce "%s" NO existe en $_POST (post_id: %d)',
                    $nonce_field,
                    $post_id
                ));
                
                // Si es un heartbeat/ajax, es normal que no haya nonce
                if (isset($_POST['action']) && in_array($_POST['action'], ['heartbeat', 'autosave'])) {
                    error_log('ℹ️ GDM: Acción heartbeat/autosave detectada, esto es normal');
                }
            }
            return false;
        }
        
        // ✅ VALIDACIÓN 4: Verificar validez del nonce
        $nonce_value = sanitize_text_field($_POST[$nonce_field]);
        $nonce_valid = wp_verify_nonce($nonce_value, $nonce_action);
        
        if (!$nonce_valid) {
            if ($log_errors) {
                error_log(sprintf(
                    '❌ GDM: Nonce inválido para "%s" (post_id: %d, acción: %s)',
                    $nonce_field,
                    $post_id,
                    $nonce_action
                ));
            }
            return false;
        }
        
        // ✅ VALIDACIÓN 5: Verificar permisos del usuario
        if (!current_user_can('edit_post', $post_id)) {
            if ($log_errors) {
                error_log(sprintf(
                    '❌ GDM: Usuario sin permisos para editar (post_id: %d, usuario: %s)',
                    $post_id,
                    wp_get_current_user()->user_login
                ));
            }
            return false;
        }
        
        // ✅ Todas las validaciones pasadas
        if ($log_errors) {
            error_log(sprintf(
                '✅ GDM: Validaciones OK para guardar %s (post_id: %d)',
                $post_type,
                $post_id
            ));
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
        
        return array_map('absint', array_filter($array, function($value) {
            return is_numeric($value) && $value > 0;
        }));
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
     * ✅ NUEVO: Sanitizar array de slugs
     * 
     * @param array|mixed $array Array a sanitizar
     * @return array Array sanitizado
     */
    public static function sanitize_slug_array($array) {
        if (!is_array($array)) {
            return [];
        }
        
        return array_map('sanitize_title', array_filter($array, function($value) {
            return !empty($value);
        }));
    }
    
    /**
     * ✅ NUEVO: Validar y sanitizar fecha/hora
     * 
     * @param string $datetime_string Fecha en formato string
     * @return string Fecha sanitizada en formato MySQL o vacío si inválido
     */
    public static function sanitize_datetime($datetime_string) {
        if (empty($datetime_string)) {
            return '';
        }
        
        $timestamp = strtotime($datetime_string);
        
        if ($timestamp === false) {
            return '';
        }
        
        return date('Y-m-d H:i:s', $timestamp);
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
            'post_status' => ['publish', 'habilitada', 'deshabilitada'],
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
                $reutilizable = get_post_meta($regla->ID, '_gdm_reutilizable', true);
                return $reutilizable === '1';
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