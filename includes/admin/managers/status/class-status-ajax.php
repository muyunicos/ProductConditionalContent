<?php
/**
 * Componente: Gestión de AJAX para Estados de Reglas
 * Compatible con WordPress 6.8.3, PHP 8.2
 * 
 * @package ProductConditionalContent
 * @since 6.3.0
 */

if (!defined('ABSPATH')) exit;

final class GDM_Rules_Status_Ajax {
    
    public function __construct() {
        // AJAX Handlers
        add_action('wp_ajax_gdm_toggle_status', [$this, 'ajax_toggle_status']);
        add_action('wp_ajax_gdm_bulk_toggle_status', [$this, 'ajax_bulk_toggle_status']);
        add_action('wp_ajax_gdm_get_regla_preview', [$this, 'ajax_get_regla_preview']);
        add_action('wp_ajax_gdm_save_metabox_status', [$this, 'ajax_save_metabox_status']);
        
        // Registrar el post type en el handler de toggle
        add_action('init', [$this, 'register_toggle_handler'], 15);
    }
    
    /**
     * Registrar en el sistema de toggle
     */
    public function register_toggle_handler() {
        if (class_exists('GDM_Toggle_AJAX_Handler')) {
            GDM_Toggle_AJAX_Handler::register_post_type('gdm_regla', [
                'enabled_status' => 'habilitada',
                'disabled_status' => 'deshabilitada',
                'capability' => 'edit_posts',
                'before_toggle' => [$this, 'before_toggle_callback'],
                'after_toggle' => [$this, 'after_toggle_callback'],
            ]);
        }
    }
    
    /**
     * Callback antes del toggle
     */
    public function before_toggle_callback($post_id, $new_status, $post) {
        // Verificar si hay conflictos de programación
        if ($new_status === 'habilitada') {
            $programar = get_post_meta($post_id, '_gdm_programar', true);
            $fecha_inicio = get_post_meta($post_id, '_gdm_fecha_inicio', true);
            
            if ($programar === '1' && $fecha_inicio) {
                $now = current_time('mysql');
                if ($fecha_inicio > $now) {
                    return new WP_Error(
                        'scheduled_rule', 
                        __('Esta regla está programada para activarse en el futuro. ¿Activar ahora de todos modos?', 'product-conditional-content')
                    );
                }
            }
        }
        
        return true;
    }
    
    /**
     * Callback después del toggle
     */
    public function after_toggle_callback($post_id, $new_status, $post) {
        // Limpiar caché
        wp_cache_delete("gdm_regla_{$post_id}", 'gdm_reglas');
        
        // Log del cambio
        $this->log_status_change($post_id, $new_status, 'manual_toggle');
        
        // Hook para extensiones
        do_action('gdm_rule_status_changed', $post_id, $new_status, 'manual_toggle');
    }
    
    /**
     * AJAX: Toggle individual
     */
    public function ajax_toggle_status() {
        check_ajax_referer('gdm_toggle_nonce', 'nonce');
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $new_status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        
        if (!$post_id || !in_array($new_status, ['habilitada', 'deshabilitada'])) {
            wp_send_json_error([
                'message' => __('Datos inválidos', 'product-conditional-content'),
            ]);
        }
        
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'gdm_regla') {
            wp_send_json_error([
                'message' => __('Regla no encontrada', 'product-conditional-content'),
            ]);
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error([
                'message' => __('No tienes permisos suficientes', 'product-conditional-content'),
            ]);
        }
        
        // Ejecutar callbacks
        $before_result = $this->before_toggle_callback($post_id, $new_status, $post);
        if (is_wp_error($before_result)) {
            wp_send_json_error([
                'message' => $before_result->get_error_message(),
                'code' => $before_result->get_error_code(),
            ]);
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
        
        // Ejecutar callback post-cambio
        $this->after_toggle_callback($post_id, $new_status, $post);
        
        // Calcular sub-estado
        $core = GDM_Regla_Status_Manager::get_component('core');
        $sub_status = $core ? $core->calculate_substatus(null, $post_id, $new_status) : null;
        
        wp_send_json_success([
            'post_id' => $post_id,
            'status' => $new_status,
            'sub_status' => $sub_status,
            'message' => sprintf(
                __('Estado actualizado a: %s', 'product-conditional-content'),
                $new_status === 'habilitada' ? __('Habilitada', 'product-conditional-content') : __('Deshabilitada', 'product-conditional-content')
            ),
        ]);
    }
    
