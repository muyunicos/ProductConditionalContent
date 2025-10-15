<?php
/**
 * Componente: Gestión de Programaciones Automáticas (Cron)
 * Compatible con WordPress 6.8.3, PHP 8.2
 * 
 * @package ProductConditionalContent
 * @since 6.3.0
 */

if (!defined('ABSPATH')) exit;

final class GDM_Rules_Status_Cron {
    
    public function __construct() {
        // Registrar hooks del cron
        add_action('gdm_check_regla_schedules', [$this, 'check_schedules']);
        
        // Programar cron si no existe
        add_action('init', [$this, 'maybe_schedule_cron'], 15);
        
        // Limpiar al desactivar plugin
        register_deactivation_hook(GDM_PLUGIN_FILE, [$this, 'clear_scheduled_events']);
    }
    
    /**
     * Programar cron si no existe
     */
    public function maybe_schedule_cron() {
        if (!wp_next_scheduled('gdm_check_regla_schedules')) {
            wp_schedule_event(time(), 'hourly', 'gdm_check_regla_schedules');
        }
    }
    
    /**
     * Cron: Verificar programaciones
     */
    public function check_schedules() {
        $now = current_time('mysql');
        
        // Verificar reglas que deben activarse
        $this->activate_scheduled_rules($now);
        
        // Verificar reglas que deben desactivarse
        $this->deactivate_expired_rules($now);
        
        // Log para debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('GDM Cron: Verificación de programaciones completada a las ' . $now);
        }
    }
    
    /**
     * Activar reglas programadas
     */
    private function activate_scheduled_rules($now) {
        $args = [
            'post_type' => 'gdm_regla',
            'post_status' => 'deshabilitada',
            'posts_per_page' => -1,
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => '_gdm_programar',
                    'value' => '1',
                ],
                [
                    'key' => '_gdm_fecha_inicio',
                    'value' => $now,
                    'compare' => '<=',
                    'type' => 'DATETIME',
                ],
            ],
        ];
        
        $reglas = get_posts($args);
        
        foreach ($reglas as $regla) {
            $this->activate_rule($regla->ID, 'cron_activation');
        }
        
        if (!empty($reglas) && defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('GDM Cron: %d reglas activadas automáticamente', count($reglas)));
        }
    }
    
    /**
     * Desactivar reglas expiradas
     */
    private function deactivate_expired_rules($now) {
        $args = [
            'post_type' => 'gdm_regla',
            'post_status' => 'habilitada',
            'posts_per_page' => -1,
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => '_gdm_programar',
                    'value' => '1',
                ],
                [
                    'key' => '_gdm_habilitar_fecha_fin',
                    'value' => '1',
                ],
                [
                    'key' => '_gdm_fecha_fin',
                    'value' => $now,
                    'compare' => '<',
                    'type' => 'DATETIME',
                ],
            ],
        ];
        
        $reglas = get_posts($args);
        
        foreach ($reglas as $regla) {
            $this->deactivate_rule($regla->ID, 'cron_expiration');
        }
        
        if (!empty($reglas) && defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('GDM Cron: %d reglas desactivadas por expiración', count($reglas)));
        }
    }
    
    /**
     * Activar una regla específica
     */
    private function activate_rule($post_id, $reason = 'manual') {
        $result = wp_update_post([
            'ID' => $post_id,
            'post_status' => 'habilitada',
        ], true);
        
        if (!is_wp_error($result)) {
            $this->log_status_change($post_id, 'habilitada', $reason);
            
            // Limpiar caché
            wp_cache_delete("gdm_regla_{$post_id}", 'gdm_reglas');
            
            // Hook para extensiones
            do_action('gdm_rule_activated', $post_id, $reason);
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("GDM Cron: Regla {$post_id} activada por {$reason}");
            }
        }
    }
    
    /**
     * Desactivar una regla específica
     */
    private function deactivate_rule($post_id, $reason = 'manual') {
        $result = wp_update_post([
            'ID' => $post_id,
            'post_status' => 'deshabilitada',
        ], true);
        
        if (!is_wp_error($result)) {
            $this->log_status_change($post_id, 'deshabilitada', $reason);
            
            // Limpiar caché
            wp_cache_delete("gdm_regla_{$post_id}", 'gdm_reglas');
            
            // Hook para extensiones
            do_action('gdm_rule_deactivated', $post_id, $reason);
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("GDM Cron: Regla {$post_id} desactivada por {$reason}");
            }
        }
    }
    
    /**
     * Registrar cambio en historial
     */
    private function log_status_change($post_id, $new_status, $reason) {
        $historial = get_post_meta($post_id, '_gdm_status_history', true) ?: [];
        
        $historial[] = [
            'date' => current_time('mysql'),
            'user' => 0, // Sistema automático
            'status' => $new_status,
            'method' => $reason,
            'automated' => true,
        ];
        
        // Mantener solo últimos 50 registros para historial automático
        if (count($historial) > 50) {
            $historial = array_slice($historial, -50);
        }
        
        update_post_meta($post_id, '_gdm_status_history', $historial);
    }
    
    /**
     * Obtener