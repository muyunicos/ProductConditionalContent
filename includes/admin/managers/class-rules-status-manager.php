<?php
/**
 * Gestor de Estados con Toggle para Reglas (OPTIMIZADO)
 * Sistema simplificado: Habilitada/Deshabilitada + Sub-estados automáticos
 * Compatible con WordPress 6.8.3, PHP 8.2
 * 
 * @package ProductConditionalContent
 * @since 5.0.4
 */

if (!defined('ABSPATH')) exit;

    final class GDM_Regla_Status_Manager {
        
        /**
         * Inicializar hooks
         */
        public static function init() {
        add_action('init', [__CLASS__, 'register_custom_statuses'], 10);
        add_action('init', [__CLASS__, 'register_toggle_handler'], 11);
        
        // Columnas del listado
        add_filter('manage_gdm_regla_posts_columns', [__CLASS__, 'custom_columns']);
        add_action('manage_gdm_regla_posts_custom_column', [__CLASS__, 'custom_column_content'], 10, 2);
        add_filter('manage_edit-gdm_regla_sortable_columns', [__CLASS__, 'sortable_columns']);
        
        // Modificar metabox nativo sin duplicar elementos
        add_action('post_submitbox_start', [__CLASS__, 'remove_native_elements']);
        add_action('post_submitbox_misc_actions', [__CLASS__, 'add_custom_sections']);
        add_filter('gettext', [__CLASS__, 'change_publish_button_text'], 10, 2);
        
        // Enqueue scripts
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
        
        add_action('save_post_gdm_regla', [__CLASS__, 'save_metabox_data'], 30, 2);
        
        // Quick Edit
        add_action('quick_edit_custom_box', [__CLASS__, 'quick_edit_fields'], 10, 2);
        add_action('save_post_gdm_regla', [__CLASS__, 'save_quick_edit'], 35, 2);
        
        // Filtros de listado
        add_action('restrict_manage_posts', [__CLASS__, 'add_status_filter']);
        add_filter('views_edit-gdm_regla', [__CLASS__, 'custom_status_views']);
        add_action('pre_get_posts', [__CLASS__, 'filter_by_status']);
        add_filter('display_post_states', [__CLASS__, 'display_post_states'], 10, 2);
        
        // Convertir publish a habilitada automáticamente
        add_filter('wp_insert_post_data', [__CLASS__, 'force_custom_status'], 10, 2);
        
        // AJAX
        add_action('wp_ajax_gdm_get_regla_data', [__CLASS__, 'ajax_get_regla_data']);
    }

        /**
         * Registrar estados personalizados
         */
        public static function register_custom_statuses() {
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
    public static function register_toggle_handler() {
        if (class_exists('GDM_Toggle_AJAX_Handler')) {
            GDM_Toggle_AJAX_Handler::register_post_type('gdm_regla', [
                'enabled_status' => 'habilitada',
                'disabled_status' => 'deshabilitada',
                'capability' => 'edit_posts',
                'before_toggle' => [__CLASS__, 'before_toggle_callback'],
                'after_toggle' => [__CLASS__, 'after_toggle_callback'],
            ]);
        }
    }
    
    /**
     * Callback antes de cambiar toggle
     */
    public static function before_toggle_callback($post_id, $new_status, $post) {
        return true;
    }
    
    /**
     * Callback después de cambiar toggle
     */
    public static function after_toggle_callback($post_id, $new_status, $post) {
        wp_cache_delete("gdm_regla_{$post_id}", 'gdm_reglas');
    }
    
    /**
     * Calcular sub-estado de una regla
     */
    public static function calculate_substatus($default, $post_id, $status) {
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
     * Remover elementos nativos innecesarios
     */
    public static function remove_native_elements() {
        global $post, $current_screen;
        
        if (!$post || $current_screen->post_type !== 'gdm_regla') {
            return;
        }
        
        ?>
        <script>
        jQuery(document).ready(function($) {
            $('#save-action').remove();
            $('#visibility').remove();
            $('.misc-pub-curtime').remove();
            $('.misc-pub-post-status').remove();
        });
        </script>
        <?php
    }
    
    /**
     * Agregar secciones personalizadas
     */
    public static function add_custom_sections() {
        global $post, $current_screen;
        
        if (!$post || $current_screen->post_type !== 'gdm_regla') {
            return;
        }
        
        $current_status = $post->post_status;
        if ($current_status === 'publish' || $current_status === 'auto-draft') {
            $current_status = 'habilitada';
        }
        
        $is_enabled = ($current_status === 'habilitada');
        $programar = get_post_meta($post->ID, '_gdm_programar', true);
        $fecha_inicio = get_post_meta($post->ID, '_gdm_fecha_inicio', true);
        $fecha_fin = get_post_meta($post->ID, '_gdm_fecha_fin', true);
        $habilitar_fin = get_post_meta($post->ID, '_gdm_habilitar_fecha_fin', true);
        
        $estado_info = self::get_estado_info($is_enabled, $programar, $fecha_inicio, $fecha_fin, $habilitar_fin);
        
        wp_nonce_field('gdm_regla_schedule_nonce', 'gdm_regla_schedule_nonce');
        
        ?>
        <!-- Sección de Toggle -->
        <div class="misc-pub-section gdm-status-section">
            <div class="gdm-status-indicator <?php echo esc_attr($estado_info['class']); ?>">
                <label class="gdm-toggle-switch gdm-toggle-metabox">
                    <input type="checkbox" 
                           id="gdm-metabox-toggle" 
                           name="gdm_regla_enabled" 
                           value="1" 
                           <?php checked($is_enabled); ?>>
                    <span class="gdm-toggle-slider"></span>
                </label>
                
                <div style="flex: 1;">
                    <strong><?php _e('Estado:', 'product-conditional-content'); ?></strong>
                    <span class="gdm-status-display">
                        <?php echo esc_html($estado_info['titulo']); ?>
                    </span>
                    <p class="description gdm-status-description" style="margin: 4px 0 0 0;">
                        <?php echo esc_html($estado_info['descripcion']); ?>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Sección de Programación -->
        <div class="misc-pub-section gdm-schedule-section">
            <label style="display: flex; align-items: center; gap: 8px;">
                <input type="checkbox" 
                       name="gdm_programar" 
                       id="gdm_programar" 
                       value="1" 
                       <?php checked($programar, '1'); ?>>
                <strong><?php _e('Programar activación', 'product-conditional-content'); ?></strong>
            </label>
            
            <div id="gdm-schedule-fields" style="<?php echo $programar !== '1' ? 'display:none;' : ''; ?> margin-top: 12px; padding-left: 24px;">
                <div style="margin-bottom: 12px;">
                    <label style="display: block; margin-bottom: 4px;">
                        <strong><?php _e('📅 Fecha de Inicio:', 'product-conditional-content'); ?></strong>
                    </label>
                    <input type="datetime-local" 
                           name="gdm_fecha_inicio" 
                           id="gdm_fecha_inicio" 
                           value="<?php echo $fecha_inicio ? esc_attr(date('Y-m-d\TH:i', strtotime($fecha_inicio))) : ''; ?>" 
                           style="width: 100%;">
                    
                    <div class="gdm-quick-dates" style="margin-top: 8px;">
                        <button type="button" class="button button-small gdm-quick-date" data-type="tomorrow">
                            <?php _e('Mañana 00:00', 'product-conditional-content'); ?>
                        </button>
                        <button type="button" class="button button-small gdm-quick-date" data-type="monday">
                            <?php _e('Próximo lunes', 'product-conditional-content'); ?>
                        </button>
                        <button type="button" class="button button-small gdm-quick-date" data-type="month">
                            <?php _e('Próximo mes', 'product-conditional-content'); ?>
                        </button>
                    </div>
                    
                    <p class="description gdm-inicio-description" style="margin: 8px 0 0 0; font-size: 11px;">
                        <?php echo self::get_inicio_description($fecha_inicio); ?>
                    </p>
                </div>
                
                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                    <input type="checkbox" 
                           name="gdm_habilitar_fecha_fin" 
                           id="gdm_habilitar_fecha_fin" 
                           value="1" 
                           <?php checked($habilitar_fin, '1'); ?>>
                    <?php _e('Programar fecha de fin', 'product-conditional-content'); ?>
                </label>
                
                <div id="gdm-fecha-fin-wrapper" style="<?php echo $habilitar_fin !== '1' ? 'display:none;' : ''; ?>">
                    <label style="display: block; margin-bottom: 4px;">
                        <strong><?php _e('📅 Fecha de Fin:', 'product-conditional-content'); ?></strong>
                    </label>
                    <input type="datetime-local" 
                           name="gdm_fecha_fin" 
                           id="gdm_fecha_fin" 
                           value="<?php echo $fecha_fin ? esc_attr(date('Y-m-d\TH:i', strtotime($fecha_fin))) : ''; ?>" 
                           style="width: 100%;">
                    
                    <div class="gdm-quick-durations" style="margin-top: 8px;">
                        <button type="button" class="button button-small gdm-quick-duration" data-hours="24">
                            <?php _e('24hs', 'product-conditional-content'); ?>
                        </button>
                        <button type="button" class="button button-small gdm-quick-duration" data-hours="72">
                            <?php _e('72hs', 'product-conditional-content'); ?>
                        </button>
                        <button type="button" class="button button-small gdm-quick-duration" data-days="7">
                            <?php _e('Semana', 'product-conditional-content'); ?>
                        </button>
                        <button type="button" class="button button-small gdm-quick-duration" data-days="30">
                            <?php _e('Mes', 'product-conditional-content'); ?>
                        </button>
                    </div>
                    
                    <p class="description gdm-fin-description" style="margin: 8px 0 0 0; font-size: 11px;">
                        <?php echo self::get_fin_description($fecha_inicio, $fecha_fin); ?>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Data attributes para JavaScript -->
        <input type="hidden" id="gdm-current-status" value="<?php echo esc_attr($current_status); ?>">
        <input type="hidden" id="gdm-button-action" value="<?php echo esc_attr($estado_info['boton']); ?>">
        <?php
    }
    
    /**
     * Obtener descripción para fecha de inicio
     */
    private static function get_inicio_description($fecha_inicio) {
        if (!$fecha_inicio) {
            return __('La regla se activará automáticamente', 'product-conditional-content');
        }
        
        $now = current_time('timestamp');
        $inicio = strtotime($fecha_inicio);
        $diff = $inicio - $now;
        
        if ($diff <= 0) {
            return __('La regla se activará inmediatamente', 'product-conditional-content');
        }
        
        $tiempo = self::calcular_tiempo_legible($diff);
        $fecha_formatted = date_i18n('j \d\e F \d\e Y', $inicio);
        
        return sprintf(
            __('La regla se activará automáticamente en %s, el día %s', 'product-conditional-content'),
            $tiempo,
            $fecha_formatted
        );
    }
    
    /**
     * Obtener descripción para fecha de fin
     */
    private static function get_fin_description($fecha_inicio, $fecha_fin) {
        if (!$fecha_fin) {
            return __('La regla se desactivará automáticamente', 'product-conditional-content');
        }
        
        $now = current_time('timestamp');
        $fin = strtotime($fecha_fin);
        $diff = $fin - $now;
        
        if ($diff <= 0) {
            return __('La regla se desactivará inmediatamente', 'product-conditional-content');
        }
        
        $tiempo = self::calcular_tiempo_legible($diff);
        $fecha_formatted = date_i18n('j \d\e F \d\e Y', $fin);
        
        return sprintf(
            __('La regla se desactivará automáticamente en %s, el día %s', 'product-conditional-content'),
            $tiempo,
            $fecha_formatted
        );
    }
    
    /**
     * Calcular tiempo legible (minutos, horas, días, semanas, meses)
     */
    private static function calcular_tiempo_legible($segundos) {
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
    
    /**
     * Obtener información de estado según tu especificación
     */
    private static function get_estado_info($is_enabled, $programar, $fecha_inicio, $fecha_fin, $habilitar_fin) {
        $now = current_time('mysql');
        
        // Calcular estado dinámico
        $estado_dinamico = self::calcular_estado_dinamico($programar, $fecha_inicio, $fecha_fin, $habilitar_fin, $now);
        
        // CASO: REGLA HABILITADA
        if ($is_enabled) {
            return [
                'class' => $estado_dinamico['class'],
                'titulo' => __('Habilitada', 'product-conditional-content'),
                'descripcion' => $estado_dinamico['texto'],
                'boton' => 'guardar',
            ];
        }
        
        // CASO: REGLA DESHABILITADA
        else {
            return [
                'class' => 'status-disabled',
                'titulo' => __('Deshabilitada', 'product-conditional-content'),
                'descripcion' => __('La regla está desactivada', 'product-conditional-content'),
                'boton' => 'guardar',
            ];
        }
    }
    
    /**
     * Calcular estado dinámico: activa/inactiva/programada + detalles
     */
    private static function calcular_estado_dinamico($programar, $fecha_inicio, $fecha_fin, $habilitar_fin, $now) {
        // Sin programación = activa
        if ($programar !== '1' || !$fecha_inicio) {
            return [
                'class' => 'status-active',
                'texto' => __('Activa', 'product-conditional-content'),
            ];
        }
        
        $inicio_timestamp = strtotime($fecha_inicio);
        $now_timestamp = strtotime($now);
        
        // Programada (aún no inicia)
        if ($inicio_timestamp > $now_timestamp) {
            $diff = $inicio_timestamp - $now_timestamp;
            $tiempo_texto = self::calcular_tiempo_legible($diff);
            
            $texto = sprintf(__('Programada, inicia en %s', 'product-conditional-content'), $tiempo_texto);
            
            // Si tiene fecha fin, agregar info
            if ($habilitar_fin === '1' && $fecha_fin) {
                $fin_timestamp = strtotime($fecha_fin);
                $diff_fin = $fin_timestamp - $now_timestamp;
                $tiempo_fin = self::calcular_tiempo_legible($diff_fin);
                $texto .= sprintf(__(', termina en %s', 'product-conditional-content'), $tiempo_fin);
            }
            
            return [
                'class' => 'status-scheduled',
                'texto' => $texto,
            ];
        }
        
        // Ya inició
        
        // Si tiene fecha fin
        if ($habilitar_fin === '1' && $fecha_fin) {
            $fin_timestamp = strtotime($fecha_fin);
            
            // Ya terminó
            if ($fin_timestamp < $now_timestamp) {
                return [
                    'class' => 'status-expired',
                    'texto' => __('Inactiva, ya terminó', 'product-conditional-content'),
                ];
            }
            
            // Activa con fin próximo
            $diff_fin = $fin_timestamp - $now_timestamp;
            $tiempo_fin = self::calcular_tiempo_legible($diff_fin);
            
            return [
                'class' => 'status-active',
                'texto' => sprintf(__('Activa, termina en %s', 'product-conditional-content'), $tiempo_fin),
            ];
        }
        
        // Activa sin fin
        return [
            'class' => 'status-active',
            'texto' => __('Activa', 'product-conditional-content'),
        ];
    }
    
    /**
     * Cambiar texto del botón Publicar dinámicamente
     */
    public static function change_publish_button_text($translation, $text) {
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
     * Guardar datos del metabox
     */
    public static function save_metabox_data($post_id, $post) {
        if (!isset($_POST['gdm_regla_schedule_nonce']) || 
            !wp_verify_nonce($_POST['gdm_regla_schedule_nonce'], 'gdm_regla_schedule_nonce')) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Guardar estado del toggle
        $is_enabled = isset($_POST['gdm_regla_enabled']) && $_POST['gdm_regla_enabled'] === '1';
        $new_status = $is_enabled ? 'habilitada' : 'deshabilitada';
        
        remove_action('save_post_gdm_regla', [__CLASS__, 'save_metabox_data'], 10);
        wp_update_post([
            'ID' => $post_id,
            'post_status' => $new_status,
        ]);
        add_action('save_post_gdm_regla', [__CLASS__, 'save_metabox_data'], 10, 2);
        
        // Guardar programación
        $programar = isset($_POST['gdm_programar']) ? '1' : '0';
        update_post_meta($post_id, '_gdm_programar', $programar);
        
        if ($programar === '1' && isset($_POST['gdm_fecha_inicio']) && !empty($_POST['gdm_fecha_inicio'])) {
            $fecha_inicio = sanitize_text_field($_POST['gdm_fecha_inicio']);
            update_post_meta($post_id, '_gdm_fecha_inicio', date('Y-m-d H:i:s', strtotime($fecha_inicio)));
        } else {
            delete_post_meta($post_id, '_gdm_fecha_inicio');
        }
        
        $habilitar_fin = isset($_POST['gdm_habilitar_fecha_fin']) ? '1' : '0';
        update_post_meta($post_id, '_gdm_habilitar_fecha_fin', $habilitar_fin);
        
        if ($programar === '1' && $habilitar_fin === '1' && isset($_POST['gdm_fecha_fin']) && !empty($_POST['gdm_fecha_fin'])) {
            $fecha_fin = sanitize_text_field($_POST['gdm_fecha_fin']);
            update_post_meta($post_id, '_gdm_fecha_fin', date('Y-m-d H:i:s', strtotime($fecha_fin)));
        } else {
            delete_post_meta($post_id, '_gdm_fecha_fin');
        }
    }
    
    /**
     * Columnas personalizadas
     */
    public static function custom_columns($columns) {
        $new_columns = [];
        
        if (isset($columns['cb'])) {
            $new_columns['cb'] = $columns['cb'];
        }
        
        $new_columns['title'] = __('Nombre', 'product-conditional-content');
        $new_columns['gdm_toggle'] = __('ON/OFF', 'product-conditional-content');
        $new_columns['aplica_a'] = __('Aplica a', 'product-conditional-content');
        $new_columns['gdm_estado'] = __('Estado', 'product-conditional-content');
        $new_columns['fechas'] = __('Programación', 'product-conditional-content');
        
        if (isset($columns['date'])) {
            $new_columns['date'] = $columns['date'];
        }
        
        return $new_columns;
    }
    
    /**
     * Contenido de columnas personalizadas
     */
    public static function custom_column_content($column, $post_id) {
        switch ($column) {
            case 'gdm_toggle':
                self::render_toggle_column($post_id);
                break;
                
            case 'aplica_a':
                self::render_aplica_a_column($post_id);
                break;
                
            case 'gdm_estado':
                self::render_estado_column($post_id);
                break;
                
            case 'fechas':
                self::render_fechas_column($post_id);
                break;
        }
    }
    
    /**
     * Renderizar columna de toggle
     */
    private static function render_toggle_column($post_id) {
        $status = get_post_status($post_id);
        $is_enabled = ($status === 'habilitada' || $status === 'publish');
        $programar = get_post_meta($post_id, '_gdm_programar', true);
        
        ?>
        <div class="gdm-toggle-wrapper">
            <label class="gdm-toggle-switch" 
                   data-post-id="<?php echo esc_attr($post_id); ?>"
                   data-current-status="<?php echo esc_attr($status); ?>"
                   data-has-programacion="<?php echo $programar === '1' ? 'true' : 'false'; ?>"
                   aria-label="<?php echo $is_enabled ? __('Desactivar regla', 'product-conditional-content') : __('Activar regla', 'product-conditional-content'); ?>">
                <input type="checkbox" 
                       <?php checked($is_enabled); ?>
                       tabindex="0">
                <span class="gdm-toggle-slider"></span>
            </label>
        </div>
        <?php
    }
    
    /**
     * Renderizar columna "Aplica a"
     */
    private static function render_aplica_a_column($post_id) {
        $aplicar_a = get_post_meta($post_id, '_gdm_aplicar_a', true) ?: [];
        
        if (empty($aplicar_a)) {
            echo '<span style="color:#999;">—</span>';
            return;
        }
        
        $labels = [
            'todos' => __('Todos', 'product-conditional-content'),
            'categoria' => __('Categoría', 'product-conditional-content'),
            'etiqueta' => __('Etiqueta', 'product-conditional-content'),
            'reutilizable' => __('Reutilizable', 'product-conditional-content'),
        ];
        
        $output = [];
        foreach ($aplicar_a as $tipo) {
            if (isset($labels[$tipo])) {
                $output[] = '<span class="gdm-badge">' . esc_html($labels[$tipo]) . '</span>';
            }
        }
        
        echo implode(' ', $output);
    }
    
    /**
     * Renderizar columna de estado
     */
    private static function render_estado_column($post_id) {
        $status = get_post_status($post_id);
        $sub_status = self::calculate_substatus(null, $post_id, $status);
        
        ?>
        <div class="gdm-status-with-description">
            <span class="gdm-status-badge <?php echo esc_attr($sub_status['class']); ?>">
                <span class="gdm-status-icon"></span>
                <?php echo esc_html($sub_status['label']); ?>
            </span>
            <?php if (!empty($sub_status['description'])): ?>
                <span class="gdm-status-description">
                    <?php echo esc_html($sub_status['description']); ?>
                </span>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Renderizar columna de fechas
     */
    private static function render_fechas_column($post_id) {
        $programar = get_post_meta($post_id, '_gdm_programar', true);
        $fecha_inicio = get_post_meta($post_id, '_gdm_fecha_inicio', true);
        $fecha_fin = get_post_meta($post_id, '_gdm_fecha_fin', true);
        $habilitar_fin = get_post_meta($post_id, '_gdm_habilitar_fecha_fin', true);
        
        if ($programar !== '1') {
            echo '<span style="color:#999;">' . __('Sin programar', 'product-conditional-content') . '</span>';
            return;
        }
        
        echo '<div class="gdm-fechas-column">';
        
        if ($fecha_inicio) {
            echo '<div>📅 <strong>' . date_i18n('d M Y H:i', strtotime($fecha_inicio)) . '</strong></div>';
        }
        
        if ($habilitar_fin === '1' && $fecha_fin) {
            echo '<div>⏹️ <strong>' . date_i18n('d M Y H:i', strtotime($fecha_fin)) . '</strong></div>';
        }
        
        echo '</div>';
    }
    
    /**
     * Columnas ordenables
     */
    public static function sortable_columns($columns) {
        $columns['gdm_estado'] = 'post_status';
        $columns['fechas'] = 'gdm_fecha_inicio';
        return $columns;
    }
    
    /**
     * Enqueue scripts y estilos
     */
    public static function enqueue_scripts($hook) {
        global $post_type;
        
        $allowed_hooks = ['post.php', 'post-new.php', 'edit.php'];
        
        if (!in_array($hook, $allowed_hooks) || $post_type !== 'gdm_regla') {
            return;
        }
        
        wp_enqueue_style(
            'gdm-regla-toggle',
            GDM_PLUGIN_URL . 'assets/admin/css/rules-toggle.css',
            [],
            GDM_VERSION
        );
        
        if ($hook === 'edit.php') {
            wp_enqueue_script(
                'gdm-regla-toggle',
                GDM_PLUGIN_URL . 'assets/admin/js/components/rules-toggle.js',
                ['jquery'],
                GDM_VERSION,
                true
            );
            
            wp_localize_script('gdm-regla-toggle', 'gdmToggle', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('gdm_toggle_nonce'),
                'i18n' => [
                    'enabled' => __('Habilitada', 'product-conditional-content'),
                    'disabled' => __('Deshabilitada', 'product-conditional-content'),
                    'error' => __('Error al cambiar el estado', 'product-conditional-content'),
                    'ajaxError' => __('Error de conexión', 'product-conditional-content'),
                    'confirmDisable' => __('¿Desactivar esta regla?', 'product-conditional-content'),
                    'onlyActive' => __('Solo activas', 'product-conditional-content'),
                    'noSelection' => __('Selecciona al menos una regla', 'product-conditional-content'),
                    'confirmBulkToggle' => __('¿Cambiar estado de las reglas seleccionadas?', 'product-conditional-content'),
                ],
            ]);
        }
        
        if (in_array($hook, ['post.php', 'post-new.php'])) {
            wp_enqueue_script(
                'gdm-regla-publish-metabox',
                GDM_PLUGIN_URL . 'assets/admin/js/metaboxes/rules-publish-toggle.js',
                ['jquery'],
                GDM_VERSION,
                true
            );
            
            wp_localize_script('gdm-regla-publish-metabox', 'gdmPublishMetabox', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('gdm_publish_metabox_nonce'),
                'i18n' => [
                    'habilitada' => __('Habilitada', 'product-conditional-content'),
                    'deshabilitada' => __('Deshabilitada', 'product-conditional-content'),
                    'habilitar' => __('Habilitar', 'product-conditional-content'),
                    'deshabilitar' => __('Deshabilitar', 'product-conditional-content'),
                    'guardar' => __('Guardar', 'product-conditional-content'),
                    'laReglaSeDesactivara' => __('La regla se desactivará', 'product-conditional-content'),
                ],
            ]);
        }
        
        if ($hook === 'edit.php') {
            wp_enqueue_script(
                'gdm-regla-quick-edit',
                GDM_PLUGIN_URL . 'assets/admin/js/components/rules-quick-edit.js',
                ['jquery', 'inline-edit-post'],
                GDM_VERSION,
                true
            );
            
            wp_localize_script('gdm-regla-quick-edit', 'gdmQuickEdit', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('gdm_quick_edit_nonce'),
            ]);
        }
    }
    
    /**
     * Quick Edit
     */
    public static function quick_edit_fields($column_name, $post_type) {
        if ($post_type !== 'gdm_regla' || $column_name !== 'gdm_estado') {
            return;
        }
        
        ?>
        <fieldset class="inline-edit-col-right">
            <div class="inline-edit-col">
                <label>
                    <input type="checkbox" name="gdm_quick_toggle" value="1">
                    <span class="checkbox-title"><?php _e('Habilitar regla', 'product-conditional-content'); ?></span>
                </label>
            </div>
        </fieldset>
        <?php
    }
    
    /**
     * Guardar Quick Edit
     */
    public static function save_quick_edit($post_id, $post) {
        if (!isset($_POST['_inline_edit']) || !wp_verify_nonce($_POST['_inline_edit'], 'inlineeditnonce')) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        $is_enabled = isset($_POST['gdm_quick_toggle']) && $_POST['gdm_quick_toggle'] === '1';
        $new_status = $is_enabled ? 'habilitada' : 'deshabilitada';
        
        remove_action('save_post_gdm_regla', [__CLASS__, 'save_quick_edit'], 15);
        wp_update_post([
            'ID' => $post_id,
            'post_status' => $new_status,
        ]);
        add_action('save_post_gdm_regla', [__CLASS__, 'save_quick_edit'], 15, 2);
    }
    
    /**
     * Bulk Edit
     */
    public static function bulk_edit_fields($column_name, $post_type) {
        if ($post_type !== 'gdm_regla' || $column_name !== 'gdm_estado') {
            return;
        }
        
        ?>
        <fieldset class="inline-edit-col-right">
            <div class="inline-edit-col">
                <label class="alignleft">
                    <span class="title"><?php _e('Estado', 'product-conditional-content'); ?></span>
                    <select name="gdm_bulk_status">
                        <option value="-1"><?php _e('— Sin cambios —', 'product-conditional-content'); ?></option>
                        <option value="habilitada"><?php _e('Habilitada', 'product-conditional-content'); ?></option>
                        <option value="deshabilitada"><?php _e('Deshabilitada', 'product-conditional-content'); ?></option>
                    </select>
                </label>
            </div>
        </fieldset>
        <?php
    }
    
    /**
     * AJAX: Obtener datos de regla
     */
    public static function ajax_get_regla_data() {
        check_ajax_referer('gdm_quick_edit_nonce', 'nonce');
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        
        if (!$post_id) {
            wp_send_json_error();
        }
        
        $status = get_post_status($post_id);
        $is_enabled = ($status === 'habilitada' || $status === 'publish');
        
        wp_send_json_success([
            'status' => $status,
            'enabled' => $is_enabled,
            'programar' => get_post_meta($post_id, '_gdm_programar', true),
            'fecha_inicio' => get_post_meta($post_id, '_gdm_fecha_inicio', true),
            'fecha_fin' => get_post_meta($post_id, '_gdm_fecha_fin', true),
            'habilitar_fin' => get_post_meta($post_id, '_gdm_habilitar_fecha_fin', true),
        ]);
    }
    
    /**
     * Cron: Verificar programaciones
     */
    public static function check_schedules() {
        $now = current_time('mysql');
        
        $args = [
            'post_type' => 'gdm_regla',
            'post_status' => 'habilitada',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_gdm_programar',
                    'value' => '1',
                ],
            ],
        ];
        
        $reglas = get_posts($args);
        
        foreach ($reglas as $regla) {
            $fecha_inicio = get_post_meta($regla->ID, '_gdm_fecha_inicio', true);
            $fecha_fin = get_post_meta($regla->ID, '_gdm_fecha_fin', true);
            $habilitar_fin = get_post_meta($regla->ID, '_gdm_habilitar_fecha_fin', true);
            
            if ($habilitar_fin === '1' && $fecha_fin && $fecha_fin < $now) {
                wp_update_post([
                    'ID' => $regla->ID,
                    'post_status' => 'deshabilitada',
                ]);
            }
        }
    }
    
    /**
     * Vistas del listado
     */
    public static function custom_status_views($views) {
        $counts = self::get_status_counts();
        
        $class = (!isset($_GET['post_status'])) ? 'current' : '';
        $views['all'] = sprintf(
            '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
            admin_url('edit.php?post_type=gdm_regla'),
            $class,
            __('Todas', 'product-conditional-content'),
            $counts['total']
        );
        
        if ($counts['habilitada'] > 0) {
            $class = (isset($_GET['post_status']) && $_GET['post_status'] === 'habilitada') ? 'current' : '';
            $views['habilitada'] = sprintf(
                '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
                admin_url('edit.php?post_type=gdm_regla&post_status=habilitada'),
                $class,
                __('Habilitadas', 'product-conditional-content'),
                $counts['habilitada']
            );
        }
        
        if ($counts['deshabilitada'] > 0) {
            $class = (isset($_GET['post_status']) && $_GET['post_status'] === 'deshabilitada') ? 'current' : '';
            $views['deshabilitada'] = sprintf(
                '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
                admin_url('edit.php?post_type=gdm_regla&post_status=deshabilitada'),
                $class,
                __('Deshabilitadas', 'product-conditional-content'),
                $counts['deshabilitada']
            );
        }
        
        unset($views['publish'], $views['draft'], $views['pending']);
        
        return $views;
    }
    
    /**
     * Contar estados
     */
    private static function get_status_counts() {
        global $wpdb;
        
        $counts = [
            'habilitada' => 0,
            'deshabilitada' => 0,
            'total' => 0,
        ];
        
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT post_status, COUNT(*) as count 
                FROM {$wpdb->posts} 
                WHERE post_type = %s 
                AND post_status IN ('habilitada', 'deshabilitada', 'publish')
                GROUP BY post_status",
                'gdm_regla'
            )
        );
        
        foreach ($results as $row) {
            $status = ($row->post_status === 'publish') ? 'habilitada' : $row->post_status;
            if (isset($counts[$status])) {
                $counts[$status] = (int) $row->count;
            }
            $counts['total'] += (int) $row->count;
        }
        
        return $counts;
    }
    
    /**
     * Filtrar por estado
     */
    public static function filter_by_status($query) {
        global $pagenow, $typenow;
        
        if ($pagenow !== 'edit.php' || $typenow !== 'gdm_regla' || !$query->is_main_query()) {
            return;
        }
        
        if (!isset($_GET['post_status'])) {
            $query->set('post_status', ['habilitada', 'deshabilitada', 'publish']);
        }
    }
    
    /**
     * Display post states
     */
    public static function display_post_states($states, $post) {
        if ($post->post_type !== 'gdm_regla') {
            return $states;
        }
        
        unset($states['publish']);
        
        return $states;
    }
}

GDM_Regla_Status_Manager::init();