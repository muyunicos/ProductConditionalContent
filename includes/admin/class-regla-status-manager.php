<?php
/**
 * Gestor de Estados Personalizados para Reglas
 * Compatible con WordPress 6.8.3, PHP 8.2
 * 
 * Estados:
 * - habilitada: Regla activa
 * - deshabilitada: Regla inactiva manualmente
 * - programada: Esperando fecha de inicio
 * - revisar: Necesita revisión
 * - terminada: Fecha de fin alcanzada
 * 
 * @package ProductConditionalContent
 * @since 5.0.2
 */

if (!defined('ABSPATH')) exit;

final class GDM_Regla_Status_Manager {
    
    /**
     * Inicializar hooks
     */
    public static function init() {
        // Registrar estados personalizados
        add_action('init', [__CLASS__, 'register_custom_statuses']);
        
        // Modificar listado de columnas
        add_filter('manage_gdm_regla_posts_columns', [__CLASS__, 'custom_columns']);
        add_action('manage_gdm_regla_posts_custom_column', [__CLASS__, 'custom_column_content'], 10, 2);
        add_filter('manage_edit-gdm_regla_sortable_columns', [__CLASS__, 'sortable_columns']);
        
        // Quick Edit
        add_action('quick_edit_custom_box', [__CLASS__, 'quick_edit_fields'], 10, 2);
        add_action('save_post_gdm_regla', [__CLASS__, 'save_quick_edit'], 10, 2);
        
        // Bulk Edit
        add_action('bulk_edit_custom_box', [__CLASS__, 'bulk_edit_fields'], 10, 2);
        
        // AJAX para Quick Edit
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
        add_action('wp_ajax_gdm_get_regla_data', [__CLASS__, 'ajax_get_regla_data']);
        
        // Display States en listado
        add_filter('display_post_states', [__CLASS__, 'display_post_states'], 10, 2);
        
        // Metabox de fechas
        add_action('add_meta_boxes_gdm_regla', [__CLASS__, 'add_schedule_metabox']);
        add_action('save_post_gdm_regla', [__CLASS__, 'save_schedule_metabox'], 20, 2);
        
        // Cron para actualizar estados
        add_action('gdm_check_regla_schedules', [__CLASS__, 'check_schedules']);
        
        // Ocultar opciones de visibilidad en Quick Edit
        add_action('admin_head-edit.php', [__CLASS__, 'hide_quick_edit_options']);
        
        // Filtros de estado en listado
        add_filter('views_edit-gdm_regla', [__CLASS__, 'custom_status_views']);
        add_action('pre_get_posts', [__CLASS__, 'filter_by_custom_status']);
    }
    
    /**
     * Registrar estados personalizados
     */
    public static function register_custom_statuses() {
        // Habilitada (reemplaza "publish")
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
        
        // Deshabilitada
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
        
        // Programada
        register_post_status('programada', [
            'label'                     => _x('Programada', 'post status', 'product-conditional-content'),
            'public'                    => false,
            'exclude_from_search'       => true,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop(
                'Programada <span class="count">(%s)</span>',
                'Programadas <span class="count">(%s)</span>',
                'product-conditional-content'
            ),
        ]);
        
        // Revisar
        register_post_status('revisar', [
            'label'                     => _x('Revisar', 'post status', 'product-conditional-content'),
            'public'                    => false,
            'exclude_from_search'       => true,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop(
                'Revisar <span class="count">(%s)</span>',
                'Revisar <span class="count">(%s)</span>',
                'product-conditional-content'
            ),
        ]);
        
        // Terminada
        register_post_status('terminada', [
            'label'                     => _x('Terminada', 'post status', 'product-conditional-content'),
            'public'                    => false,
            'exclude_from_search'       => true,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop(
                'Terminada <span class="count">(%s)</span>',
                'Terminadas <span class="count">(%s)</span>',
                'product-conditional-content'
            ),
        ]);
    }
    
