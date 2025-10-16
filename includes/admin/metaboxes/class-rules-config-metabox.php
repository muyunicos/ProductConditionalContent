<?php
/**
 * Metabox de Configuración de Reglas
 * Compatible con WordPress 6.8.3, PHP 8.2, WooCommerce 10.2.2
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
                    if (class_exists('GDM_Action_Manager')) {
                        $action_manager = GDM_Action_Manager::instance();
                        $actions = $action_manager->get_actions_ordered();

                        foreach ($actions as $action_id => $action_config) {

                            $is_checked = in_array($action_id, $aplicar_a);
                            ?>
                            <label class="gdm-module-checkbox <?php echo $is_checked ? 'active' : ''; ?>">
                                <input type="checkbox"
                                       class="gdm-module-toggle"
                                       name="gdm_aplicar_a[]"
                                       value="<?php echo esc_attr($action_id); ?>"
                                       data-module="<?php echo esc_attr($action_id); ?>"
                                       <?php checked($is_checked); ?>>
                                <span class="gdm-module-icon"><?php echo esc_html($action_config['icon']); ?></span>
                                <span class="gdm-module-label"><?php echo esc_html($action_config['label']); ?></span>
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

            <!-- Configuración de Acciones/Módulos -->
            <div class="gdm-config-section">
                <h3><?php _e('⚙️ Configuración de Módulos', 'product-conditional-content'); ?></h3>
                <p class="description">
                    <?php _e('Configura las opciones para cada módulo seleccionado:', 'product-conditional-content'); ?>
                </p>

                <div class="gdm-actions-wrapper">
                    <?php
                    if (class_exists('GDM_Action_Manager')) {
                        $action_manager = GDM_Action_Manager::instance();
                        $action_manager->render_all($post->ID);
                    }
                    ?>
                </div>
            </div>
            
            <hr class="gdm-separator">
            
            <div class="gdm-config-section">
                <h3><?php _e('🎯 Ámbito de Aplicación', 'product-conditional-content'); ?></h3>
                <p class="description">
                    <?php _e('Define a qué productos se aplicará esta regla:', 'product-conditional-content'); ?>
                </p>
                
                <div class="gdm-conditions-wrapper">
                    <?php
                    if (class_exists('GDM_Condition_Manager')) {
                        $condition_manager = GDM_Condition_Manager::instance();
                        $condition_manager->render_all($post->ID);
                    }
                    ?>
                </div>
            </div>
            
        </div>
        <?php
    }

public static function save_metabox($post_id, $post) {
    if (!GDM_Admin_Helpers::validate_metabox_save(
        $post_id, 
        $post, 
        'gdm_nonce', 
        'gdm_save_rule_data', 
        'gdm_regla'
    )) {
        return;
    }

    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('=== GDM REGLAS SAVE START ===');
        error_log('Post ID: ' . $post_id);
    }

    try {
        $reutilizable = isset($_POST['gdm_reutilizable']) && $_POST['gdm_reutilizable'] === '1' ? '1' : '0';
        update_post_meta($post_id, '_gdm_reutilizable', $reutilizable);
        
        $aplicar_a = [];
        
        if (isset($_POST['gdm_aplicar_a']) && is_array($_POST['gdm_aplicar_a'])) {
            $aplicar_a = array_map('sanitize_text_field', $_POST['gdm_aplicar_a']);
        }
        
        update_post_meta($post_id, '_gdm_aplicar_a', $aplicar_a);
        
        do_action('gdm_save_modules_data', $post_id, $post);
        
        do_action('gdm_save_conditions_data', $post_id, $post);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('✅ GDM: Regla guardada exitosamente ID ' . $post_id);
        }
        
        } catch (Exception $e) {
            error_log('❌ GDM: Error al guardar regla ID ' . $post_id . ': ' . $e->getMessage());
        }
    }
}

GDM_Rules_Config_Metabox::init();