<?php
/**
 * Metabox de Configuración General de Reglas v6.2
 * Sistema modular con ámbitos independientes
 * Compatible con WordPress 6.8.3, PHP 8.2, WooCommerce 10.2.2
 * 
 * @package ProductConditionalContent
 * @since 6.2.0
 * @date 2025-10-15
 */

if (!defined('ABSPATH')) exit;

final class GDM_Reglas_Metabox {
    
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

        wp_enqueue_script('jquery-ui-sortable');
        
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
                'noResults' => __('No se encontraron resultados', 'product-conditional-content'),
            ]
        ]);
    }

    public static function add_metabox() {
        add_meta_box(
            'gdm_regla_config',
            __('⚙️ Configuración General de la Regla', 'product-conditional-content'),
            [__CLASS__, 'render_metabox'],
            'gdm_regla',
            'normal',
            'high'
        );
    }

    public static function render_metabox($post) {
        wp_nonce_field('gdm_save_rule_data', 'gdm_nonce');
        if (defined('WP_DEBUG') && WP_DEBUG) {
            echo '<!-- GDM Debug: Nonce renderizado correctamente -->';
            echo '<!-- Nonce name: gdm_nonce -->';
            echo '<!-- Nonce action: gdm_save_rule_data -->';
        }
        
        $data = self::get_rule_data($post->ID);
        
        // Obtener módulos con validación
        $available_modules = [];
        $manager_status = 'No inicializado';
        
        if (class_exists('GDM_Module_Manager')) {
            try {
                $module_manager = GDM_Module_Manager::instance();
                $available_modules = $module_manager->get_modules_with_icons();
                $count = $module_manager->get_modules_count();
                $manager_status = sprintf('Registrados: %d | Habilitados: %d', $count['total'], $count['enabled']);
            } catch (Exception $e) {
                $manager_status = 'Error: ' . $e->getMessage();
            }
        }
        ?>
        <div class="gdm-config-general">
            
            <!-- Información Básica -->
            <div class="gdm-section">
                <div class="gdm-section-header">
                    <h3>
                        <span class="dashicons dashicons-admin-generic"></span>
                        <?php _e('Información Básica', 'product-conditional-content'); ?>
                    </h3>
                </div>
                
                <div class="gdm-row">
                    <div class="gdm-col-6">
                        <label for="gdm_prioridad">
                            <strong><?php _e('🔢 Prioridad:', 'product-conditional-content'); ?></strong>
                        </label>
                        <input type="number" 
                               id="gdm_prioridad" 
                               name="gdm_prioridad" 
                               value="<?php echo esc_attr($data['prioridad']); ?>" 
                               min="1" 
                               max="999" 
                               class="small-text">
                        <p class="description">
                            <?php _e('Número más bajo = mayor prioridad', 'product-conditional-content'); ?>
                        </p>
                    </div>
                    
                    <div class="gdm-col-6">
                        <label>
                            <input type="checkbox" 
                                   name="gdm_reutilizable" 
                                   value="1" 
                                   <?php checked($data['reutilizable'], '1'); ?>>
                            <strong><?php _e('🔄 Regla Reutilizable', 'product-conditional-content'); ?></strong>
                        </label>
                        <p class="description">
                            <?php _e('Shortcode: ', 'product-conditional-content'); ?>
                            <code>[rule-<?php echo esc_attr($post->ID); ?>]</code>
                        </p>
                    </div>
                </div>
            </div>
            
            <hr class="gdm-separator">
            
            <!-- ✅ ORDEN CORREGIDO: Ámbito ANTES de Aplica a -->
            
            <!-- Ámbito de Aplicación (MODULAR) -->
            <div class="gdm-section">
                <div class="gdm-section-header">
                    <h3>
                        <span class="dashicons dashicons-category"></span>
                        <?php _e('Ámbito de Aplicación', 'product-conditional-content'); ?>
                    </h3>
                </div>
                
                <p class="description">
                    <?php _e('Define a qué productos se aplicará esta regla. Si no seleccionas nada, se aplicará a todos.', 'product-conditional-content'); ?>
                </p>
                
                <div class="gdm-scopes-container">
                    <?php
                    // ✅ Renderizar todos los ámbitos dinámicamente
                    if (class_exists('GDM_Scope_Manager')) {
                        $scope_manager = GDM_Scope_Manager::instance();
                        $scope_manager->render_all($post->ID);
                    } else {
                        echo '<p class="notice notice-warning inline">⚠️ El gestor de ámbitos no está disponible.</p>';
                    }
                    ?>
                </div>
            </div>
            
            <hr class="gdm-separator">
            
            <!-- Aplica a (MÓDULOS) -->
            <div class="gdm-section">
                <div class="gdm-section-header">
                    <h3>
                        <span class="dashicons dashicons-admin-tools"></span>
                        <?php _e('Aplica a', 'product-conditional-content'); ?>
                    </h3>
                </div>
                
                <p class="description">
                    <?php _e('Selecciona los módulos que deseas activar para esta regla.', 'product-conditional-content'); ?>
                </p>
                
                <?php if (empty($available_modules)): ?>
                    <div class="notice notice-warning inline">
                        <p>
                            <strong><?php _e('⚠️ No hay módulos disponibles', 'product-conditional-content'); ?></strong><br>
                            <small style="color:#666;"><?php echo esc_html($manager_status); ?></small>
                        </p>
                    </div>
                <?php else: ?>
                    <div class="gdm-modules-grid">
                        <?php foreach ($available_modules as $module_id => $module): ?>
                            <label class="gdm-module-checkbox <?php echo in_array($module_id, $data['aplicar_a']) ? 'active' : ''; ?>">
                                <input type="checkbox" 
                                       name="gdm_aplicar_a[]" 
                                       value="<?php echo esc_attr($module_id); ?>" 
                                       class="gdm-module-toggle"
                                       data-module="<?php echo esc_attr($module_id); ?>"
                                       <?php checked(in_array($module_id, $data['aplicar_a'])); ?>>
                                <span class="gdm-module-icon"><?php echo esc_html($module['icon']); ?></span>
                                <span class="gdm-module-label"><?php echo esc_html($module['label']); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
        </div>
        <?php
    }

    /**
     * Guardar datos
     */
   /**
 * Guardar datos del metabox de configuración general
 * ✅ VERSIÓN CORREGIDA con validación mejorada de nonce y debug detallado
 */