    /**
     * AJAX: Toggle masivo
     */
    public function ajax_bulk_toggle_status() {
        check_ajax_referer('gdm_toggle_nonce', 'nonce');
        
        $post_ids = isset($_POST['post_ids']) ? array_map('intval', $_POST['post_ids']) : [];
        $new_status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        
        if (empty($post_ids) || !in_array($new_status, ['habilitada', 'deshabilitada'])) {
            wp_send_json_error([
                'message' => __('Datos inválidos', 'product-conditional-content'),
            ]);
        }
        
        $updated_count = 0;
        $errors = [];
        
        foreach ($post_ids as $post_id) {
            $post = get_post($post_id);
            
            if (!$post || $post->post_type !== 'gdm_regla') {
                $errors[] = sprintf(__('Regla %d no encontrada', 'product-conditional-content'), $post_id);
                continue;
            }
            
            if (!current_user_can('edit_post', $post_id)) {
                $errors[] = sprintf(__('Sin permisos para regla %d', 'product-conditional-content'), $post_id);
                continue;
            }
            
            // Ejecutar callbacks
            $before_result = $this->before_toggle_callback($post_id, $new_status, $post);
            if (is_wp_error($before_result)) {
                $errors[] = sprintf(__('Regla %d: %s', 'product-conditional-content'), $post_id, $before_result->get_error_message());
                continue;
            }
            
            // Actualizar
            $updated = wp_update_post([
                'ID' => $post_id,
                'post_status' => $new_status,
            ], true);
            
            if (is_wp_error($updated)) {
                $errors[] = sprintf(__('Error en regla %d: %s', 'product-conditional-content'), $post_id, $updated->get_error_message());
                continue;
            }
            
            // Callback post-cambio
            $this->after_toggle_callback($post_id, $new_status, $post);
            $updated_count++;
        }
        
        $status_label = $new_status === 'habilitada' ? __('habilitadas', 'product-conditional-content') : __('deshabilitadas', 'product-conditional-content');
        
        wp_send_json_success([
            'updated_count' => $updated_count,
            'total_count' => count($post_ids),
            'errors' => $errors,
            'message' => sprintf(
                _n('%d regla %s correctamente.', '%d reglas %s correctamente.', $updated_count, 'product-conditional-content'),
                $updated_count,
                $status_label
            ),
        ]);
    }
    
    /**
     * AJAX: Obtener preview de regla
     */
    public function ajax_get_regla_preview() {
        check_ajax_referer('gdm_preview_nonce', 'nonce');
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        
        if (!$post_id) {
            wp_send_json_error([
                'message' => __('ID de regla no válido', 'product-conditional-content'),
            ]);
        }
        
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'gdm_regla') {
            wp_send_json_error([
                'message' => __('Regla no encontrada', 'product-conditional-content'),
            ]);
        }
        
        // Obtener datos de la regla
        $status = get_post_status($post_id);
        $aplicar_a = get_post_meta($post_id, '_gdm_aplicar_a', true) ?: [];
        $reutilizable = get_post_meta($post_id, '_gdm_reutilizable', true);
        $programar = get_post_meta($post_id, '_gdm_programar', true);
        $fecha_inicio = get_post_meta($post_id, '_gdm_fecha_inicio', true);
        $fecha_fin = get_post_meta($post_id, '_gdm_fecha_fin', true);
        $habilitar_fin = get_post_meta($post_id, '_gdm_habilitar_fecha_fin', true);
        
        // Calcular sub-estado
        $core = GDM_Regla_Status_Manager::get_component('core');
        $sub_status = $core ? $core->calculate_substatus(null, $post_id, $status) : null;
        
        // Obtener módulos aplicados
        $modulos_info = [];
        if (class_exists('GDM_Module_Manager')) {
            $module_manager = GDM_Module_Manager::instance();
            $modules = $module_manager->get_modules_ordered();
            
            foreach ($aplicar_a as $module_id) {
                if (isset($modules[$module_id])) {
                    $modulos_info[] = [
                        'id' => $module_id,
                        'label' => $modules[$module_id]['label'],
                        'icon' => $modules[$module_id]['icon'],
                    ];
                }
            }
        }
        
