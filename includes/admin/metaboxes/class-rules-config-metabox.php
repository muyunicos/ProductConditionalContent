<?php
/**
 * Metabox de Configuración de Reglas
 * Compatible con WordPress 6.8.3, PHP 8.2, WooCommerce 10.2.2
 * 
 * ✅ FIX v6.2.6: Permitir guardado de auto-draft si viene con nonce
 * 
 * @package ProductConditionalContent
 * @since 5.0.1
 */

if (!defined('ABSPATH')) exit;

final class GDM_Rules_Config_Metabox {
    
    public static function init() {
        add_action('add_meta_boxes', [__CLASS__, 'add_metabox']);
        add_action('save_post_gdm_regla', [__CLASS__, 'save_metabox'], 10, 2);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    public static function enqueue_assets($hook) {
        $screen = get_current_screen();
        if ($screen->id !== 'gdm_regla') {
            return;
        }

        wp_enqueue_script(
            'gdm-reglas-metabox',
            GDM_PLUGIN_URL . 'assets/admin/js/metaboxes/rules-config-metabox.js',
            ['jquery'],
            GDM_VERSION,
            true
        );

        wp_enqueue_script(
            'gdm-modules-toggle-handler',
            GDM_PLUGIN_URL . 'assets/admin/js/modules/modules-toggle-handler.js',
            ['jquery'],
            GDM_VERSION,
            true
        );
        
        wp_enqueue_style(
            'gdm-reglas-metabox',
            GDM_PLUGIN_URL . 'assets/admin/css/rules-config-metabox.css',
            [],
            GDM_VERSION
        );
        
        wp_localize_script('gdm-reglas-metabox', 'gdmReglasMetabox', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gdm_admin_nonce'),
            'i18n' => [
                'selectModule' => __('Selecciona al menos un módulo', 'product-conditional-content'),
                'requiredFields' => __('Completa los campos requeridos', 'product-conditional-content'),
            ],
        ]);
    }

    public static function add_metabox() {
        add_meta_box(
            'gdm_regla_config',
            __('Configuración de la Regla', 'product-conditional-content'),
            [__CLASS__, 'render_metabox'],
            'gdm_regla',
            'normal',
            'high'
        );
    }

    public static function render_metabox($post) {
        wp_nonce_field('gdm_save_rule_data', 'gdm_nonce');
        
        $reutilizable = get_post_meta($post->ID, '_gdm_reutilizable', true);
        $aplicar_a = get_post_meta($post->ID, '_gdm_aplicar_a', true) ?: [];
        
        ?>
        <div class="gdm-regla-config-wrapper">
            
            <!-- Sección: Reutilizable -->
            <div class="gdm-config-section">
                <h3><?php _e('🔄 Tipo de Regla', 'product-conditional-content'); ?></h3>
                <label class="gdm-checkbox-label">
                    <input type="checkbox" 
                           name="gdm_reutilizable" 
                           id="gdm_reutilizable" 
                           value="1" 
                           <?php checked($reutilizable, '1'); ?>>
                    <span><?php _e('Regla reutilizable (puede aplicarse a múltiples productos)', 'product-conditional-content'); ?></span>
                </label>
                <p class="description">
                    <?php _e('Si está marcada, esta regla puede ser asignada manualmente desde la edición de productos.', 'product-conditional-content'); ?>
                </p>
            </div>
            
            <hr class="gdm-separator">
            
            <!-- Sección: Aplica a (Módulos) -->
            <div class="gdm-config-section">
                <h3><?php _e('📦 Aplica a (Módulos)', 'product-conditional-content'); ?></h3>
                <p class="description">
                    <?php _e('Selecciona los tipos de contenido que esta regla modificará:', 'product-conditional-content'); ?>
                </p>
                
                <div class="gdm-modules-grid">
                    <?php
                    if (class_exists('GDM_Module_Manager')) {
                        $module_manager = GDM_Module_Manager::instance();
                        $modules = $module_manager->get_modules_ordered();
                        
                        foreach ($modules as $module_id => $module_config) {
                            if (!$module_config['enabled']) {
                                continue;
                            }
                            
                            $is_checked = in_array($module_id, $aplicar_a);
                            ?>
                            <label class="gdm-module-checkbox <?php echo $is_checked ? 'active' : ''; ?>">
                                <input type="checkbox" 
                                       class="gdm-module-toggle"
                                       name="gdm_aplicar_a[]" 
                                       value="<?php echo esc_attr($module_id); ?>"
                                       data-module="<?php echo esc_attr($module_id); ?>"
                                       <?php checked($is_checked); ?>>
                                <span class="gdm-module-icon"><?php echo esc_html($module_config['icon']); ?></span>
                                <span class="gdm-module-label"><?php echo esc_html($module_config['label']); ?></span>
                            </label>
                            <?php
                        }
                    }
                    ?>
                </div>
                
                <p class="gdm-validation-message" id="gdm-modules-validation" style="display:none; color:#d63638;">
                    <?php _e('⚠️ Debes seleccionar al menos un módulo', 'product-conditional-content'); ?>
                </p>
            </div>
            
            <hr class="gdm-separator">
            
            <!-- Sección: Ámbitos (Scopes) -->
            <div class="gdm-config-section">
                <h3><?php _e('🎯 Ámbito de Aplicación', 'product-conditional-content'); ?></h3>
                <p class="description">
                    <?php _e('Define a qué productos se aplicará esta regla:', 'product-conditional-content'); ?>
                </p>
                
                <div class="gdm-scopes-wrapper">
                    <?php
                    if (class_exists('GDM_Scope_Manager')) {
                        $scope_manager = GDM_Scope_Manager::instance();
                        $scope_manager->render_all($post->ID);
                    }
                    ?>
                </div>
            </div>
            
        </div>
        <?php
    }

