<?php
/**
 * Metabox de Configuración General de Reglas (Sistema Modular v6.0)
 * Compatible con WordPress 6.8.3, PHP 8.2, WooCommerce 10.2.2
 * 
 * @package ProductConditionalContent
 * @since 6.0.0
 * @author MuyUnicos
 * @date 2025-10-15
 */

if (!defined('ABSPATH')) exit;

final class GDM_Reglas_Metabox {
    
    public static function init() {
        add_action('add_meta_boxes', [__CLASS__, 'add_metabox']);
        add_action('save_post_gdm_regla', [__CLASS__, 'save_metabox'], 10, 2);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    /**
     * Encolar scripts y estilos
     */
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
                'selectModule' => __('Selecciona al menos un módulo en "Aplicar a"', 'product-conditional-content'),
                'moduleWarning' => __('Al desactivar un módulo, se ocultará su configuración pero no se perderán los datos.', 'product-conditional-content'),
            ]
        ]);
    }

    /**
     * Registrar metabox
     */
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

    /**
     * Renderizar metabox
     */
    public static function render_metabox($post) {
        wp_nonce_field('gdm_save_rule_data', 'gdm_nonce');
        
        $data = self::get_rule_data($post->ID);
        $module_manager = GDM_Module_Manager::instance();
        $available_modules = $module_manager->get_modules_with_icons();
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
                        <label>
                            <strong><?php _e('ID de Regla:', 'product-conditional-content'); ?></strong>
                            <input type="text" 
                                   value="<?php echo esc_attr($post->ID); ?>" 
                                   disabled 
                                   class="regular-text">
                        </label>
                        <p class="description">
                            <?php _e('Identificador único de la regla (generado automáticamente)', 'product-conditional-content'); ?>
                        </p>
                    </div>
                    <div class="gdm-col-6">
                        <label>
                            <strong><?php _e('Prioridad:', 'product-conditional-content'); ?></strong>
                            <input type="number" 
                                   name="gdm_prioridad" 
                                   value="<?php echo esc_attr($data['prioridad']); ?>" 
                                   min="0" 
                                   max="999" 
                                   class="regular-text">
                        </label>
                        <p class="description">
                            <?php _e('Número menor = Mayor prioridad. Las reglas con menor número se procesan primero.', 'product-conditional-content'); ?>
                        </p>
                    </div>
                </div>
                
                <div class="gdm-field-group">
                    <label>
                        <input type="checkbox" 
                               name="gdm_reutilizable" 
                               value="1"
                               <?php checked($data['reutilizable'], '1'); ?>>
                        <strong><?php _e('🔄 Regla Reutilizable', 'product-conditional-content'); ?></strong>
                    </label>
                    <p class="description">
                        <?php _e('Las reglas reutilizables solo se activan mediante el shortcode [rule-ID] en otras reglas.', 'product-conditional-content'); ?>
                    </p>
                </div>
            </div>
            
            <hr class="gdm-separator">
            
            <!-- Aplicar a (Módulos) -->
            <div class="gdm-section">
                <div class="gdm-section-header">
                    <h3>
                        <span class="dashicons dashicons-admin-tools"></span>
                        <?php _e('Aplicar a (Módulos)', 'product-conditional-content'); ?>
                    </h3>
                </div>
                
                <p class="description">
                    <?php _e('Selecciona las características del producto que esta regla modificará. Solo los módulos seleccionados mostrarán su configuración.', 'product-conditional-content'); ?>
                </p>
                
                <div class="gdm-modules-grid">
                    <?php foreach ($available_modules as $module_id => $module_info): ?>
                        <label class="gdm-module-checkbox <?php echo in_array($module_id, $data['aplicar_a']) ? 'active' : ''; ?>">
                            <input type="checkbox" 
                                   name="gdm_aplicar_a[]" 
                                   value="<?php echo esc_attr($module_id); ?>"
                                   class="gdm-module-toggle"
                                   data-module="<?php echo esc_attr($module_id); ?>"
                                   <?php checked(in_array($module_id, $data['aplicar_a'])); ?>>
                            <span class="gdm-module-icon"><?php echo esc_html($module_info['icon']); ?></span>
                            <span class="gdm-module-label"><?php echo esc_html($module_info['label']); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                
                <div class="gdm-modules-hint">
                    <span class="dashicons dashicons-info"></span>
                    <?php _e('Activa/desactiva módulos haciendo clic en las tarjetas. Los metaboxes correspondientes aparecerán/desaparecerán automáticamente.', 'product-conditional-content'); ?>
                </div>
            </div>
            
            <hr class="gdm-separator">
            
            <!-- Ámbito de Aplicación -->
            <div class="gdm-section">
                <div class="gdm-section-header">
                    <h3>
                        <span class="dashicons dashicons-category"></span>
                        <?php _e('Ámbito de Aplicación', 'product-conditional-content'); ?>
                    </h3>
                </div>
                
                <p class="description">
                    <?php _e('Define a qué productos se aplicará esta regla. Si no seleccionas nada, se aplicará a todos los productos.', 'product-conditional-content'); ?>
                </p>
                
                <!-- Categorías -->
                <div class="gdm-scope-group">
                    <label class="gdm-scope-toggle">
                        <input type="checkbox" 
                               id="gdm_todas_categorias" 
                               name="gdm_todas_categorias" 
                               value="1"
                               <?php checked($data['todas_categorias'], '1'); ?>>
                        <strong><?php _e('📂 Todas las Categorías', 'product-conditional-content'); ?></strong>
                    </label>
                    
                    <div class="gdm-scope-content" id="gdm-categorias-wrapper" <?php echo $data['todas_categorias'] === '1' ? 'style="display:none;"' : ''; ?>>
                        <input type="text" 
                               id="gdm_category_filter" 
                               class="gdm-filter-input" 
                               placeholder="<?php esc_attr_e('Buscar categorías...', 'product-conditional-content'); ?>">
                        
                        <div class="gdm-category-list">
                            <?php
                            $categories = get_terms([
                                'taxonomy' => 'product_cat',
                                'hide_empty' => false,
                                'orderby' => 'name',
                                'order' => 'ASC'
                            ]);
                            
                            if ($categories && !is_wp_error($categories)) {
                                foreach ($categories as $cat) {
                                    $checked = in_array($cat->term_id, $data['categorias_objetivo']);
                                    printf(
                                        '<label class="gdm-checkbox-item">
                                            <input type="checkbox" 
                                                   name="gdm_categorias_objetivo[]" 
                                                   value="%d"
                                                   class="gdm-category-checkbox"
                                                   %s>
                                            <span>%s</span>
                                        </label>',
                                        $cat->term_id,
                                        checked($checked, true, false),
                                        esc_html($cat->name)
                                    );
                                }
                            }
                            ?>
                        </div>
                    </div>
                </div>
                
                <!-- Tags -->
                <div class="gdm-scope-group">
                    <label class="gdm-scope-toggle">
                        <input type="checkbox" 
                               id="gdm_cualquier_tag" 
                               name="gdm_cualquier_tag" 
                               value="1"
                               <?php checked($data['cualquier_tag'], '1'); ?>>
                        <strong><?php _e('🏷️ Cualquier Etiqueta (Tag)', 'product-conditional-content'); ?></strong>
                    </label>
                    
                    <div class="gdm-scope-content" id="gdm-tags-wrapper" <?php echo $data['cualquier_tag'] === '1' ? 'style="display:none;"' : ''; ?>>
                        <input type="text" 
                               id="gdm_tag_filter" 
                               class="gdm-filter-input" 
                               placeholder="<?php esc_attr_e('Buscar etiquetas...', 'product-conditional-content'); ?>">
                        
                        <div class="gdm-tag-list">
                            <?php
                            $tags = get_terms([
                                'taxonomy' => 'product_tag',
                                'hide_empty' => false,
                                'orderby' => 'name',
                                'order' => 'ASC'
                            ]);
                            
                            if ($tags && !is_wp_error($tags)) {
                                foreach ($tags as $tag) {
                                    $checked = in_array($tag->term_id, $data['tags_objetivo']);
                                    printf(
                                        '<label class="gdm-checkbox-item">
                                            <input type="checkbox" 
                                                   name="gdm_tags_objetivo[]" 
                                                   value="%d"
                                                   class="gdm-tag-checkbox"
                                                   %s>
                                            <span>%s</span>
                                        </label>',
                                        $tag->term_id,
                                        checked($checked, true, false),
                                        esc_html($tag->name)
                                    );
                                }
                            }
                            ?>
                        </div>
                    </div>
                </div>
                
                <!-- Ámbito Futuro (placeholder para extensiones) -->
                <?php
                /**
                 * Hook para agregar ámbitos personalizados
                 * 
                 * @param int $post_id ID de la regla
                 * @param array $data Datos de la regla
                 */
                do_action('gdm_ambito_adicional', $post->ID, $data);
                ?>
            </div>
            
        </div>
        <?php
    }

    /**
     * Guardar datos
     */
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
        
        // Guardar configuración general
        update_post_meta($post_id, '_gdm_prioridad', 
            max(0, intval($_POST['gdm_prioridad'] ?? 10)));
        
        update_post_meta($post_id, '_gdm_reutilizable', 
            isset($_POST['gdm_reutilizable']) ? '1' : '0');
        
        // Guardar "Aplicar a" (módulos activos)
        $aplicar_a = isset($_POST['gdm_aplicar_a']) && is_array($_POST['gdm_aplicar_a'])
            ? array_map('sanitize_text_field', $_POST['gdm_aplicar_a'])
            : [];
        update_post_meta($post_id, '_gdm_aplicar_a', $aplicar_a);
        
        // Guardar ámbito - Categorías
        update_post_meta($post_id, '_gdm_todas_categorias', 
            isset($_POST['gdm_todas_categorias']) ? '1' : '0');
        
        $categorias = isset($_POST['gdm_categorias_objetivo']) && is_array($_POST['gdm_categorias_objetivo'])
            ? array_map('intval', $_POST['gdm_categorias_objetivo'])
            : [];
        update_post_meta($post_id, '_gdm_categorias_objetivo', $categorias);
        
        // Guardar ámbito - Tags
        update_post_meta($post_id, '_gdm_cualquier_tag', 
            isset($_POST['gdm_cualquier_tag']) ? '1' : '0');
        
        $tags = isset($_POST['gdm_tags_objetivo']) && is_array($_POST['gdm_tags_objetivo'])
            ? array_map('intval', $_POST['gdm_tags_objetivo'])
            : [];
        update_post_meta($post_id, '_gdm_tags_objetivo', $tags);
        
        /**
         * Hook para guardar datos adicionales del metabox
         * 
         * @param int $post_id ID del post
         * @param WP_Post $post Objeto del post
         */
        do_action('gdm_save_metabox_adicional', $post_id, $post);
    }

    /**
     * Obtener datos de la regla
     */
    private static function get_rule_data($post_id) {
        static $cache = [];
        
        if (isset($cache[$post_id])) {
            return $cache[$post_id];
        }
        
        $data = [
            'prioridad' => (int) (get_post_meta($post_id, '_gdm_prioridad', true) ?: 10),
            'reutilizable' => get_post_meta($post_id, '_gdm_reutilizable', true),
            'aplicar_a' => get_post_meta($post_id, '_gdm_aplicar_a', true) ?: [],
            'todas_categorias' => get_post_meta($post_id, '_gdm_todas_categorias', true),
            'categorias_objetivo' => get_post_meta($post_id, '_gdm_categorias_objetivo', true) ?: [],
            'cualquier_tag' => get_post_meta($post_id, '_gdm_cualquier_tag', true),
            'tags_objetivo' => get_post_meta($post_id, '_gdm_tags_objetivo', true) ?: [],
        ];
        
        $cache[$post_id] = $data;
        return $data;
    }
}

GDM_Reglas_Metabox::init();