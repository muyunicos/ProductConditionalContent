<?php
/**
 * Handler AJAX para Toggle de Estados
 * Componente reutilizable para cualquier CPT que necesite toggle
 * Compatible con WordPress 6.8.3, PHP 8.2
 * 
 * @package ProductConditionalContent
 * @since 5.0.3
 */

if (!defined('ABSPATH')) exit;

final class GDM_Toggle_AJAX_Handler {
    
    /**
     * Post types registrados para toggle
     */
    private static $registered_post_types = [];
    
    /**
     * Registrar un post type para usar toggle
     * 
     * @param string $post_type Tipo de post
     * @param array $config Configuración del toggle
     */
    public static function register_post_type($post_type, $config = []) {
        self::$registered_post_types[$post_type] = wp_parse_args($config, [
            'enabled_status' => 'habilitada',
            'disabled_status' => 'deshabilitada',
            'capability' => 'edit_posts',
            'before_toggle' => null,
            'after_toggle' => null,
        ]);
    }
    
    /**
     * Inicializar hooks
     */
    public static function init() {
        add_action('wp_ajax_gdm_toggle_status', [__CLASS__, 'ajax_toggle_status']);
    }
    
    /**
     * AJAX: Cambiar estado del toggle
     */
    public static function ajax_toggle_status() {
        // Verificar nonce
        check_ajax_referer('gdm_toggle_nonce', 'nonce');
        
        // Obtener datos
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $new_status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        
        if (!$post_id || !$new_status) {
            wp_send_json_error([
                'message' => __('Datos inválidos', 'product-conditional-content'),
            ]);
        }
        
        // Verificar post
        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error([
                'message' => __('Post no encontrado', 'product-conditional-content'),
            ]);
        }
        
        // Verificar si el post type está registrado
        if (!isset(self::$registered_post_types[$post->post_type])) {
            wp_send_json_error([
                'message' => __('Post type no soportado', 'product-conditional-content'),
            ]);
        }
        
        $config = self::$registered_post_types[$post->post_type];
        
        // Verificar permisos
        if (!current_user_can($config['capability'], $post_id)) {
            wp_send_json_error([
                'message' => __('No tienes permisos suficientes', 'product-conditional-content'),
            ]);
        }
        
        // Validar estado
        $allowed_statuses = [$config['enabled_status'], $config['disabled_status']];
        if (!in_array($new_status, $allowed_statuses)) {
            wp_send_json_error([
                'message' => __('Estado inválido', 'product-conditional-content'),
            ]);
        }
        
        // Callback antes de cambiar (si existe)
        if (is_callable($config['before_toggle'])) {
            $result = call_user_func($config['before_toggle'], $post_id, $new_status, $post);
            if (is_wp_error($result)) {
                wp_send_json_error([
                    'message' => $result->get_error_message(),
                ]);
            }
        }
        
        // Actualizar estado
        $updated = wp_update_post([
            'ID' => $post_id,
            'post_status' => $new_status,
        ], true);
        
        if (is_wp_error($updated)) {
            wp_send_json_error([
                'message' => $updated->get_error_message(),
            ]);
        }
        
        // Callback después de cambiar (si existe)
        if (is_callable($config['after_toggle'])) {
            call_user_func($config['after_toggle'], $post_id, $new_status, $post);
        }
        
        // Registrar en historial (si el post type lo soporta)
        self::log_status_change($post_id, $new_status, $post->post_type);
        
        // Calcular sub-estado (si aplica)
        $sub_status_data = apply_filters("gdm_calculate_substatus_{$post->post_type}", null, $post_id, $new_status);
        
        wp_send_json_success([
            'post_id' => $post_id,
            'status' => $new_status,
            'sub_status' => $sub_status_data,
            'message' => sprintf(
                __('Estado actualizado a: %s', 'product-conditional-content'),
                $new_status === $config['enabled_status'] ? __('Habilitada', 'product-conditional-content') : __('Deshabilitada', 'product-conditional-content')
            ),
        ]);
    }
    
    /**
     * Registrar cambio en historial
     */
    private static function log_status_change($post_id, $new_status, $post_type) {
        $historial = get_post_meta($post_id, '_gdm_status_history', true) ?: [];
        
        $historial[] = [
            'date' => current_time('mysql'),
            'user' => get_current_user_id(),
            'status' => $new_status,
            'method' => 'toggle_ajax',
        ];
        
        // Mantener solo últimos 20 registros
        if (count($historial) > 20) {
            $historial = array_slice($historial, -20);
        }
        
        update_post_meta($post_id, '_gdm_status_history', $historial);
    }
}

GDM_Toggle_AJAX_Handler::init();