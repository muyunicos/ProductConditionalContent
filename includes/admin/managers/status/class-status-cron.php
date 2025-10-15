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
        
        // Hook para stats de debug
        add_action('wp_ajax_gdm_cron_stats', [$this, 'ajax_get_cron_stats']);
    }
    
    /**
     * Programar cron si no existe
     */
    public function maybe_schedule_cron() {
        if (!wp_next_scheduled('gdm_check_regla_schedules')) {
            wp_schedule_event(time(), 'hourly', 'gdm_check_regla_schedules');
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('GDM Cron: Evento programado para verificación horaria de reglas');
            }
        }
    }
    
    /**
     * Cron: Verificar programaciones
     */
    public function check_schedules() {
        $start_time = microtime(true);
        $now = current_time('mysql');
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("GDM Cron: Iniciando verificación de programaciones a las {$now}");
        }
        
        // Verificar reglas que deben activarse
        $activated = $this->activate_scheduled_rules($now);
        
        // Verificar reglas que deben desactivarse
        $deactivated = $this->deactivate_expired_rules($now);
        
        // Log para debugging
        $execution_time = round((microtime(true) - $start_time) * 1000, 2);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'GDM Cron: Verificación completada - %d reglas activadas, %d desactivadas en %s ms',
                $activated,
                $deactivated,
                $execution_time
            ));
        }
        
        // Almacenar estadísticas para debug
        $this->update_cron_stats($activated, $deactivated, $execution_time);
        
        // Hook para extensiones
        do_action('gdm_cron_schedules_checked', $activated, $deactivated, $execution_time);
    }
    
    /**
     * Activar reglas programadas
     */
    private function activate_scheduled_rules($now) {
        $args = [
            'post_type' => 'gdm_regla',
            'post_status' => 'deshabilitada',
            'posts_per_page' => -1,
            'fields' => 'ids', // Solo necesitamos los IDs
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
        
        $regla_ids = get_posts($args);
        $activated_count = 0;
        
        foreach ($regla_ids as $regla_id) {
            // Verificar que no esté ya activada (por si cambió entre consulta y procesamiento)
            $current_status = get_post_status($regla_id);
            if ($current_status === 'habilitada') {
                continue;
            }
            
            // Verificar que la fecha de inicio sigue siendo válida
            $fecha_inicio = get_post_meta($regla_id, '_gdm_fecha_inicio', true);
            if (!$fecha_inicio || $fecha_inicio > $now) {
                continue;
            }
            
            if ($this->activate_rule($regla_id, 'cron_activation')) {
                $activated_count++;
            }
        }
        
        if ($activated_count > 0 && defined('WP_DEBUG') && WP_DEBUG) {
            error_log("GDM Cron: {$activated_count} reglas activadas automáticamente");
        }
        
        return $activated_count;
    }
    
    /**
     * Desactivar reglas expiradas
     */
    private function deactivate_expired_rules($now) {
        $args = [
            'post_type' => 'gdm_regla',
            'post_status' => 'habilitada',
            'posts_per_page' => -1,
            'fields' => 'ids', // Solo necesitamos los IDs
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
        
        $regla_ids = get_posts($args);
        $deactivated_count = 0;
        
        foreach ($regla_ids as $regla_id) {
            // Verificar que no esté ya desactivada
            $current_status = get_post_status($regla_id);
            if ($current_status === 'deshabilitada') {
                continue;
            }
            
            // Verificar que la fecha de fin sigue siendo válida
            $fecha_fin = get_post_meta($regla_id, '_gdm_fecha_fin', true);
            $habilitar_fin = get_post_meta($regla_id, '_gdm_habilitar_fecha_fin', true);
            
            if (!$fecha_fin || $habilitar_fin !== '1' || $fecha_fin >= $now) {
                continue;
            }
            
            if ($this->deactivate_rule($regla_id, 'cron_expiration')) {
                $deactivated_count++;
            }
        }
        
        if ($deactivated_count > 0 && defined('WP_DEBUG') && WP_DEBUG) {
            error_log("GDM Cron: {$deactivated_count} reglas desactivadas por expiración");
        }
        
        return $deactivated_count;
    }
    
    /**
     * Activar una regla específica
     */
    private function activate_rule($post_id, $reason = 'manual') {
        // Hook antes de activar
        $can_activate = apply_filters('gdm_before_rule_activation', true, $post_id, $reason);
        if (!$can_activate) {
            return false;
        }
        
        $result = wp_update_post([
            'ID' => $post_id,
            'post_status' => 'habilitada',
        ], true);
        
        if (is_wp_error($result)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("GDM Cron: Error al activar regla {$post_id}: " . $result->get_error_message());
            }
            return false;
        }
        
        // Log del cambio
        $this->log_status_change($post_id, 'habilitada', $reason);
        
        // Limpiar caché
        $this->clear_rule_cache($post_id);
        
        // Hook para extensiones
        do_action('gdm_rule_activated', $post_id, $reason);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("GDM Cron: Regla {$post_id} activada por {$reason}");
        }
        
        return true;
    }
    
    /**
     * Desactivar una regla específica
     */
    private function deactivate_rule($post_id, $reason = 'manual') {
        // Hook antes de desactivar
        $can_deactivate = apply_filters('gdm_before_rule_deactivation', true, $post_id, $reason);
        if (!$can_deactivate) {
            return false;
        }
        
        $result = wp_update_post([
            'ID' => $post_id,
            'post_status' => 'deshabilitada',
        ], true);
        
        if (is_wp_error($result)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("GDM Cron: Error al desactivar regla {$post_id}: " . $result->get_error_message());
            }
            return false;
        }
        
        // Log del cambio
        $this->log_status_change($post_id, 'deshabilitada', $reason);
        
        // Limpiar caché
        $this->clear_rule_cache($post_id);
        
        // Hook para extensiones
        do_action('gdm_rule_deactivated', $post_id, $reason);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("GDM Cron: Regla {$post_id} desactivada por {$reason}");
        }
        
        return true;
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
            'user_agent' => 'GDM-Cron-System',
        ];
        
        // Mantener solo últimos 50 registros para historial automático
        if (count($historial) > 50) {
            $historial = array_slice($historial, -50);
        }
        
        update_post_meta($post_id, '_gdm_status_history', $historial);
    }
    
    /**
     * Limpiar caché de regla
     */
    private function clear_rule_cache($post_id) {
        // Cache del plugin
        wp_cache_delete("gdm_regla_{$post_id}", 'gdm_reglas');
        
        // Cache de object cache si está disponible
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group('gdm_reglas');
        }
        
        // Hook para limpiar caches personalizados
        do_action('gdm_clear_rule_cache', $post_id);
    }
    
    /**
     * Actualizar estadísticas del cron
     */
    private function update_cron_stats($activated, $deactivated, $execution_time) {
        $stats = get_option('gdm_cron_stats', [
            'last_run' => '',
            'total_runs' => 0,
            'total_activated' => 0,
            'total_deactivated' => 0,
            'avg_execution_time' => 0,
            'last_execution_time' => 0,
        ]);
        
        $stats['last_run'] = current_time('mysql');
        $stats['total_runs']++;
        $stats['total_activated'] += $activated;
        $stats['total_deactivated'] += $deactivated;
        $stats['last_execution_time'] = $execution_time;
        
        // Calcular tiempo promedio de ejecución
        if ($stats['total_runs'] > 1) {
            $stats['avg_execution_time'] = (($stats['avg_execution_time'] * ($stats['total_runs'] - 1)) + $execution_time) / $stats['total_runs'];
        } else {
            $stats['avg_execution_time'] = $execution_time;
        }
        
        update_option('gdm_cron_stats', $stats);
    }
    
    /**
     * Obtener reglas con programación próxima
     */
    public function get_upcoming_schedules($limit = 10) {
        $now = current_time('mysql');
        
        $args = [
            'post_type' => 'gdm_regla',
            'post_status' => ['habilitada', 'deshabilitada'],
            'posts_per_page' => $limit,
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => '_gdm_programar',
                    'value' => '1',
                ],
                [
                    'relation' => 'OR',
                    [
                        'key' => '_gdm_fecha_inicio',
                        'value' => $now,
                        'compare' => '>',
                        'type' => 'DATETIME',
                    ],
                    [
                        'relation' => 'AND',
                        [
                            'key' => '_gdm_habilitar_fecha_fin',
                            'value' => '1',
                        ],
                        [
                            'key' => '_gdm_fecha_fin',
                            'value' => $now,
                            'compare' => '>',
                            'type' => 'DATETIME',
                        ],
                    ],
                ],
            ],
            'orderby' => 'meta_value',
            'meta_key' => '_gdm_fecha_inicio',
            'order' => 'ASC',
        ];
        
        return get_posts($args);
    }
    
    /**
     * AJAX: Obtener estadísticas del cron
     */
    public function ajax_get_cron_stats() {
        check_ajax_referer('gdm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Sin permisos suficientes', 'product-conditional-content'));
        }
        
        $stats = get_option('gdm_cron_stats', []);
        $next_scheduled = wp_next_scheduled('gdm_check_regla_schedules');
        $upcoming = $this->get_upcoming_schedules(5);
        
        wp_send_json_success([
            'stats' => $stats,
            'next_scheduled' => $next_scheduled ? date_i18n('Y-m-d H:i:s', $next_scheduled) : null,
            'upcoming_schedules' => array_map(function($post) {
                return [
                    'id' => $post->ID,
                    'title' => $post->post_title,
                    'status' => $post->post_status,
                    'fecha_inicio' => get_post_meta($post->ID, '_gdm_fecha_inicio', true),
                    'fecha_fin' => get_post_meta($post->ID, '_gdm_fecha_fin', true),
                ];
            }, $upcoming),
        ]);
    }
    
    /**
     * Limpiar eventos programados
     */
    public function clear_scheduled_events() {
        $timestamp = wp_next_scheduled('gdm_check_regla_schedules');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'gdm_check_regla_schedules');
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('GDM Cron: Eventos programados limpiados al desactivar plugin');
            }
        }
    }
    
    /**
     * Forzar ejecución del cron (para debug)
     */
    public function force_check_schedules() {
        if (!current_user_can('manage_options')) {
            return false;
        }
        
        return $this->check_schedules();
    }
    
    /**
     * Verificar si el cron está funcionando correctamente
     */
    public function is_cron_working() {
        $stats = get_option('gdm_cron_stats', []);
        
        if (empty($stats['last_run'])) {
            return false;
        }
        
        $last_run = strtotime($stats['last_run']);
        $now = current_time('timestamp');
        
        // Si ha pasado más de 2 horas desde la última ejecución, algo puede estar mal
        return ($now - $last_run) < (2 * HOUR_IN_SECONDS);
    }
    
    /**
     * Obtener información de debug del cron
     */
    public function get_debug_info() {
        return [
            'is_working' => $this->is_cron_working(),
            'next_scheduled' => wp_next_scheduled('gdm_check_regla_schedules'),
            'stats' => get_option('gdm_cron_stats', []),
            'wp_cron_disabled' => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON,
            'alternative_cron' => defined('ALTERNATE_WP_CRON') && ALTERNATE_WP_CRON,
        ];
    }
}