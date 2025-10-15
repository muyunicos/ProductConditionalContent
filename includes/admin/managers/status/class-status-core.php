<?php
/**
 * Componente Core: Registro de Estados y Toggle Handler
 * Compatible con WordPress 6.8.3, PHP 8.2
 * 
 * @package ProductConditionalContent
 * @since 6.3.0
 */

if (!defined('ABSPATH')) exit;

final class GDM_Rules_Status_Core {
    
    public function __construct() {
        // Registrar estados personalizados
        add_action('init', [$this, 'register_custom_statuses'], 10);
        add_action('init', [$this, 'register_toggle_handler'], 11);
        
        // Convertir publish a habilitada automáticamente
        add_filter('wp_insert_post_data', [$this, 'force_custom_status'], 10, 2);
        
        // Cambiar texto del botón
        add_filter('gettext', [$this, 'change_publish_button_text'], 10, 2);
        
        // Display post states
        add_filter('display_post_states', [$this, 'display_post_states'], 10, 2);
    }
    
    /**
     * Registrar estados personalizados
     */
    public function register_custom_statuses() {
        register_post_status('habilitada', [
            'label'                     => _x('Habilitada', 'post status', 'product-conditional-content'),
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop(
                'Habilitada <span class="count">(%s)</span>',
                'Habilitadas <span class="count">(%s)</span>',
                'product-conditional-content'
            ),
        ]);
        
        register_post_status('deshabilitada', [
            'label'                     => _x('Deshabilitada', 'post status', 'product-conditional-content'),
            'public'                    => false,
            'exclude_from_search'       => true,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop(
                'Deshabilitada <span class="count">(%s)</span>',
                'Deshabilitadas <span class="count">(%s)</span>',
                'product-conditional-content'
            ),
        ]);
    }
    
    /**
     * Registrar en el handler de toggle
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
     * Callback antes de cambiar toggle
     */
    public function before_toggle_callback($post_id, $new_status, $post) {
        return true;
    }
    
    /**
     * Callback después de cambiar toggle
     */
    public function after_toggle_callback($post_id, $new_status, $post) {
        wp_cache_delete("gdm_regla_{$post_id}", 'gdm_reglas');
    }
    
    /**
     * Calcular sub-estado de una regla
     */
    public function calculate_substatus($default, $post_id, $status) {
        if ($status === 'deshabilitada') {
            return [
                'class' => 'status-disabled',
                'label' => __('Deshabilitada', 'product-conditional-content'),
                'description' => __('No se activará', 'product-conditional-content'),
            ];
        }
        
        $programar = get_post_meta($post_id, '_gdm_programar', true);
        $fecha_inicio = get_post_meta($post_id, '_gdm_fecha_inicio', true);
        $fecha_fin = get_post_meta($post_id, '_gdm_fecha_fin', true);
        $habilitar_fin = get_post_meta($post_id, '_gdm_habilitar_fecha_fin', true);
        
        $now = current_time('mysql');
        
        if ($programar !== '1' || !$fecha_inicio) {
            return [
                'class' => 'status-active',
                'label' => __('Habilitada (activa)', 'product-conditional-content'),
                'description' => __('Funcionando ahora', 'product-conditional-content'),
            ];
        }
        
        if ($fecha_inicio > $now) {
            $dias = ceil((strtotime($fecha_inicio) - strtotime($now)) / 86400);
            return [
                'class' => 'status-scheduled',
                'label' => __('Habilitada (programada)', 'product-conditional-content'),
                'description' => sprintf(
                    _n('Se activa en %d día', 'Se activa en %d días', $dias, 'product-conditional-content'),
                    $dias
                ),
            ];
        }
        
        if ($habilitar_fin === '1' && $fecha_fin) {
            if ($fecha_fin < $now) {
                return [
                    'class' => 'status-expired',
                    'label' => __('Habilitada (terminada)', 'product-conditional-content'),
                    'description' => __('Fecha de fin alcanzada', 'product-conditional-content'),
                ];
            }
            
            $horas = (strtotime($fecha_fin) - strtotime($now)) / 3600;
            if ($horas < 24) {
                return [
                    'class' => 'status-expiring',
                    'label' => __('Habilitada (activa)', 'product-conditional-content'),
                    'description' => sprintf(
                        __('Expira en %d horas', 'product-conditional-content'),
                        ceil($horas)
                    ),
                ];
            }
            
            $dias = ceil((strtotime($fecha_fin) - strtotime($now)) / 86400);
            return [
                'class' => 'status-active',
                'label' => __('Habilitada (activa)', 'product-conditional-content'),
                'description' => sprintf(
                    _n('Termina en %d día', 'Termina en %d días', $dias, 'product-conditional-content'),
                    $dias
                ),
            ];
        }
        
        return [
            'class' => 'status-active',
            'label' => __('Habilitada (activa)', 'product-conditional-content'),
            'description' => __('Sin fecha de finalización', 'product-conditional-content'),
        ];
    }
    