    /**
     * Configurar columnas personalizadas
     */
    public static function custom_columns($columns) {
        $new_columns = [];
        
        // Checkbox
        if (isset($columns['cb'])) {
            $new_columns['cb'] = $columns['cb'];
        }
        
        // Nombre (título)
        $new_columns['title'] = __('Nombre', 'product-conditional-content');
        
        // Aplica a
        $new_columns['aplica_a'] = __('Aplica a', 'product-conditional-content');
        
        // Fecha inicio/fin
        $new_columns['fechas'] = __('Fecha Inicio / Fin', 'product-conditional-content');
        
        // Estado
        $new_columns['estado'] = __('Estado', 'product-conditional-content');
        
        // Fecha (WordPress default - mantener para sorting)
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
            case 'aplica_a':
                self::render_aplica_a_column($post_id);
                break;
                
            case 'fechas':
                self::render_fechas_column($post_id);
                break;
                
            case 'estado':
                self::render_estado_column($post_id);
                break;
        }
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
            'todos' => __('Todos los productos', 'product-conditional-content'),
            'categoria' => __('Por categoría', 'product-conditional-content'),
            'etiqueta' => __('Por etiqueta', 'product-conditional-content'),
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
     * Renderizar columna "Fechas"
     */
    private static function render_fechas_column($post_id) {
        $fecha_inicio = get_post_meta($post_id, '_gdm_fecha_inicio', true);
        $fecha_fin = get_post_meta($post_id, '_gdm_fecha_fin', true);
        $habilitar_fin = get_post_meta($post_id, '_gdm_habilitar_fecha_fin', true);
        
        if (!$fecha_inicio && !$fecha_fin) {
            echo '<span style="color:#999;">—</span>';
            return;
        }
        
        echo '<div class="gdm-fechas-column">';
        
        if ($fecha_inicio) {
            $fecha_inicio_formatted = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($fecha_inicio));
            echo '<div><strong>' . __('Inicio:', 'product-conditional-content') . '</strong> ' . esc_html($fecha_inicio_formatted) . '</div>';
        }
        