    /**
     * ✅ FIX v6.2.6: Guardar metabox con validación corregida
     * 
     * @param int $post_id ID del post
     * @param WP_Post $post Objeto post
     */
    public static function save_metabox($post_id, $post) {
        // ========================================================================
        // ✅ VALIDACIÓN PRELIMINAR: Detectar contextos donde NO se debe procesar
        // ========================================================================
        
        // 1️⃣ Ignorar autosave (guardado automático de WordPress)
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            error_log('⚠️ GDM: Autosave detectado, saltando guardado de regla ID ' . $post_id);
            return;
        }
        
        // 2️⃣ ✅ FIX v6.2.6: Ignorar auto-drafts SOLO si no viene de un guardado manual
        // WordPress crea auto-draft al abrir "Agregar nueva", pero al guardar manualmente
        // el post sigue siendo auto-draft hasta que se procese wp_insert_post_data
        if ($post->post_status === 'auto-draft') {
            // ✅ PERMITIR guardado si viene con el nonce (guardado manual del usuario)
            $has_nonce = isset($_POST['gdm_nonce']);
            
            if (!$has_nonce) {
                error_log('⚠️ GDM: Auto-draft sin nonce detectado, saltando guardado de regla ID ' . $post_id);
                return;
            }
            
            error_log('ℹ️ GDM: Auto-draft CON nonce detectado (primer guardado), continuando...');
        }
        
        // 3️⃣ Verificar que es el post type correcto
        if ($post->post_type !== 'gdm_regla') {
            error_log('⚠️ GDM: Post type incorrecto (' . $post->post_type . '), esperado: gdm_regla');
            return;
        }
        
        // 4️⃣ Detectar guardados AJAX (heartbeat, autosave periódico)
        if (defined('DOING_AJAX') && DOING_AJAX) {
            // Permitir solo si viene del editor de bloques
            $is_block_editor = isset($_POST['action']) && $_POST['action'] === 'editpost';
            if (!$is_block_editor) {
                error_log('⚠️ GDM: AJAX detectado (no es guardado manual), saltando regla ID ' . $post_id);
                return;
            }
        }
        
        // ========================================================================
        // ✅ VALIDACIÓN DE NONCE: Verificar integridad de la solicitud
        // ========================================================================
        
        // Verificar existencia del nonce
        if (!isset($_POST['gdm_nonce'])) {
            error_log('❌ GDM: Campo gdm_nonce NO existe en $_POST para regla ID ' . $post_id);
            error_log('Campos POST disponibles: ' . implode(', ', array_keys($_POST)));
            return;
        }
        
