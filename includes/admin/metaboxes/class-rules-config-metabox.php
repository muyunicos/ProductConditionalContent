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
    public static function save_metabox($post_id, $post) {
        if (!isset($_POST['gdm_nonce']) || !wp_verify_nonce($_POST['gdm_nonce'], 'gdm_save_rule_data')) {
            error_log('GDM: Nonce inválido al guardar regla ID ' . $post_id);
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            error_log('GDM: Autosave detectado, no guardando regla ID ' . $post_id);
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            error_log('GDM: Usuario sin permisos para editar regla ID ' . $post_id);
            return;
        }

        error_log('GDM: Guardando regla ID ' . $post_id);
        error_log('GDM: POST data: ' . print_r($_POST, true));

        // Información básica
        $prioridad = isset($_POST['gdm_prioridad']) ? absint($_POST['gdm_prioridad']) : 10;
        update_post_meta($post_id, '_gdm_prioridad', $prioridad);
        error_log('GDM: Prioridad guardada: ' . $prioridad);
        
        $reutilizable = isset($_POST['gdm_reutilizable']) ? '1' : '0';
        update_post_meta($post_id, '_gdm_reutilizable', $reutilizable);
        
        $aplicar_a = isset($_POST['gdm_aplicar_a']) ? array_map('sanitize_text_field', $_POST['gdm_aplicar_a']) : [];
        update_post_meta($post_id, '_gdm_aplicar_a', $aplicar_a);
        error_log('GDM: Aplicar a guardado: ' . print_r($aplicar_a, true));

        if (class_exists('GDM_Scope_Manager')) {
            $scope_manager = GDM_Scope_Manager::instance();
            $scope_manager->save_all($post_id);
            error_log('GDM: Ámbitos guardados correctamente');
        } else {
            error_log('GDM: ERROR - Scope Manager no disponible');
        }
        
        error_log('GDM: Regla ID ' . $post_id . ' guardada exitosamente');
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