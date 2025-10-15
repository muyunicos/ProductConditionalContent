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
        
        <?php self::render_global_scope_styles(); ?>
        <?php
    }
    
    /**
     * Estilos globales para ámbitos
     */
    private static function render_global_scope_styles() {
        ?>
        <style>
            /* Estilos globales para todos los ámbitos */
            .gdm-scopes-container {
                display: flex;
                flex-direction: column;
                gap: 15px;
            }
            
            .gdm-scope-group {
                border: 1px solid #ddd;
                border-radius: 4px;
                background: #fff;
                overflow: hidden;
                transition: all 0.3s ease;
            }
            
            .gdm-scope-group:hover {
                box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            }
            
            .gdm-scope-header {
                padding: 12px 15px;
                background: #f9f9f9;
                border-bottom: 1px solid #ddd;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 15px;
                cursor: pointer;
                transition: background 0.2s ease;
            }
            
            .gdm-scope-header:hover {
                background: #f0f0f1;
            }
            
            .gdm-scope-toggle {
                display: flex;
                align-items: center;
                margin: 0;
                cursor: pointer;
                user-select: none;
            }
            
            .gdm-scope-toggle input[type="checkbox"] {
                margin: 0 10px 0 0 !important;
                cursor: pointer;
            }
            
            .gdm-scope-toggle strong {
                font-size: 14px;
            }
            
            .gdm-scope-summary {
                display: flex;
                align-items: center;
                gap: 10px;
                flex: 1;
                padding-left: 15px;
                border-left: 2px solid #2271b1;
            }
            
            .gdm-summary-text {
                flex: 1;
                font-size: 12px;
                color: #135e96;
                line-height: 1.5;
            }
            
            .gdm-summary-text em {
                font-weight: 600;
                font-style: normal;
                color: #2271b1;
            }
            
            .gdm-scope-edit {
                padding: 4px 12px !important;
                height: auto !important;
                font-size: 12px !important;
                border-color: #2271b1 !important;
                color: #2271b1 !important;
                transition: all 0.2s ease;
            }
            
            .gdm-scope-edit:hover {
                background: #2271b1 !important;
                color: #fff !important;
            }
            
            .gdm-scope-content {
                padding: 15px;
                display: none;
                background: #fafafa;
                animation: slideDown 0.3s ease;
            }
            
            .gdm-scope-content.active {
                display: block;
            }
            
            .gdm-scope-actions {
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-top: 15px;
                padding-top: 15px;
                border-top: 1px solid #e0e0e0;
                gap: 10px;
            }
            
            .gdm-scope-save {
                padding: 8px 20px !important;
                height: auto !important;
                line-height: 1.4 !important;
                font-weight: 600 !important;
            }
            
            .gdm-scope-save .dashicons {
                vertical-align: middle;
                margin-right: 5px;
            }
            
            .gdm-scope-cancel {
                padding: 8px 16px !important;
                height: auto !important;
            }
            
            .gdm-selection-counter {
                font-size: 12px;
                color: #666;
                font-weight: 600;
                padding: 5px 12px;
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 3px;
            }
            
            .gdm-filter-input {
                width: 100%;
                padding: 10px 12px;
                border: 1px solid #ddd;
                border-radius: 4px;
                margin-bottom: 10px;
                box-sizing: border-box;
                transition: border-color 0.2s ease, box-shadow 0.2s ease;
            }
            
            .gdm-filter-input:focus {
                border-color: #2271b1;
                outline: none;
                box-shadow: 0 0 0 1px #2271b1;
            }
            
            .gdm-scope-list {
                max-height: 300px;
                overflow-y: auto;
                border: 1px solid #ddd;
                border-radius: 4px;
                padding: 8px;
                background: #fff;
                margin-bottom: 12px;
            }
            
            .gdm-scope-list::-webkit-scrollbar {
                width: 8px;
            }
            
            .gdm-scope-list::-webkit-scrollbar-track {
                background: #f1f1f1;
                border-radius: 4px;
            }
            
            .gdm-scope-list::-webkit-scrollbar-thumb {
                background: #c1c1c1;
                border-radius: 4px;
            }
            
            .gdm-scope-list::-webkit-scrollbar-thumb:hover {
                background: #a8a8a8;
            }
            
            .gdm-checkbox-item {
                display: flex;
                align-items: center;
                padding: 8px 10px;
                cursor: pointer;
                transition: background 0.2s ease;
                border-radius: 3px;
                margin-bottom: 2px;
                gap: 8px;
            }
            
            .gdm-checkbox-item:hover {
                background: #e8f0fe;
            }
            
            .gdm-checkbox-item input[type="checkbox"] {
                margin: 0 !important;
                cursor: pointer;
            }
            
            .gdm-checkbox-item span {
                flex: 1;
            }
            
            .gdm-item-count {
                color: #999;
                font-size: 11px;
            }
            
            .gdm-empty-state {
                padding: 30px;
                text-align: center;
                color: #999;
            }
            
            .gdm-empty-state .dashicons {
                font-size: 48px;
                width: 48px;
                height: 48px;
                margin-bottom: 10px;
                opacity: 0.3;
            }
            
            .gdm-empty-state p {
                margin: 0;
                font-size: 13px;
            }
            
            .gdm-field-group {
                margin-bottom: 15px;
            }
            
            .gdm-field-group label {
                display: block;
                margin-bottom: 5px;
            }
            
            @keyframes slideDown {
                from {
                    opacity: 0;
                    transform: translateY(-10px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            @media screen and (max-width: 782px) {
                .gdm-scope-header {
                    flex-direction: column;
                    align-items: flex-start;
                }
                
                .gdm-scope-summary {
                    padding-left: 0;
                    border-left: none;
                    padding-top: 10px;
                    border-top: 1px solid #ddd;
                }
                
                .gdm-scope-actions {
                    flex-direction: column;
                    gap: 10px;
                }
                
                .gdm-scope-save,
                .gdm-scope-cancel,
                .gdm-selection-counter {
                    width: 100%;
                    text-align: center;
                }
            }
        </style>
        <?php
    }

    /**
     * Guardar datos
     */
    public static function save_metabox($post_id, $post) {
        if (!isset($_POST['gdm_nonce']) || !wp_verify_nonce($_POST['gdm_nonce'], 'gdm_save_rule_data')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        // Información básica
        update_post_meta($post_id, '_gdm_prioridad', isset($_POST['gdm_prioridad']) ? absint($_POST['gdm_prioridad']) : 10);
        update_post_meta($post_id, '_gdm_reutilizable', isset($_POST['gdm_reutilizable']) ? '1' : '0');
        update_post_meta($post_id, '_gdm_aplicar_a', isset($_POST['gdm_aplicar_a']) ? array_map('sanitize_text_field', $_POST['gdm_aplicar_a']) : []);

        // ✅ Guardar ámbitos dinámicamente
        if (class_exists('GDM_Scope_Manager')) {
            $scope_manager = GDM_Scope_Manager::instance();
            $scope_manager->save_all($post_id);
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