        // Validar nonce
        $nonce_value = sanitize_text_field($_POST['gdm_nonce']);
        $nonce_valid = wp_verify_nonce($nonce_value, 'gdm_save_rule_data');
        
        if (!$nonce_valid) {
            error_log('❌ GDM: Nonce inválido al guardar regla ID ' . $post_id);
            error_log('Nonce recibido: ' . substr($nonce_value, 0, 20) . '...');
            return;
        }
        
        // ========================================================================
        // ✅ VALIDACIÓN DE PERMISOS: Verificar capacidades del usuario
        // ========================================================================
        
        if (!current_user_can('edit_post', $post_id)) {
            error_log('❌ GDM: Usuario sin permisos para editar regla ID ' . $post_id);
            return;
        }
        
        // ========================================================================
        // ✅ GUARDADO DE DATOS: Procesar y guardar campos del metabox
        // ========================================================================
        
        try {
            error_log('🔵 GDM: Iniciando guardado de regla ID ' . $post_id);
            
            // 1️⃣ GUARDAR: Título (ya viene en $_POST['post_title'] - WordPress lo procesa)
            // No es necesario guardarlo manualmente
            
            // 2️⃣ GUARDAR: Reutilizable
            $reutilizable = isset($_POST['gdm_reutilizable']) && $_POST['gdm_reutilizable'] === '1' ? '1' : '0';
            update_post_meta($post_id, '_gdm_reutilizable', $reutilizable);
            error_log('✅ Reutilizable guardada: ' . $reutilizable);
            
            // 3️⃣ GUARDAR: Aplica a (Módulos seleccionados)
            $aplicar_a = [];
            
            if (isset($_POST['gdm_aplicar_a']) && is_array($_POST['gdm_aplicar_a'])) {
                $aplicar_a = array_map('sanitize_text_field', $_POST['gdm_aplicar_a']);
                
                // Validar que los módulos existen
                if (class_exists('GDM_Module_Manager')) {
                    $module_manager = GDM_Module_Manager::instance();
                    $valid_modules = [];
                    
                    foreach ($aplicar_a as $module_id) {
                        if ($module_manager->is_module_registered($module_id)) {
                            $valid_modules[] = $module_id;
                        } else {
                            error_log('⚠️ GDM: Módulo inválido ignorado: ' . $module_id);
                        }
                    }
                    
                    $aplicar_a = $valid_modules;
                }
            }
            
            update_post_meta($post_id, '_gdm_aplicar_a', $aplicar_a);
            error_log('✅ Módulos guardados: ' . implode(', ', $aplicar_a));
            
            // 4️⃣ GUARDAR: Scopes (ámbitos de aplicación)
            if (class_exists('GDM_Scope_Manager')) {
                $scope_manager = GDM_Scope_Manager::instance();
                $scope_manager->save_all($post_id);
                error_log('✅ Scopes guardados via Scope Manager');
            }
            
            // 5️⃣ LIMPIAR CACHÉ
            wp_cache_delete("gdm_regla_{$post_id}", 'gdm_reglas');
            wp_cache_delete("gdm_rule_data_{$post_id}", 'gdm_rules');
            
            // 6️⃣ HOOK PERSONALIZADO: Permitir extensiones guardar datos adicionales
            do_action('gdm_after_save_rule_config', $post_id, $post, $_POST);
            
            error_log('✅✅✅ GDM: Regla ID ' . $post_id . ' guardada EXITOSAMENTE');
            
        } catch (Exception $e) {
            error_log('❌❌❌ EXCEPCIÓN CRÍTICA al guardar regla ID ' . $post_id);
            error_log('Mensaje: ' . $e->getMessage());
            error_log('Archivo: ' . $e->getFile() . ':' . $e->getLine());
            
            // Notificar al administrador
            if (current_user_can('manage_options')) {
                add_action('admin_notices', function() use ($e) {
                    ?>
                    <div class="notice notice-error is-dismissible">
                        <p><strong><?php _e('Error al guardar la regla:', 'product-conditional-content'); ?></strong></p>
                        <p><?php echo esc_html($e->getMessage()); ?></p>
                    </div>
                    <?php
                });
            }
        }
    }
}

GDM_Rules_Config_Metabox::init();