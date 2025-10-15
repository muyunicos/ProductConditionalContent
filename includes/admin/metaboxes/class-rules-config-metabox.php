<?php
/**
 * Metabox de Configuración General de Reglas v6.1
 * Compatible con WordPress 6.8.3, PHP 8.2, WooCommerce 10.2.2
 * 
 * @package ProductConditionalContent
 * @since 6.1.0
 * @date 2025-10-15
 */

if (!defined('ABSPATH')) exit;

final class GDM_Reglas_Metabox {
    
    public static function init() {
        add_action('add_meta_boxes', [__CLASS__, 'add_metabox']);
        add_action('save_post_gdm_regla', [__CLASS__, 'save_metabox'], 10, 2);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        
        // AJAX para búsqueda de productos
        add_action('wp_ajax_gdm_search_products', [__CLASS__, 'ajax_search_products']);
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
                'selectModule' => __('Selecciona al menos un módulo en "Aplica a"', 'product-conditional-content'),
                'moduleWarning' => __('Al desactivar un módulo, se ocultará su configuración pero no se perderán los datos.', 'product-conditional-content'),
                'searching' => __('Buscando...', 'product-conditional-content'),
                'noResults' => __('No se encontraron resultados', 'product-conditional-content'),
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
        
        // ✅ CORRECCIÓN: Obtener módulos del manager
        $available_modules = [];
        if (class_exists('GDM_Module_Manager')) {
            $module_manager = GDM_Module_Manager::instance();
            $available_modules = $module_manager->get_modules_with_icons();
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
                            <?php _e('Número más bajo = mayor prioridad (se aplica primero)', 'product-conditional-content'); ?>
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
                            <?php _e('Las reglas reutilizables solo se activan mediante el shortcode ', 'product-conditional-content'); ?>
                            <code class="gdm-click-to-copy" onclick="copyToClipboard(this)">[rule-<?php echo esc_attr($post->ID); ?>]</code>
                            <?php _e(' en otras reglas.', 'product-conditional-content'); ?>
                        </p>
                    </div>
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
                    <?php _e('Selecciona los módulos que deseas activar para esta regla. Solo los módulos seleccionados aparecerán en la configuración.', 'product-conditional-content'); ?>
                </p>
                
                <?php if (empty($available_modules)): ?>
                    <div class="notice notice-warning inline">
                        <p>
                            <strong><?php _e('⚠️ No hay módulos disponibles', 'product-conditional-content'); ?></strong><br>
                            <?php _e('Verifica que el Module Manager esté correctamente inicializado.', 'product-conditional-content'); ?>
                        </p>
                        <p style="font-size: 11px; color: #666;">
                            <?php 
                            if (class_exists('GDM_Module_Manager')) {
                                $manager = GDM_Module_Manager::instance();
                                $count = $manager->get_modules_count();
                                printf(__('Módulos registrados: %d | Habilitados: %d', 'product-conditional-content'), $count['total'], $count['enabled']);
                            } else {
                                _e('Clase GDM_Module_Manager no encontrada', 'product-conditional-content');
                            }
                            ?>
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
            
            <hr class="gdm-separator">
            
            <!-- Ámbito de Aplicación MEJORADO -->
            <div class="gdm-section">
                <div class="gdm-section-header">
                    <h3>
                        <span class="dashicons dashicons-category"></span>
                        <?php _e('Ámbito de Aplicación', 'product-conditional-content'); ?>
                    </h3>
                </div>
                
                <p class="description">
                    <?php _e('Define a qué productos se aplicará esta regla. Si no seleccionas ningún ámbito, la regla se aplicará a todos los productos.', 'product-conditional-content'); ?>
                </p>
                
                <!-- CATEGORÍAS MEJORADAS -->
                <div class="gdm-scope-group">
                    <div class="gdm-scope-header">
                        <label class="gdm-scope-toggle">
                            <input type="checkbox" 
                                   id="gdm_categorias_enabled" 
                                   name="gdm_categorias_enabled" 
                                   class="gdm-scope-checkbox"
                                   value="1"
                                   <?php checked(!empty($data['categorias_objetivo'])); ?>>
                            <strong><?php _e('📂 Categorías Determinadas', 'product-conditional-content'); ?></strong>
                        </label>
                        
                        <div class="gdm-scope-summary" id="gdm-categorias-summary" style="<?php echo empty($data['categorias_objetivo']) ? 'display:none;' : ''; ?>">
                            <span class="gdm-summary-text" id="gdm-categorias-summary-text">
                                <?php
                                if (!empty($data['categorias_objetivo'])) {
                                    $cat_names = [];
                                    foreach ($data['categorias_objetivo'] as $cat_id) {
                                        $cat = get_term($cat_id, 'product_cat');
                                        if ($cat && !is_wp_error($cat)) {
                                            $cat_names[] = $cat->name;
                                        }
                                    }
                                    echo esc_html(implode(', ', $cat_names));
                                }
                                ?>
                            </span>
                            <button type="button" class="button button-small gdm-scope-edit" data-target="categorias">
                                <?php _e('Editar', 'product-conditional-content'); ?>
                            </button>
                        </div>
                    </div>
                    
                    <div class="gdm-scope-content" id="gdm-categorias-content" style="display:none;">
                        <input type="text" 
                               id="gdm_category_filter" 
                               class="gdm-filter-input" 
                               placeholder="<?php esc_attr_e('🔍 Buscar categorías...', 'product-conditional-content'); ?>">
                        
                        <div class="gdm-category-list" id="gdm-category-list">
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
                        
                        <div class="gdm-scope-actions">
                            <button type="button" class="button button-primary gdm-scope-accept" data-target="categorias">
                                <span class="dashicons dashicons-yes"></span>
                                <?php _e('Aceptar', 'product-conditional-content'); ?>
                            </button>
                            <span class="gdm-selection-counter" id="gdm-category-counter">
                                <?php echo count($data['categorias_objetivo']); ?> seleccionadas
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- TAGS MEJORADAS -->
                <div class="gdm-scope-group">
                    <div class="gdm-scope-header">
                        <label class="gdm-scope-toggle">
                            <input type="checkbox" 
                                   id="gdm_tags_enabled" 
                                   name="gdm_tags_enabled" 
                                   class="gdm-scope-checkbox"
                                   value="1"
                                   <?php checked(!empty($data['tags_objetivo'])); ?>>
                            <strong><?php _e('🏷️ Etiquetas Determinadas', 'product-conditional-content'); ?></strong>
                        </label>
                        
                        <div class="gdm-scope-summary" id="gdm-tags-summary" style="<?php echo empty($data['tags_objetivo']) ? 'display:none;' : ''; ?>">
                            <span class="gdm-summary-text" id="gdm-tags-summary-text">
                                <?php
                                if (!empty($data['tags_objetivo'])) {
                                    $tag_names = [];
                                    foreach ($data['tags_objetivo'] as $tag_id) {
                                        $tag = get_term($tag_id, 'product_tag');
                                        if ($tag && !is_wp_error($tag)) {
                                            $tag_names[] = $tag->name;
                                        }
                                    }
                                    echo esc_html(implode(', ', $tag_names));
                                }
                                ?>
                            </span>
                            <button type="button" class="button button-small gdm-scope-edit" data-target="tags">
                                <?php _e('Editar', 'product-conditional-content'); ?>
                            </button>
                        </div>
                    </div>
                    
                    <div class="gdm-scope-content" id="gdm-tags-content" style="display:none;">
                        <input type="text" 
                               id="gdm_tag_filter" 
                               class="gdm-filter-input" 
                               placeholder="<?php esc_attr_e('🔍 Buscar etiquetas...', 'product-conditional-content'); ?>">
                        
                        <div class="gdm-tag-list" id="gdm-tag-list">
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
                        
                        <div class="gdm-scope-actions">
                            <button type="button" class="button button-primary gdm-scope-accept" data-target="tags">
                                <span class="dashicons dashicons-yes"></span>
                                <?php _e('Aceptar', 'product-conditional-content'); ?>
                            </button>
                            <span class="gdm-selection-counter" id="gdm-tag-counter">
                                <?php echo count($data['tags_objetivo']); ?> seleccionadas
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- PRODUCTOS ESPECÍFICOS -->
                <div class="gdm-scope-group">
                    <div class="gdm-scope-header">
                        <label class="gdm-scope-toggle">
                            <input type="checkbox" 
                                   id="gdm_productos_enabled" 
                                   name="gdm_productos_enabled" 
                                   class="gdm-scope-checkbox"
                                   value="1"
                                   <?php checked(!empty($data['productos_objetivo'])); ?>>
                            <strong><?php _e('🛍️ Productos Específicos', 'product-conditional-content'); ?></strong>
                        </label>
                        
                        <div class="gdm-scope-summary" id="gdm-productos-summary" style="<?php echo empty($data['productos_objetivo']) ? 'display:none;' : ''; ?>">
                            <span class="gdm-summary-text" id="gdm-productos-summary-text">
                                <?php
                                if (!empty($data['productos_objetivo'])) {
                                    $prod_names = [];
                                    foreach ($data['productos_objetivo'] as $prod_id) {
                                        $product = wc_get_product($prod_id);
                                        if ($product) {
                                            $prod_names[] = $product->get_name();
                                        }
                                    }
                                    echo esc_html(implode(', ', $prod_names));
                                }
                                ?>
                            </span>
                            <button type="button" class="button button-small gdm-scope-edit" data-target="productos">
                                <?php _e('Editar', 'product-conditional-content'); ?>
                            </button>
                        </div>
                    </div>
                    
                    <div class="gdm-scope-content" id="gdm-productos-content" style="display:none;">
                        <input type="text" 
                               id="gdm_product_search" 
                               class="gdm-filter-input" 
                               placeholder="<?php esc_attr_e('🔍 Buscar productos (mín. 3 caracteres)...', 'product-conditional-content'); ?>">
                        
                        <div class="gdm-product-list" id="gdm-product-list">
                            <?php if (!empty($data['productos_objetivo'])): ?>
                                <?php foreach ($data['productos_objetivo'] as $product_id): 
                                    $product = wc_get_product($product_id);
                                    if ($product):
                                ?>
                                    <label class="gdm-checkbox-item">
                                        <input type="checkbox" 
                                               name="gdm_productos_objetivo[]" 
                                               value="<?php echo esc_attr($product_id); ?>"
                                               class="gdm-product-checkbox"
                                               checked>
                                        <span><?php echo esc_html($product->get_name()); ?></span>
                                    </label>
                                <?php 
                                    endif;
                                endforeach; ?>
                            <?php else: ?>
                                <div class="gdm-empty-state">
                                    <p><?php _e('Busca productos para agregar', 'product-conditional-content'); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="gdm-scope-actions">
                            <button type="button" class="button button-primary gdm-scope-accept" data-target="productos">
                                <span class="dashicons dashicons-yes"></span>
                                <?php _e('Aceptar', 'product-conditional-content'); ?>
                            </button>
                            <span class="gdm-selection-counter" id="gdm-product-counter">
                                <?php echo count($data['productos_objetivo']); ?> seleccionados
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- MÁS ÁMBITOS... (continúa con el mismo patrón) -->
                
            </div>
            
        </div>
        
        <?php self::render_scope_styles(); ?>
        <?php
    }
    
    /**
     * Renderizar estilos de ámbitos
     */
    private static function render_scope_styles() {
        ?>
        <style>
            /* Estilos para ámbitos mejorados */
            .gdm-scope-group {
                margin-bottom: 15px;
                border: 1px solid #ddd;
                border-radius: 4px;
                background: #fff;
            }
            
            .gdm-scope-header {
                padding: 12px 15px;
                background: #f9f9f9;
                border-bottom: 1px solid #ddd;
                display: flex;
                align-items: center;
                justify-content: space-between;
            }
            
            .gdm-scope-toggle {
                display: flex;
                align-items: center;
                margin: 0;
                cursor: pointer;
            }
            
            .gdm-scope-toggle input[type="checkbox"] {
                margin: 0 10px 0 0 !important;
            }
            
            .gdm-scope-summary {
                display: flex;
                align-items: center;
                gap: 10px;
                flex: 1;
                margin-left: 20px;
            }
            
            .gdm-summary-text {
                flex: 1;
                font-size: 12px;
                color: #666;
                font-style: italic;
            }
            
            .gdm-scope-edit {
                padding: 4px 12px !important;
                height: auto !important;
                line-height: 1.4 !important;
            }
            
            .gdm-scope-content {
                padding: 15px;
                display: none;
            }
            
            .gdm-scope-content.active {
                display: block;
                animation: slideDown 0.3s ease;
            }
            
            .gdm-scope-actions {
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-top: 12px;
                padding-top: 12px;
                border-top: 1px solid #e0e0e0;
            }
            
            .gdm-selection-counter {
                font-size: 12px;
                color: #666;
                font-weight: 600;
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
            
            /* Reutilizar estilos existentes para listas */
            .gdm-category-list,
            .gdm-tag-list,
            .gdm-product-list {
                max-height: 250px;
                overflow-y: auto;
                border: 1px solid #ddd;
                border-radius: 4px;
                padding: 8px;
                background: #fafafa;
                margin-bottom: 12px;
            }
        </style>
        <?php
    }

    /**
     * Guardar datos del metabox
     */
    public static function save_metabox($post_id, $post) {
        if (!isset($_POST['gdm_nonce']) || !wp_verify_nonce($_POST['gdm_nonce'], 'gdm_save_rule_data')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Prioridad
        $prioridad = isset($_POST['gdm_prioridad']) ? absint($_POST['gdm_prioridad']) : 10;
        update_post_meta($post_id, '_gdm_prioridad', $prioridad);

        // Reutilizable
        $reutilizable = isset($_POST['gdm_reutilizable']) ? '1' : '0';
        update_post_meta($post_id, '_gdm_reutilizable', $reutilizable);

        // Aplicar a (módulos)
        $aplicar_a = isset($_POST['gdm_aplicar_a']) ? array_map('sanitize_text_field', $_POST['gdm_aplicar_a']) : [];
        update_post_meta($post_id, '_gdm_aplicar_a', $aplicar_a);

        // Categorías (NUEVO FORMATO)
        $categorias_enabled = isset($_POST['gdm_categorias_enabled']) ? '1' : '0';
        update_post_meta($post_id, '_gdm_categorias_enabled', $categorias_enabled);
        
        $categorias_objetivo = isset($_POST['gdm_categorias_objetivo']) ? array_map('intval', $_POST['gdm_categorias_objetivo']) : [];
        update_post_meta($post_id, '_gdm_categorias_objetivo', $categorias_objetivo);
        
        // Mantener compatibilidad con código antiguo
        update_post_meta($post_id, '_gdm_todas_categorias', empty($categorias_objetivo) ? '1' : '0');

        // Tags (NUEVO FORMATO)
        $tags_enabled = isset($_POST['gdm_tags_enabled']) ? '1' : '0';
        update_post_meta($post_id, '_gdm_tags_enabled', $tags_enabled);
        
        $tags_objetivo = isset($_POST['gdm_tags_objetivo']) ? array_map('intval', $_POST['gdm_tags_objetivo']) : [];
        update_post_meta($post_id, '_gdm_tags_objetivo', $tags_objetivo);
        
        // Mantener compatibilidad
        update_post_meta($post_id, '_gdm_cualquier_tag', empty($tags_objetivo) ? '1' : '0');
        
        // Productos específicos
        $productos_enabled = isset($_POST['gdm_productos_enabled']) ? '1' : '0';
        update_post_meta($post_id, '_gdm_productos_enabled', $productos_enabled);
        
        $productos_objetivo = isset($_POST['gdm_productos_objetivo']) ? array_map('intval', $_POST['gdm_productos_objetivo']) : [];
        update_post_meta($post_id, '_gdm_productos_objetivo', $productos_objetivo);
        
        // ... continuar con los demás ámbitos
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
            'prioridad' => get_post_meta($post_id, '_gdm_prioridad', true) ?: 10,
            'reutilizable' => get_post_meta($post_id, '_gdm_reutilizable', true),
            'aplicar_a' => get_post_meta($post_id, '_gdm_aplicar_a', true) ?: [],
            'todas_categorias' => get_post_meta($post_id, '_gdm_todas_categorias', true) ?: '1',
            'categorias_objetivo' => get_post_meta($post_id, '_gdm_categorias_objetivo', true) ?: [],
            'cualquier_tag' => get_post_meta($post_id, '_gdm_cualquier_tag', true) ?: '1',
            'tags_objetivo' => get_post_meta($post_id, '_gdm_tags_objetivo', true) ?: [],
            'productos_objetivo' => get_post_meta($post_id, '_gdm_productos_objetivo', true) ?: [],
        ];
        
        $cache[$post_id] = $data;
        return $data;
    }
    
    /**
     * AJAX: Buscar productos
     */
    public static function ajax_search_products() {
        check_ajax_referer('gdm_admin_nonce', 'nonce');
        
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        
        if (strlen($search) < 3) {
            wp_send_json_error(['message' => __('Escribe al menos 3 caracteres', 'product-conditional-content')]);
        }
        
        $products = wc_get_products([
            's' => $search,
            'limit' => 50,
            'return' => 'ids',
        ]);
        
        $results = [];
        foreach ($products as $product_id) {
            $product = wc_get_product($product_id);
            if ($product) {
                $results[] = [
                    'id' => $product_id,
                    'title' => $product->get_name(),
                ];
            }
        }
        
        wp_send_json_success(['products' => $results]);
    }
}

GDM_Reglas_Metabox::init();