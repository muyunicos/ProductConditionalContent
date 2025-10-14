<?php
/**
 * Gestor de Estados Personalizados para Reglas
 * Modificación del metabox "Publicar" nativo de WordPress
 * Compatible con WordPress 6.8.3, PHP 8.2
 * 
 * Estados personalizados:
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
        
        // Modificar el metabox "Publicar" nativo
        add_action('post_submitbox_misc_actions', [__CLASS__, 'modify_publish_metabox']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_publish_metabox_scripts']);
        
        // Guardar datos del metabox modificado
        add_action('save_post_gdm_regla', [__CLASS__, 'save_publish_metabox_data'], 10, 2);
        
        // Quick Edit
        add_action('quick_edit_custom_box', [__CLASS__, 'quick_edit_fields'], 10, 2);
        add_action('save_post_gdm_regla', [__CLASS__, 'save_quick_edit'], 10, 2);
        
        // Bulk Edit
        add_action('bulk_edit_custom_box', [__CLASS__, 'bulk_edit_fields'], 10, 2);
        
        // AJAX para Quick Edit
        add_action('wp_ajax_gdm_get_regla_data', [__CLASS__, 'ajax_get_regla_data']);
        
        // Display States en listado
        add_filter('display_post_states', [__CLASS__, 'display_post_states'], 10, 2);
        
        // Cron para actualizar estados
        add_action('gdm_check_regla_schedules', [__CLASS__, 'check_schedules']);
        
        // Filtros de estado en listado
        add_filter('views_edit-gdm_regla', [__CLASS__, 'custom_status_views']);
        add_action('pre_get_posts', [__CLASS__, 'filter_by_custom_status']);
    }
    
    /**
     * Registrar estados personalizados
     */
    public static function register_custom_statuses() {
        // Habilitada (activa)
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
     * Modificar el metabox "Publicar" para gdm_regla
     */
    public static function modify_publish_metabox() {
        global $post, $current_screen;
        
        if (!$post || $current_screen->post_type !== 'gdm_regla') {
            return;
        }
        
        // Obtener datos actuales
        $current_status = $post->post_status;
        $fecha_inicio = get_post_meta($post->ID, '_gdm_fecha_inicio', true);
        $fecha_fin = get_post_meta($post->ID, '_gdm_fecha_fin', true);
        $habilitar_fin = get_post_meta($post->ID, '_gdm_habilitar_fecha_fin', true);
        
        // Mapear publish a habilitada
        if ($current_status === 'publish' || $current_status === 'auto-draft') {
            $current_status = 'habilitada';
        }
        
        ?>
        <!-- Campos ocultos para pasar datos al JavaScript -->
        <input type="hidden" id="gdm_current_status" value="<?php echo esc_attr($current_status); ?>">
        <input type="hidden" id="gdm_has_fecha_inicio" value="<?php echo $fecha_inicio ? '1' : '0'; ?>">
        <input type="hidden" id="gdm_has_fecha_fin" value="<?php echo $fecha_fin ? '1' : '0'; ?>">
        
        <!-- Sección de Fecha de Fin (se insertará vía JavaScript) -->
        <div id="gdm-fecha-fin-section" style="display:none;">
            <div class="misc-pub-section">
                <label style="display: block; margin-bottom: 8px;">
                    <input type="checkbox" 
                           name="gdm_habilitar_fecha_fin" 
                           id="gdm_habilitar_fecha_fin" 
                           value="1" 
                           <?php checked($habilitar_fin, '1'); ?>>
                    <strong><?php _e('Habilitar fecha de finalización', 'product-conditional-content'); ?></strong>
                </label>
                
                <div id="gdm_fecha_fin_wrapper" style="<?php echo $habilitar_fin !== '1' ? 'display:none;' : ''; ?>">
                    <span id="gdm-fecha-fin-display">
                        <?php 
                        if ($habilitar_fin === '1' && $fecha_fin) {
                            echo '<b>' . date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($fecha_fin)) . '</b>';
                        } else {
                            echo '<b>' . __('Sin fecha de fin', 'product-conditional-content') . '</b>';
                        }
                        ?>
                    </span>
                    
                    <a href="#gdm_fecha_fin" class="edit-gdm-fecha-fin hide-if-no-js" role="button">
                        <span aria-hidden="true"><?php _e('Editar', 'product-conditional-content'); ?></span>
                    </a>
                    
                    <fieldset id="gdm-fecha-fin-div" class="hide-if-js">
                        <legend class="screen-reader-text"><?php _e('Fecha de Fin', 'product-conditional-content'); ?></legend>
                        <div class="timestamp-wrap">
                            <?php
                            $fecha_fin_parsed = $fecha_fin ? strtotime($fecha_fin) : current_time('timestamp');
                            $jj_fin = date('d', $fecha_fin_parsed);
                            $mm_fin = date('m', $fecha_fin_parsed);
                            $aa_fin = date('Y', $fecha_fin_parsed);
                            $hh_fin = date('H', $fecha_fin_parsed);
                            $mn_fin = date('i', $fecha_fin_parsed);
                            ?>
                            
                            <label>
                                <span class="screen-reader-text"><?php _e('Día', 'product-conditional-content'); ?></span>
                                <input type="text" id="gdm_jj_fin" name="gdm_jj_fin" value="<?php echo $jj_fin; ?>" size="2" maxlength="2" autocomplete="off" class="form-required" inputmode="numeric">
                            </label> de 
                            
                            <label>
                                <span class="screen-reader-text"><?php _e('Mes', 'product-conditional-content'); ?></span>
                                <select class="form-required" id="gdm_mm_fin" name="gdm_mm_fin">
                                    <?php
                                    for ($i = 1; $i <= 12; $i++) {
                                        $monthnum = zeroise($i, 2);
                                        $monthtext = date_i18n('M', mktime(0, 0, 0, $i, 1));
                                        echo '<option value="' . $monthnum . '"' . selected($monthnum, $mm_fin, false) . '>' . $monthnum . '-' . $monthtext . '</option>';
                                    }
                                    ?>
                                </select>
                            </label> de 
                            
                            <label>
                                <span class="screen-reader-text"><?php _e('Año', 'product-conditional-content'); ?></span>
                                <input type="text" id="gdm_aa_fin" name="gdm_aa_fin" value="<?php echo $aa_fin; ?>" size="4" maxlength="4" autocomplete="off" class="form-required" inputmode="numeric">
                            </label> a las 
                            
                            <label>
                                <span class="screen-reader-text"><?php _e('Hora', 'product-conditional-content'); ?></span>
                                <input type="text" id="gdm_hh_fin" name="gdm_hh_fin" value="<?php echo $hh_fin; ?>" size="2" maxlength="2" autocomplete="off" class="form-required" inputmode="numeric">
                            </label>:
                            
                            <label>
                                <span class="screen-reader-text"><?php _e('Minuto', 'product-conditional-content'); ?></span>
                                <input type="text" id="gdm_mn_fin" name="gdm_mn_fin" value="<?php echo $mn_fin; ?>" size="2" maxlength="2" autocomplete="off" class="form-required" inputmode="numeric">
                            </label>
                        </div>
                        
                        <p>
                            <a href="#gdm_fecha_fin" class="save-gdm-fecha-fin hide-if-no-js button"><?php _e('Aceptar', 'product-conditional-content'); ?></a>
                            <a href="#gdm_fecha_fin" class="cancel-gdm-fecha-fin hide-if-no-js button-cancel"><?php _e('Cancelar', 'product-conditional-content'); ?></a>
                        </p>
                    </fieldset>
                </div>
            </div>
        </div>
        
        <style>
        /* Estilos para la sección de fecha de fin */
        #gdm-fecha-fin-section .misc-pub-section {
            padding: 10px 12px;
            border-top: 1px solid #dcdcde;
        }
        #gdm_fecha_fin_wrapper {
            margin-top: 8px;
            padding-left: 20px;
        }
        #gdm-fecha-fin-display {
            margin-right: 8px;
        }
        </style>
        <?php
    }
    
    /**
     * Enqueue scripts para modificar el metabox de publicación
     */
    public static function enqueue_publish_metabox_scripts($hook) {
        global $post_type;
        
        if (!in_array($hook, ['post.php', 'post-new.php']) || $post_type !== 'gdm_regla') {
            return;
        }
        
        wp_enqueue_script(
            'gdm-regla-publish-metabox',
            plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/admin/regla-publish-metabox.js',
            ['jquery'],
            '1.0.0',
            true
        );
        
        wp_localize_script('gdm-regla-publish-metabox', 'gdmPublishMetabox', [
            'statusLabels' => [
                'habilitada' => __('Habilitada', 'product-conditional-content'),
                'deshabilitada' => __('Deshabilitada', 'product-conditional-content'),
                'programada' => __('Programada', 'product-conditional-content'),
                'revisar' => __('Revisar', 'product-conditional-content'),
                'terminada' => __('Terminada', 'product-conditional-content'),
            ],
            'i18n' => [
                'publishOn' => __('Activar el', 'product-conditional-content'),
                'immediately' => __('inmediatamente', 'product-conditional-content'),
                'startDate' => __('Fecha de Inicio', 'product-conditional-content'),
                'endDate' => __('Fecha de Fin', 'product-conditional-content'),
            ],
        ]);
        
        // Estilos adicionales
        wp_add_inline_style('wp-admin', '
            /* Ocultar sección de visibilidad en gdm_regla */
            .post-type-gdm_regla #visibility {
                display: none !important;
            }
            
            /* Columnas personalizadas */
            .gdm-badge { 
                background: #f0f0f1; 
                padding: 2px 8px; 
                border-radius: 3px; 
                font-size: 11px; 
                margin-right: 4px;
                display: inline-block;
            }
            .gdm-fechas-column { 
                font-size: 13px; 
                line-height: 1.6; 
            }
            
            /* Quick Edit: ocultar campos no deseados */
            .post-type-gdm_regla .inline-edit-password-input,
            .post-type-gdm_regla .inline-edit-private { 
                display: none !important; 
            }
        ');
        
        // Script para Quick Edit
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
    }
    
    /**
     * Guardar datos del metabox de publicación modificado
     */
    public static function save_publish_metabox_data($post_id, $post) {
        // Verificar permisos
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Evitar autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Guardar estado personalizado
        if (isset($_POST['post_status'])) {
            $status = sanitize_text_field($_POST['post_status']);
            $allowed_statuses = ['habilitada', 'deshabilitada', 'programada', 'revisar', 'terminada'];
            
            if (in_array($status, $allowed_statuses)) {
                // Actualizar estado del post
                remove_action('save_post_gdm_regla', [__CLASS__, 'save_publish_metabox_data'], 10);
                wp_update_post([
                    'ID' => $post_id,
                    'post_status' => $status,
                ]);
                add_action('save_post_gdm_regla', [__CLASS__, 'save_publish_metabox_data'], 10, 2);
            }
        }
        
        // Guardar fecha de inicio (desde el timestamp nativo de WordPress)
        if (isset($_POST['aa'], $_POST['mm'], $_POST['jj'], $_POST['hh'], $_POST['mn'])) {
            $aa = intval($_POST['aa']);
            $mm = intval($_POST['mm']);
            $jj = intval($_POST['jj']);
            $hh = intval($_POST['hh']);
            $mn = intval($_POST['mn']);
            $ss = isset($_POST['ss']) ? intval($_POST['ss']) : 0;
            
            $fecha_inicio = sprintf('%04d-%02d-%02d %02d:%02d:%02d', $aa, $mm, $jj, $hh, $mn, $ss);
            update_post_meta($post_id, '_gdm_fecha_inicio', $fecha_inicio);
        }
        
        // Guardar opción de habilitar fecha fin
        $habilitar_fin = isset($_POST['gdm_habilitar_fecha_fin']) ? '1' : '0';
        update_post_meta($post_id, '_gdm_habilitar_fecha_fin', $habilitar_fin);
        
        // Guardar fecha fin
        if ($habilitar_fin === '1' && isset($_POST['gdm_aa_fin'], $_POST['gdm_mm_fin'], $_POST['gdm_jj_fin'], $_POST['gdm_hh_fin'], $_POST['gdm_mn_fin'])) {
            $aa_fin = intval($_POST['gdm_aa_fin']);
            $mm_fin = intval($_POST['gdm_mm_fin']);
            $jj_fin = intval($_POST['gdm_jj_fin']);
            $hh_fin = intval($_POST['gdm_hh_fin']);
            $mn_fin = intval($_POST['gdm_mn_fin']);
            
            $fecha_fin = sprintf('%04d-%02d-%02d %02d:%02d:00', $aa_fin, $mm_fin, $jj_fin, $hh_fin, $mn_fin);
            update_post_meta($post_id, '_gdm_fecha_fin', $fecha_fin);
        } else {
            delete_post_meta($post_id, '_gdm_fecha_fin');
        }
    }
    
    /**
     * Configurar columnas personalizadas
     */
    public static function custom_columns($columns) {
        $new_columns = [];
        
        if (isset($columns['cb'])) {
            $new_columns['cb'] = $columns['cb'];
        }
        
        $new_columns['title'] = __('Nombre', 'product-conditional-content');
        $new_columns['aplica_a'] = __('Aplica a', 'product-conditional-content');
        $new_columns['fechas'] = __('Fecha Inicio / Fin', 'product-conditional-content');
        $new_columns['estado'] = __('Estado', 'product-conditional-content');
        
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
     * Quick Edit - Campos personalizados
     */
    public static function quick_edit_fields($column_name, $post_type) {
        if ($post_type !== 'gdm_regla' || $column_name !== 'estado') {
            return;
        }
        
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
     * Bulk Edit
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
     * AJAX: Obtener datos de regla
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
     * Display post states
     */
    public static function display_post_states($states, $post) {
        if ($post->post_type !== 'gdm_regla') {
            return $states;
        }
        
        unset($states['publish']);
        
        return $states;
    }
    
    /**
     * Cron: Verificar programaciones
     */
    public static function check_schedules() {
        $now = current_time('mysql');
        
        // Activar reglas programadas
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
        
        // Finalizar reglas expiradas
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
        $counts = self::get_status_counts();
        
        $class = (!isset($_GET['post_status'])) ? 'current' : '';
        $views['all'] = sprintf(
            '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
            admin_url('edit.php?post_type=gdm_regla'),
            $class,
            __('Todas', 'product-conditional-content'),
            array_sum($counts)
        );
        
        foreach (['habilitada', 'deshabilitada', 'programada', 'revisar', 'terminada'] as $status) {
            if ($counts[$status] > 0) {
                $class = (isset($_GET['post_status']) && $_GET['post_status'] === $status) ? 'current' : '';
                $labels = [
                    'habilitada' => __('Habilitadas', 'product-conditional-content'),
                    'deshabilitada' => __('Deshabilitadas', 'product-conditional-content'),
                    'programada' => __('Programadas', 'product-conditional-content'),
                    'revisar' => __('Revisar', 'product-conditional-content'),
                    'terminada' => __('Terminadas', 'product-conditional-content'),
                ];
                
                $views[$status] = sprintf(
                    '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
                    admin_url('edit.php?post_type=gdm_regla&post_status=' . $status),
                    $class,
                    $labels[$status],
                    $counts[$status]
                );
            }
        }
        
        unset($views['publish'], $views['draft'], $views['pending']);
        
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
        
        if (!isset($_GET['post_status'])) {
            $query->set('post_status', ['habilitada', 'deshabilitada', 'programada', 'revisar', 'terminada', 'publish']);
        }
    }
}

GDM_Regla_Status_Manager::init();