        wp_send_json_success([
            'regla' => [
                'id' => $post_id,
                'titulo' => $post->post_title,
                'status' => $status,
                'sub_status' => $sub_status,
                'reutilizable' => $reutilizable === '1',
                'aplicar_a' => $aplicar_a,
                'modulos_info' => $modulos_info,
                'programacion' => [
                    'programar' => $programar === '1',
                    'fecha_inicio' => $fecha_inicio,
                    'fecha_fin' => $fecha_fin,
                    'habilitar_fin' => $habilitar_fin === '1',
                ],
                'fechas_legibles' => [
                    'inicio' => $fecha_inicio ? date_i18n('d M Y H:i', strtotime($fecha_inicio)) : '',
                    'fin' => $fecha_fin ? date_i18n('d M Y H:i', strtotime($fecha_fin)) : '',
                ],
            ],
        ]);
    }
    
    /**
     * AJAX: Guardar estado desde metabox
     */
    public function ajax_save_metabox_status() {
        check_ajax_referer('gdm_publish_metabox_nonce', 'nonce');
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $is_enabled = isset($_POST['enabled']) && $_POST['enabled'] === 'true';
        $programar = isset($_POST['programar']) && $_POST['programar'] === 'true';
        $fecha_inicio = isset($_POST['fecha_inicio']) ? sanitize_text_field($_POST['fecha_inicio']) : '';
        $fecha_fin = isset($_POST['fecha_fin']) ? sanitize_text_field($_POST['fecha_fin']) : '';
        $habilitar_fin = isset($_POST['habilitar_fin']) && $_POST['habilitar_fin'] === 'true';
        
        if (!$post_id) {
            wp_send_json_error([
                'message' => __('ID de regla no válido', 'product-conditional-content'),
            ]);
        }
        
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'gdm_regla') {
            wp_send_json_error([
                'message' => __('Regla no encontrada', 'product-conditional-content'),
            ]);
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error([
                'message' => __('No tienes permisos suficientes', 'product-conditional-content'),
            ]);
        }
        
        // Guardar estado
        $new_status = $is_enabled ? 'habilitada' : 'deshabilitada';
        wp_update_post([
            'ID' => $post_id,
            'post_status' => $new_status,
        ]);
        
        // Guardar programación
        update_post_meta($post_id, '_gdm_programar', $programar ? '1' : '0');
        
        if ($programar && !empty($fecha_inicio)) {
            update_post_meta($post_id, '_gdm_fecha_inicio', date('Y-m-d H:i:s', strtotime($fecha_inicio)));
        } else {
            delete_post_meta($post_id, '_gdm_fecha_inicio');
        }
        
        update_post_meta($post_id, '_gdm_habilitar_fecha_fin', $habilitar_fin ? '1' : '0');
        
        if ($programar && $habilitar_fin && !empty($fecha_fin)) {
            update_post_meta($post_id, '_gdm_fecha_fin', date('Y-m-d H:i:s', strtotime($fecha_fin)));
        } else {
            delete_post_meta($post_id, '_gdm_fecha_fin');
        }
        
        // Limpiar caché y log
        wp_cache_delete("gdm_regla_{$post_id}", 'gdm_reglas');
        $this->log_status_change($post_id, $new_status, 'metabox_save');
        
        // Calcular sub-estado actualizado
        $core = GDM_Regla_Status_Manager::get_component('core');
        $sub_status = $core ? $core->calculate_substatus(null, $post_id, $new_status) : null;
        
        wp_send_json_success([
            'post_id' => $post_id,
            'status' => $new_status,
            'sub_status' => $sub_status,
            'message' => __('Estado guardado correctamente', 'product-conditional-content'),
        ]);
    }
    
    /**
     * Registrar cambio en historial
     */
    private function log_status_change($post_id, $new_status, $method) {
        $historial = get_post_meta($post_id, '_gdm_status_history', true) ?: [];
        
        $historial[] = [
            'date' => current_time('mysql'),
            'user' => get_current_user_id(),
            'status' => $new_status,
            'method' => $method,
            'automated' => false,
        ];
        
        // Mantener solo últimos 50 registros
        if (count($historial) > 50) {
            $historial = array_slice($historial, -50);
        }
        
        update_post_meta($post_id, '_gdm_status_history', $historial);
    }
}