        if ($habilitar_fin === '1' && $fecha_fin) {
            $fecha_fin_formatted = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($fecha_fin));
            echo '<div><strong>' . __('Fin:', 'product-conditional-content') . '</strong> ' . esc_html($fecha_fin_formatted) . '</div>';
        }
        
        echo '</div>';
    }
    
    /**
     * Renderizar columna "Estado"
     */
    private static function render_estado_column($post_id) {
        $status = get_post_status($post_id);
        
        // Mapear publish a habilitada para visualización
        if ($status === 'publish') {
            $status = 'habilitada';
        }
        
        $status_colors = [
            'habilitada' => '#46b450',
            'deshabilitada' => '#999',
            'programada' => '#00a0d2',
            'revisar' => '#f56e28',
            'terminada' => '#dc3232',
        ];
        
        $status_labels = [
            'habilitada' => __('Habilitada', 'product-conditional-content'),
            'deshabilitada' => __('Deshabilitada', 'product-conditional-content'),
            'programada' => __('Programada', 'product-conditional-content'),
            'revisar' => __('Revisar', 'product-conditional-content'),
            'terminada' => __('Terminada', 'product-conditional-content'),
        ];
        
        $color = isset($status_colors[$status]) ? $status_colors[$status] : '#999';
        $label = isset($status_labels[$status]) ? $status_labels[$status] : ucfirst($status);
        
        echo sprintf(
            '<span class="gdm-status-badge" style="background-color:%s;color:#fff;padding:4px 10px;border-radius:3px;font-weight:500;display:inline-block;">%s</span>',
            esc_attr($color),
            esc_html($label)
        );
    }
    
    /**
     * Columnas ordenables
     */
    public static function sortable_columns($columns) {
        $columns['estado'] = 'post_status';
        $columns['fechas'] = 'gdm_fecha_inicio';
        return $columns;
    }
    
    /**
     * Metabox de programación
     */
    public static function add_schedule_metabox() {
        add_meta_box(
            'gdm_regla_schedule',
            __('Programación de Regla', 'product-conditional-content'),
            [__CLASS__, 'render_schedule_metabox'],
            'gdm_regla',
            'side',
            'high'
        );
    }
    
    /**
     * Renderizar metabox de programación
     */
    public static function render_schedule_metabox($post) {
        wp_nonce_field('gdm_schedule_nonce', 'gdm_schedule_nonce');
        
        $fecha_inicio = get_post_meta($post->ID, '_gdm_fecha_inicio', true);
        $fecha_fin = get_post_meta($post->ID, '_gdm_fecha_fin', true);
        $habilitar_fin = get_post_meta($post->ID, '_gdm_habilitar_fecha_fin', true);
        $current_status = get_post_status($post->ID);
        
        // Mapear publish a habilitada
        if ($current_status === 'publish') {
            $current_status = 'habilitada';
        }
        
        ?>
        <div class="gdm-schedule-meta">
            <p>
                <label for="gdm_regla_status"><strong><?php _e('Estado:', 'product-conditional-content'); ?></strong></label><br>
                <select name="gdm_regla_status" id="gdm_regla_status" style="width:100%;">
                    <option value="habilitada" <?php selected($current_status, 'habilitada'); ?>><?php _e('Habilitada', 'product-conditional-content'); ?></option>
                    <option value="deshabilitada" <?php selected($current_status, 'deshabilitada'); ?>><?php _e('Deshabilitada', 'product-conditional-content'); ?></option>
                    <option value="programada" <?php selected($current_status, 'programada'); ?>><?php _e('Programada', 'product-conditional-content'); ?></option>
                    <option value="revisar" <?php selected($current_status, 'revisar'); ?>><?php _e('Revisar', 'product-conditional-content'); ?></option>
                    <option value="terminada" <?php selected($current_status, 'terminada'); ?>><?php _e('Terminada', 'product-conditional-content'); ?></option>
                </select>
            </p>
            
            <p>
                <label for="gdm_fecha_inicio"><strong><?php _e('Fecha de Inicio:', 'product-conditional-content'); ?></strong></label><br>
                <input type="datetime-local" 
                       name="gdm_fecha_inicio" 
                       id="gdm_fecha_inicio" 
                       value="<?php echo $fecha_inicio ? esc_attr(date('Y-m-d\TH:i', strtotime($fecha_inicio))) : ''; ?>" 
                       style="width:100%;">
                <span class="description"><?php _e('Cuando la regla se activará', 'product-conditional-content'); ?></span>
            </p>
            
            <p>
                <label>
                    <input type="checkbox" 
                           name="gdm_habilitar_fecha_fin" 
                           id="gdm_habilitar_fecha_fin" 
                           value="1" 
                           <?php checked($habilitar_fin, '1'); ?>>
                    <strong><?php _e('Habilitar fecha de finalización', 'product-conditional-content'); ?></strong>
                </label>
            </p>
            
            <p id="gdm_fecha_fin_wrapper" style="<?php echo $habilitar_fin !== '1' ? 'display:none;' : ''; ?>">
                <label for="gdm_fecha_fin"><strong><?php _e('Fecha de Fin (Caducidad):', 'product-conditional-content'); ?></strong></label><br>
                <input type="datetime-local" 
                       name="gdm_fecha_fin" 
                       id="gdm_fecha_fin" 
                       value="<?php echo $fecha_fin ? esc_attr(date('Y-m-d\TH:i', strtotime($fecha_fin))) : ''; ?>" 
                       style="width:100%;">
                <span class="description"><?php _e('La regla se deshabilitará automáticamente', 'product-conditional-content'); ?></span>
            </p>
            
            <script>
            jQuery(document).ready(function($) {
                $('#gdm_habilitar_fecha_fin').on('change', function() {
                    $('#gdm_fecha_fin_wrapper').toggle(this.checked);
                });
            });
            </script>
            
            <style>
            .gdm-schedule-meta p { margin: 12px 0; }
            .gdm-schedule-meta .description { font-size: 12px; color: #666; display: block; margin-top: 4px; }
            </style>
        </div>
        <?php
    }
    
    /**
     * Guardar metabox de programación
     */
    public static function save_schedule_metabox($post_id, $post) {
        // Verificar nonce
        if (!isset($_POST['gdm_schedule_nonce']) || !wp_verify_nonce($_POST['gdm_schedule_nonce'], 'gdm_schedule_nonce')) {
            return;
        }
        
        // Verificar permisos
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Evitar autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Guardar estado
        if (isset($_POST['gdm_regla_status'])) {
            $status = sanitize_text_field($_POST['gdm_regla_status']);
            $allowed_statuses = ['habilitada', 'deshabilitada', 'programada', 'revisar', 'terminada'];
            
            if (in_array($status, $allowed_statuses)) {
                // Actualizar estado del post
                remove_action('save_post_gdm_regla', [__CLASS__, 'save_schedule_metabox'], 20);
                wp_update_post([
                    'ID' => $post_id,
                    'post_status' => $status,
                ]);
                add_action('save_post_gdm_regla', [__CLASS__, 'save_schedule_metabox'], 20, 2);
            }
        }
        
        // Guardar fecha inicio
        if (isset($_POST['gdm_fecha_inicio']) && !empty($_POST['gdm_fecha_inicio'])) {
            $fecha_inicio = sanitize_text_field($_POST['gdm_fecha_inicio']);
            update_post_meta($post_id, '_gdm_fecha_inicio', date('Y-m-d H:i:s', strtotime($fecha_inicio)));
        } else {
            delete_post_meta($post_id, '_gdm_fecha_inicio');
        }
        
        // Guardar opción de habilitar fecha fin
        $habilitar_fin = isset($_POST['gdm_habilitar_fecha_fin']) ? '1' : '0';
        update_post_meta($post_id, '_gdm_habilitar_fecha_fin', $habilitar_fin);
        
        // Guardar fecha fin
        if ($habilitar_fin === '1' && isset($_POST['gdm_fecha_fin']) && !empty($_POST['gdm_fecha_fin'])) {
            $fecha_fin = sanitize_text_field($_POST['gdm_fecha_fin']);
            update_post_meta($post_id, '_gdm_fecha_fin', date('Y-m-d H:i:s', strtotime($fecha_fin)));
        } else {
            delete_post_meta($post_id, '_gdm_fecha_fin');
        }
    }
    
    /**
     * Quick Edit - Campos personalizados
     */
    public static function quick_edit_fields($column_name, $post_type) {
        if ($post_type !== 'gdm_regla') {
            return;
        }
        
        if ($column_name === 'estado') {
            ?>
            <fieldset class="inline-edit-col-right">
                <div class="inline-edit-col">
                    <label class="inline-edit-status alignleft">
                        <span class="title"><?php _e('Estado', 'product-conditional-content'); ?></span>
                        <select name="gdm_regla_status">
                            <option value="habilitada"><?php _e('Habilitada', 'product-conditional-content'); ?></option>
                            <option value="deshabilitada"><?php _e('Deshabilitada', 'product-conditional-content'); ?></option>
                            <option value="programada"><?php _e('Programada', 'product-conditional-content'); ?></option>
                            <option value="revisar"><?php _e('Revisar', 'product-conditional-content'); ?></option>
                            <option value="terminada"><?php _e('Terminada', 'product-conditional-content'); ?></option>
                        </select>
                    </label>
                    
                    <label class="alignleft">
                        <span class="title"><?php _e('Fecha Inicio', 'product-conditional-content'); ?></span>
                        <input type="datetime-local" name="gdm_fecha_inicio" value="">
                    </label>
                    
                    <label class="alignleft">
                        <input type="checkbox" name="gdm_habilitar_fecha_fin" value="1">
                        <?php _e('Habilitar fecha fin', 'product-conditional-content'); ?>
                    </label>
                    
                    <label class="alignleft">
                        <span class="title"><?php _e('Fecha Fin', 'product-conditional-content'); ?></span>
                        <input type="datetime-local" name="gdm_fecha_fin" value="">
                    </label>
                </div>
            </fieldset>
            <?php
        }
    }
    
    /**
     * Guardar Quick Edit
     */
    public static function save_quick_edit($post_id, $post) {
        // Verificar si es quick edit
        if (!isset($_POST['_inline_edit']) || !wp_verify_nonce($_POST['_inline_edit'], 'inlineeditnonce')) {
            return;
        }
        
        // Verificar permisos
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Guardar estado
        if (isset($_POST['gdm_regla_status'])) {
            $status = sanitize_text_field($_POST['gdm_regla_status']);
            $allowed_statuses = ['habilitada', 'deshabilitada', 'programada', 'revisar', 'terminada'];
            
            if (in_array($status, $allowed_statuses)) {
                remove_action('save_post_gdm_regla', [__CLASS__, 'save_quick_edit'], 10);
                wp_update_post([
                    'ID' => $post_id,
                    'post_status' => $status,
                ]);
                add_action('save_post_gdm_regla', [__CLASS__, 'save_quick_edit'], 10, 2);
            }
        }
        
        // Guardar fechas
        if (isset($_POST['gdm_fecha_inicio']) && !empty($_POST['gdm_fecha_inicio'])) {
            update_post_meta($post_id, '_gdm_fecha_inicio', date('Y-m-d H:i:s', strtotime($_POST['gdm_fecha_inicio'])));
        }
        
        $habilitar_fin = isset($_POST['gdm_habilitar_fecha_fin']) ? '1' : '0';
        update_post_meta($post_id, '_gdm_habilitar_fecha_fin', $habilitar_fin);
        
        if ($habilitar_fin === '1' && isset($_POST['gdm_fecha_fin']) && !empty($_POST['gdm_fecha_fin'])) {
            update_post_meta($post_id, '_gdm_fecha_fin', date('Y-m-d H:i:s', strtotime($_POST['gdm_fecha_fin'])));
        }
    }
    
    /**
     * Bulk Edit - Campos
     */
    public static function bulk_edit_fields($column_name, $post_type) {
        if ($post_type !== 'gdm_regla' || $column_name !== 'estado') {
            return;
        }
        
        ?>
        <fieldset class="inline-edit-col-right">
            <div class="inline-edit-col">
                <label class="inline-edit-status alignleft">
                    <span class="title"><?php _e('Estado', 'product-conditional-content'); ?></span>
                    <select name="gdm_regla_status">
                        <option value="-1"><?php _e('— Sin cambios —', 'product-conditional-content'); ?></option>
                        <option value="habilitada"><?php _e('Habilitada', 'product-conditional-content'); ?></option>
                        <option value="deshabilitada"><?php _e('Deshabilitada', 'product-conditional-content'); ?></option>
                        <option value="programada"><?php _e('Programada', 'product-conditional-content'); ?></option>
                        <option value="revisar"><?php _e('Revisar', 'product-conditional-content'); ?></option>
                        <option value="terminada"><?php _e('Terminada', 'product-conditional-content'); ?></option>
                    </select>
                </label>
            </div>
        </fieldset>
        <?php
    }
    
    /**
     * Enqueue scripts para Quick Edit
     */
    public static function enqueue_scripts($hook) {
        if ($hook !== 'edit.php' || get_current_screen()->post_type !== 'gdm_regla') {
            return;
        }
        
        wp_enqueue_script(
            'gdm-regla-quick-edit',
            plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/admin/regla-quick-edit.js',
            ['jquery', 'inline-edit-post'],
            '1.0.0',
            true
        );
        
        wp_localize_script('gdm-regla-quick-edit', 'gdmQuickEdit', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gdm_quick_edit_nonce'),
        ]);
        
        // CSS para ocultar opciones de Quick Edit
        wp_add_inline_style('wp-admin', '
            .post-type-gdm_regla .inline-edit-password-input,
            .post-type-gdm_regla .inline-edit-private { display: none !important; }
            .gdm-badge { 
                background: #f0f0f1; 
                padding: 2px 8px; 
                border-radius: 3px; 
                font-size: 11px; 
                margin-right: 4px;
                display: inline-block;
            }
            .gdm-fechas-column { font-size: 13px; line-height: 1.6; }
        ');
    }
    
    /**
     * AJAX: Obtener datos de regla para Quick Edit
     */
    public static function ajax_get_regla_data() {
        check_ajax_referer('gdm_quick_edit_nonce', 'nonce');
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        
        if (!$post_id) {
            wp_send_json_error();
        }
        
        $data = [
            'status' => get_post_status($post_id),
            'fecha_inicio' => get_post_meta($post_id, '_gdm_fecha_inicio', true),
            'fecha_fin' => get_post_meta($post_id, '_gdm_fecha_fin', true),
            'habilitar_fin' => get_post_meta($post_id, '_gdm_habilitar_fecha_fin', true),
        ];
        
        wp_send_json_success($data);
    }
    
    /**
     * Ocultar opciones de visibilidad en Quick Edit
     */
    public static function hide_quick_edit_options() {
        global $current_screen;
        
        if ($current_screen->post_type !== 'gdm_regla') {
            return;
        }
        
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Ocultar opciones de visibilidad
            $('.inline-edit-row .inline-edit-password-input, .inline-edit-row .inline-edit-private').remove();
        });
        </script>
        <?php
    }
    
    /**
     * Display post states
     */
    public static function display_post_states($states, $post) {
        if ($post->post_type !== 'gdm_regla') {
            return $states;
        }
        
        // Limpiar estados por defecto
        unset($states['publish']);
        
        return $states;
    }
    
    /**
     * Cron job: Verificar programaciones
     */
    public static function check_schedules() {
        $now = current_time('mysql');
        
        // Activar reglas programadas cuya fecha de inicio ya pasó
        $args = [
            'post_type' => 'gdm_regla',
            'post_status' => 'programada',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_gdm_fecha_inicio',
                    'value' => $now,
                    'compare' => '<=',
                    'type' => 'DATETIME',
                ],
            ],
        ];
        
        $programadas = get_posts($args);
        
        foreach ($programadas as $regla) {
            wp_update_post([
                'ID' => $regla->ID,
                'post_status' => 'habilitada',
            ]);
        }
        
        // Finalizar reglas habilitadas cuya fecha de fin ya pasó
        $args = [
            'post_type' => 'gdm_regla',
            'post_status' => 'habilitada',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_gdm_habilitar_fecha_fin',
                    'value' => '1',
                ],
                [
                    'key' => '_gdm_fecha_fin',
                    'value' => $now,
                    'compare' => '<=',
                    'type' => 'DATETIME',
                ],
            ],
        ];
        
        $expiradas = get_posts($args);
        
        foreach ($expiradas as $regla) {
            wp_update_post([
                'ID' => $regla->ID,
                'post_status' => 'terminada',
            ]);
        }
    }
    
    /**
     * Vistas de estado personalizadas
     */
    public static function custom_status_views($views) {
        global $wp_query;
        
        // Contar por estados personalizados
        $counts = self::get_status_counts();
        
        // Todas
        $class = (!isset($_GET['post_status'])) ? 'current' : '';
        $views['all'] = sprintf(
            '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
            admin_url('edit.php?post_type=gdm_regla'),
            $class,
            __('Todas', 'product-conditional-content'),
            array_sum($counts)
        );
        
        // Habilitadas
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
        
        // Deshabilitadas
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
        
        // Programadas
        if ($counts['programada'] > 0) {
            $class = (isset($_GET['post_status']) && $_GET['post_status'] === 'programada') ? 'current' : '';
            $views['programada'] = sprintf(
                '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
                admin_url('edit.php?post_type=gdm_regla&post_status=programada'),
                $class,
                __('Programadas', 'product-conditional-content'),
                $counts['programada']
            );
        }
        
        // Revisar
        if ($counts['revisar'] > 0) {
            $class = (isset($_GET['post_status']) && $_GET['post_status'] === 'revisar') ? 'current' : '';
            $views['revisar'] = sprintf(
                '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
                admin_url('edit.php?post_type=gdm_regla&post_status=revisar'),
                $class,
                __('Revisar', 'product-conditional-content'),
                $counts['revisar']
            );
        }
        
        // Terminadas
        if ($counts['terminada'] > 0) {
            $class = (isset($_GET['post_status']) && $_GET['post_status'] === 'terminada') ? 'current' : '';
            $views['terminada'] = sprintf(
                '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
                admin_url('edit.php?post_type=gdm_regla&post_status=terminada'),
                $class,
                __('Terminadas', 'product-conditional-content'),
                $counts['terminada']
            );
        }
        
        // Remover vistas por defecto que no queremos
        unset($views['publish']);
        unset($views['draft']);
        unset($views['pending']);
        
        return $views;
    }
    
    /**
     * Obtener conteos de estados
     */
    private static function get_status_counts() {
        global $wpdb;
        
        $counts = [
            'habilitada' => 0,
            'deshabilitada' => 0,
            'programada' => 0,
            'revisar' => 0,
            'terminada' => 0,
        ];
        
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT post_status, COUNT(*) as count 
                FROM {$wpdb->posts} 
                WHERE post_type = %s 
                AND post_status IN ('habilitada', 'deshabilitada', 'programada', 'revisar', 'terminada', 'publish')
                GROUP BY post_status",
                'gdm_regla'
            )
        );
        
        foreach ($results as $row) {
            // Tratar 'publish' como 'habilitada'
            $status = ($row->post_status === 'publish') ? 'habilitada' : $row->post_status;
            if (isset($counts[$status])) {
                $counts[$status] = (int) $row->count;
            }
        }
        
        return $counts;
    }
    
    /**
     * Filtrar por estado personalizado
     */
    public static function filter_by_custom_status($query) {
        global $pagenow, $typenow;
        
        if ($pagenow !== 'edit.php' || $typenow !== 'gdm_regla' || !$query->is_main_query()) {
            return;
        }
        
        // Si no hay filtro de estado, mostrar todos los estados personalizados
        if (!isset($_GET['post_status'])) {
            $query->set('post_status', ['habilitada', 'deshabilitada', 'programada', 'revisar', 'terminada', 'publish']);
        }
    }
}

GDM_Regla_Status_Manager::init();