public static function save_metabox($post_id, $post) {
    // ✅ DEBUG: Registrar inicio del guardado
    error_log('=== GDM SAVE METABOX DEBUG START ===');
    error_log('Post ID: ' . $post_id);
    error_log('Post Type: ' . $post->post_type);
    error_log('Post Status: ' . $post->post_status);
    
    // ✅ VALIDACIÓN 1: Verificar que existe el nonce
    if (!isset($_POST['gdm_nonce'])) {
        error_log('❌ GDM: Campo gdm_nonce NO existe en $_POST');
        error_log('Campos POST disponibles: ' . implode(', ', array_keys($_POST)));
        return;
    }
    
    // ✅ VALIDACIÓN 2: Verificar validez del nonce
    $nonce_value = sanitize_text_field($_POST['gdm_nonce']);
    $nonce_valid = wp_verify_nonce($nonce_value, 'gdm_save_rule_data');
    
    error_log('Nonce recibido: ' . substr($nonce_value, 0, 20) . '...');
    error_log('Nonce válido: ' . ($nonce_valid ? 'SÍ' : 'NO'));
    
    if (!$nonce_valid) {
        error_log('❌ GDM: Nonce inválido al guardar regla ID ' . $post_id);
        error_log('Valor nonce: ' . $nonce_value);
        error_log('Acción esperada: gdm_save_rule_data');
        return;
    }
    
    // ✅ VALIDACIÓN 3: Evitar autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        error_log('⚠️ GDM: Autosave detectado, saltando guardado de regla ID ' . $post_id);
        return;
    }
    
    // ✅ VALIDACIÓN 4: Verificar permisos del usuario
    if (!current_user_can('edit_post', $post_id)) {
        error_log('❌ GDM: Usuario sin permisos para editar regla ID ' . $post_id);
        error_log('Usuario actual: ' . wp_get_current_user()->user_login);
        error_log('Capacidad requerida: edit_post');
        return;
    }
    
    // ✅ VALIDACIÓN 5: Verificar que es el post type correcto
    if ($post->post_type !== 'gdm_regla') {
        error_log('⚠️ GDM: Post type incorrecto (' . $post->post_type . '), esperado: gdm_regla');
        return;
    }
    
    // ========================================================================
    // ✅ TODAS LAS VALIDACIONES PASADAS - PROCEDER CON EL GUARDADO
    // ========================================================================
    
    error_log('✅ GDM: Todas las validaciones pasadas, procediendo con guardado...');
    
    try {
        // ✅ GUARDAR: Prioridad
        $prioridad = isset($_POST['gdm_prioridad']) ? absint($_POST['gdm_prioridad']) : 10;
        
        // Validar rango de prioridad
        if ($prioridad < 1) {
            $prioridad = 1;
        } elseif ($prioridad > 999) {
            $prioridad = 999;
        }
        
        $prioridad_guardada = update_post_meta($post_id, '_gdm_prioridad', $prioridad);
        error_log('✅ Prioridad guardada: ' . $prioridad . ' (resultado: ' . ($prioridad_guardada ? 'OK' : 'YA EXISTÍA') . ')');
        
        // ✅ GUARDAR: Reutilizable
        $reutilizable = isset($_POST['gdm_reutilizable']) && $_POST['gdm_reutilizable'] === '1' ? '1' : '0';
        update_post_meta($post_id, '_gdm_reutilizable', $reutilizable);
        error_log('✅ Reutilizable guardada: ' . $reutilizable);
        
        // ✅ GUARDAR: Aplica a (Módulos seleccionados)
        $aplicar_a = [];
        
        if (isset($_POST['gdm_aplicar_a']) && is_array($_POST['gdm_aplicar_a'])) {
            $aplicar_a = array_map('sanitize_text_field', $_POST['gdm_aplicar_a']);
            
            // Validar que los módulos existen
            if (class_exists('GDM_Module_Manager')) {
                $module_manager = GDM_Module_Manager::instance();
                $valid_modules = array_keys($module_manager->get_modules());
                
                // Filtrar solo módulos válidos
                $aplicar_a = array_intersect($aplicar_a, $valid_modules);
                
                error_log('Módulos válidos filtrados: ' . implode(', ', $aplicar_a));
            }
        }
        
        update_post_meta($post_id, '_gdm_aplicar_a', $aplicar_a);
        error_log('✅ Aplicar a guardado: [' . implode(', ', $aplicar_a) . '] (total: ' . count($aplicar_a) . ')');
        
        // ✅ GUARDAR: Ámbitos (Scopes) - SISTEMA MODULAR
        if (class_exists('GDM_Scope_Manager')) {
            try {
                $scope_manager = GDM_Scope_Manager::instance();
                $scope_manager->save_all($post_id);
                error_log('✅ Ámbitos guardados correctamente vía Scope Manager');
            } catch (Exception $e) {
                error_log('❌ ERROR al guardar ámbitos: ' . $e->getMessage());
                error_log('Stack trace: ' . $e->getTraceAsString());
            }
        } else {
            error_log('⚠️ ADVERTENCIA: GDM_Scope_Manager no está disponible, ámbitos no guardados');
        }
        
        // ✅ LIMPIAR CACHÉ (si existe sistema de caché)
        wp_cache_delete("gdm_regla_{$post_id}", 'gdm_reglas');
        wp_cache_delete("gdm_rule_data_{$post_id}", 'gdm_rules');
        
        error_log('✅ Caché limpiado para regla ID ' . $post_id);
        
        // ✅ HOOK PERSONALIZADO: Permitir extensiones guardar datos adicionales
        do_action('gdm_after_save_rule_config', $post_id, $post, $_POST);
        
        error_log('✅✅✅ GDM: Regla ID ' . $post_id . ' guardada EXITOSAMENTE');
        error_log('=== GDM SAVE METABOX DEBUG END ===');
        
    } catch (Exception $e) {
        error_log('❌❌❌ EXCEPCIÓN CRÍTICA al guardar regla ID ' . $post_id);
        error_log('Mensaje: ' . $e->getMessage());
        error_log('Archivo: ' . $e->getFile() . ':' . $e->getLine());
        error_log('Stack trace: ' . $e->getTraceAsString());
        
        // ✅ OPCIONAL: Notificar al admin
        if (current_user_can('manage_options')) {
            add_action('admin_notices', function() use ($e) {
                ?>
                <div class="notice notice-error is-dismissible">
                    <p>
                        <strong>Error al guardar regla:</strong>
                        <?php echo esc_html($e->getMessage()); ?>
                    </p>
                </div>
                <?php
            });
        }
    }
}

    /**
     * Obtener datos
     */
    private static function get_rule_data($post_id) {
        static $cache = [];
        if (isset($cache[$post_id])) return $cache[$post_id];

        $data = [
            'prioridad' => get_post_meta($post_id, '_gdm_prioridad', true) ?: 10,
            'reutilizable' => get_post_meta($post_id, '_gdm_reutilizable', true),
            'aplicar_a' => get_post_meta($post_id, '_gdm_aplicar_a', true) ?: [],
        ];
        
        $cache[$post_id] = $data;
        return $data;
    }
}

GDM_Reglas_Metabox::init();