    /**
     * Forzar estado personalizado
     */
    public function force_custom_status($data, $postarr) {
        if ($data['post_type'] !== 'gdm_regla') {
            return $data;
        }
        
        if ($data['post_status'] === 'publish') {
            $data['post_status'] = 'habilitada';
        }
        
        return $data;
    }
    
    /**
     * Cambiar texto del botón Publicar
     */
    public function change_publish_button_text($translation, $text) {
        global $post, $pagenow;
        
        if (!in_array($pagenow, ['post.php', 'post-new.php']) || !$post || $post->post_type !== 'gdm_regla') {
            return $translation;
        }
        
        if ($text === 'Publicar' || $text === 'Publish') {
            return __('Guardar', 'product-conditional-content');
        }
        
        return $translation;
    }
    
    /**
     * Display post states
     */
    public function display_post_states($states, $post) {
        if ($post->post_type !== 'gdm_regla') {
            return $states;
        }
        
        unset($states['publish']);
        return $states;
    }
    
    /**
     * Obtener información de estado
     */
    public function get_estado_info($is_enabled, $programar, $fecha_inicio, $fecha_fin, $habilitar_fin) {
        $now = current_time('mysql');
        $estado_dinamico = $this->calcular_estado_dinamico($programar, $fecha_inicio, $fecha_fin, $habilitar_fin, $now);
        
        if ($is_enabled) {
            return [
                'class' => $estado_dinamico['class'],
                'titulo' => __('Habilitada', 'product-conditional-content'),
                'descripcion' => $estado_dinamico['texto'],
                'boton' => 'guardar',
            ];
        } else {
            return [
                'class' => 'status-disabled',
                'titulo' => __('Deshabilitada', 'product-conditional-content'),
                'descripcion' => __('La regla está desactivada', 'product-conditional-content'),
                'boton' => 'guardar',
            ];
        }
    }
    
    /**
     * Calcular estado dinámico
     */
    private function calcular_estado_dinamico($programar, $fecha_inicio, $fecha_fin, $habilitar_fin, $now) {
        if ($programar !== '1' || !$fecha_inicio) {
            return [
                'class' => 'status-active',
                'texto' => __('Activa', 'product-conditional-content'),
            ];
        }
        
        $inicio_timestamp = strtotime($fecha_inicio);
        $now_timestamp = strtotime($now);
        
        if ($inicio_timestamp > $now_timestamp) {
            $diff = $inicio_timestamp - $now_timestamp;
            $tiempo_texto = $this->calcular_tiempo_legible($diff);
            
            $texto = sprintf(__('Programada, inicia en %s', 'product-conditional-content'), $tiempo_texto);
            
            if ($habilitar_fin === '1' && $fecha_fin) {
                $fin_timestamp = strtotime($fecha_fin);
                $diff_fin = $fin_timestamp - $now_timestamp;
                $tiempo_fin = $this->calcular_tiempo_legible($diff_fin);
                $texto .= sprintf(__(', termina en %s', 'product-conditional-content'), $tiempo_fin);
            }
            
            return [
                'class' => 'status-scheduled',
                'texto' => $texto,
            ];
        }
        
        if ($habilitar_fin === '1' && $fecha_fin) {
            $fin_timestamp = strtotime($fecha_fin);
            
            if ($fin_timestamp < $now_timestamp) {
                return [
                    'class' => 'status-expired',
                    'texto' => __('Inactiva, ya terminó', 'product-conditional-content'),
                ];
            }
            
            $diff_fin = $fin_timestamp - $now_timestamp;
            $tiempo_fin = $this->calcular_tiempo_legible($diff_fin);
            
            return [
                'class' => 'status-active',
                'texto' => sprintf(__('Activa, termina en %s', 'product-conditional-content'), $tiempo_fin),
            ];
        }
        
        return [
            'class' => 'status-active',
            'texto' => __('Activa', 'product-conditional-content'),
        ];
    }
    
    /**
     * Calcular tiempo legible
     */
    private function calcular_tiempo_legible($segundos) {
        $minutos = round($segundos / 60);
        $horas = round($segundos / 3600);
        $dias = round($segundos / 86400);
        $semanas = round($segundos / 604800);
        $meses = round($segundos / 2592000);
        
        if ($minutos < 60) {
            return sprintf(_n('%d minuto', '%d minutos', $minutos, 'product-conditional-content'), $minutos);
        } elseif ($horas < 24) {
            return sprintf(_n('%d hora', '%d horas', $horas, 'product-conditional-content'), $horas);
        } elseif ($dias < 7) {
            return sprintf(_n('%d día', '%d días', $dias, 'product-conditional-content'), $dias);
        } elseif ($semanas < 4) {
            return sprintf(_n('%d semana', '%d semanas', $semanas, 'product-conditional-content'), $semanas);
        } else {
            return sprintf(_n('%d mes', '%d meses', $meses, 'product-conditional-content'), $meses);
        }
    }
}