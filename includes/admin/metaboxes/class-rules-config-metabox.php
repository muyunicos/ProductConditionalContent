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
        
        // ✅ Obtener módulos con validación
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
                    <?php _e('Define a qué productos se aplicará esta regla. Si no seleccionas nada, se aplicará a todos.', 'product-conditional-content'); ?>
                </p>
                
                <?php self::render_scope_categorias($data); ?>
                <?php self::render_scope_tags($data); ?>
                <?php self::render_scope_productos($data); ?>
                <?php self::render_scope_atributos($data); ?>
                <?php self::render_scope_stock($data); ?>
                <?php self::render_scope_precio($data); ?>
                <?php self::render_scope_titulo($data); ?>
                
            </div>
            
        </div>
        
        <?php self::render_inline_styles(); ?>
        <?php
    }
    
    /**
     * ÁMBITO: CATEGORÍAS
     */
    private static function render_scope_categorias($data) {
        $has_selection = !empty($data['categorias_objetivo']);
        ?>
        <div class="gdm-scope-group">
            <div class="gdm-scope-header">
                <label class="gdm-scope-toggle">
                    <input type="checkbox" 
                           id="gdm_categorias_enabled" 
                           name="gdm_categorias_enabled" 
                           class="gdm-scope-checkbox"
                           value="1"
                           <?php checked($has_selection); ?>>
                    <strong><?php _e('📂 Categorías Determinadas', 'product-conditional-content'); ?></strong>
                </label>
                
                <div class="gdm-scope-summary" id="gdm-categorias-summary" style="<?php echo !$has_selection ? 'display:none;' : ''; ?>">
                    <span class="gdm-summary-text" id="gdm-categorias-summary-text">
                        <?php
                        if ($has_selection) {
                            $names = [];
                            foreach ($data['categorias_objetivo'] as $cat_id) {
                                $cat = get_term($cat_id, 'product_cat');
                                if ($cat && !is_wp_error($cat)) {
                                    $names[] = $cat->name;
                                }
                            }
                            echo count($names) <= 3 ? esc_html(implode(', ', $names)) : 
                                 esc_html(implode(', ', array_slice($names, 0, 3))) . ' <em>y ' . (count($names) - 3) . ' más</em>';
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
                    $categories = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false, 'orderby' => 'name']);
                    if ($categories && !is_wp_error($categories)) {
                        foreach ($categories as $cat) {
                            printf(
                                '<label class="gdm-checkbox-item">
                                    <input type="checkbox" name="gdm_categorias_objetivo[]" value="%d" class="gdm-category-checkbox" %s>
                                    <span>%s</span>
                                </label>',
                                $cat->term_id,
                                checked(in_array($cat->term_id, $data['categorias_objetivo']), true, false),
                                esc_html($cat->name)
                            );
                        }
                    }
                    ?>
                </div>
                
                <div class="gdm-scope-actions">
                    <button type="button" class="button button-primary gdm-scope-accept" data-target="categorias">
                        <span class="dashicons dashicons-yes"></span> <?php _e('Aceptar', 'product-conditional-content'); ?>
                    </button>
                    <span class="gdm-selection-counter" id="gdm-category-counter">
                        <?php echo count($data['categorias_objetivo']); ?> seleccionadas
                    </span>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * ÁMBITO: TAGS
     */
    private static function render_scope_tags($data) {
        $has_selection = !empty($data['tags_objetivo']);
        ?>
        <div class="gdm-scope-group">
            <div class="gdm-scope-header">
                <label class="gdm-scope-toggle">
                    <input type="checkbox" 
                           id="gdm_tags_enabled" 
                           name="gdm_tags_enabled" 
                           class="gdm-scope-checkbox"
                           value="1"
                           <?php checked($has_selection); ?>>
                    <strong><?php _e('🏷️ Etiquetas Determinadas', 'product-conditional-content'); ?></strong>
                </label>
                
                <div class="gdm-scope-summary" id="gdm-tags-summary" style="<?php echo !$has_selection ? 'display:none;' : ''; ?>">
                    <span class="gdm-summary-text" id="gdm-tags-summary-text">
                        <?php
                        if ($has_selection) {
                            $names = [];
                            foreach ($data['tags_objetivo'] as $tag_id) {
                                $tag = get_term($tag_id, 'product_tag');
                                if ($tag && !is_wp_error($tag)) {
                                    $names[] = $tag->name;
                                }
                            }
                            echo count($names) <= 3 ? esc_html(implode(', ', $names)) : 
                                 esc_html(implode(', ', array_slice($names, 0, 3))) . ' <em>y ' . (count($names) - 3) . ' más</em>';
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
                    $tags = get_terms(['taxonomy' => 'product_tag', 'hide_empty' => false, 'orderby' => 'name']);
                    if ($tags && !is_wp_error($tags)) {
                        foreach ($tags as $tag) {
                            printf(
                                '<label class="gdm-checkbox-item">
                                    <input type="checkbox" name="gdm_tags_objetivo[]" value="%d" class="gdm-tag-checkbox" %s>
                                    <span>%s</span>
                                </label>',
                                $tag->term_id,
                                checked(in_array($tag->term_id, $data['tags_objetivo']), true, false),
                                esc_html($tag->name)
                            );
                        }
                    }
                    ?>
                </div>
                
                <div class="gdm-scope-actions">
                    <button type="button" class="button button-primary gdm-scope-accept" data-target="tags">
                        <span class="dashicons dashicons-yes"></span> <?php _e('Aceptar', 'product-conditional-content'); ?>
                    </button>
                    <span class="gdm-selection-counter" id="gdm-tag-counter">
                        <?php echo count($data['tags_objetivo']); ?> seleccionadas
                    </span>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * ÁMBITO: PRODUCTOS ESPECÍFICOS
     */
    private static function render_scope_productos($data) {
        $has_selection = !empty($data['productos_objetivo']);
        ?>
        <div class="gdm-scope-group">
            <div class="gdm-scope-header">
                <label class="gdm-scope-toggle">
                    <input type="checkbox" 
                           id="gdm_productos_enabled" 
                           name="gdm_productos_enabled" 
                           class="gdm-scope-checkbox"
                           value="1"
                           <?php checked($has_selection); ?>>
                    <strong><?php _e('🛍️ Productos Específicos', 'product-conditional-content'); ?></strong>
                </label>
                
                <div class="gdm-scope-summary" id="gdm-productos-summary" style="<?php echo !$has_selection ? 'display:none;' : ''; ?>">
                    <span class="gdm-summary-text" id="gdm-productos-summary-text">
                        <?php
                        if ($has_selection) {
                            $names = [];
                            foreach ($data['productos_objetivo'] as $prod_id) {
                                $product = wc_get_product($prod_id);
                                if ($product) {
                                    $names[] = $product->get_name();
                                }
                            }
                            echo count($names) <= 3 ? esc_html(implode(', ', $names)) : 
                                 esc_html(implode(', ', array_slice($names, 0, 3))) . ' <em>y ' . (count($names) - 3) . ' más</em>';
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
                    <?php if ($has_selection): ?>
                        <?php foreach ($data['productos_objetivo'] as $product_id): 
                            $product = wc_get_product($product_id);
                            if ($product):
                        ?>
                            <label class="gdm-checkbox-item">
                                <input type="checkbox" name="gdm_productos_objetivo[]" value="<?php echo esc_attr($product_id); ?>" class="gdm-product-checkbox" checked>
                                <span><?php echo esc_html($product->get_name()); ?></span>
                            </label>
                        <?php endif; endforeach; ?>
                    <?php else: ?>
                        <div class="gdm-empty-state">
                            <p><?php _e('Busca productos para agregar', 'product-conditional-content'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="gdm-scope-actions">
                    <button type="button" class="button button-primary gdm-scope-accept" data-target="productos">
                        <span class="dashicons dashicons-yes"></span> <?php _e('Aceptar', 'product-conditional-content'); ?>
                    </button>
                    <span class="gdm-selection-counter" id="gdm-product-counter">
                        <?php echo count($data['productos_objetivo']); ?> seleccionados
                    </span>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * ÁMBITO: ATRIBUTOS
     */
    private static function render_scope_atributos($data) {
        $has_selection = !empty($data['atributos']);
        ?>
        <div class="gdm-scope-group">
            <div class="gdm-scope-header">
                <label class="gdm-scope-toggle">
                    <input type="checkbox" 
                           id="gdm_atributos_enabled" 
                           name="gdm_atributos_enabled" 
                           class="gdm-scope-checkbox"
                           value="1"
                           <?php checked($has_selection); ?>>
                    <strong><?php _e('🎨 Atributos de Productos', 'product-conditional-content'); ?></strong>
                </label>
            </div>
            
            <div class="gdm-scope-content" id="gdm-atributos-content" style="display:none;">
                <?php
                $product_attributes = wc_get_attribute_taxonomies();
                if ($product_attributes) {
                    foreach ($product_attributes as $attribute) {
                        $taxonomy = wc_attribute_taxonomy_name($attribute->attribute_name);
                        $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
                        
                        if ($terms && !is_wp_error($terms)) {
                            echo '<div class="gdm-attribute-group">';
                            echo '<strong>' . esc_html($attribute->attribute_label) . ':</strong>';
                            echo '<div class="gdm-attribute-list">';
                            foreach ($terms as $term) {
                                $checked = isset($data['atributos'][$taxonomy]) && in_array($term->term_id, $data['atributos'][$taxonomy]);
                                printf(
                                    '<label class="gdm-checkbox-item">
                                        <input type="checkbox" name="gdm_atributos[%s][]" value="%d" %s>
                                        <span>%s</span>
                                    </label>',
                                    esc_attr($taxonomy),
                                    $term->term_id,
                                    checked($checked, true, false),
                                    esc_html($term->name)
                                );
                            }
                            echo '</div></div>';
                        }
                    }
                } else {
                    echo '<p class="description">' . __('No hay atributos configurados', 'product-conditional-content') . '</p>';
                }
                ?>
                
                <div class="gdm-scope-actions">
                    <button type="button" class="button button-primary gdm-scope-accept" data-target="atributos">
                        <span class="dashicons dashicons-yes"></span> <?php _e('Aceptar', 'product-conditional-content'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * ÁMBITO: STOCK
     */
    private static function render_scope_stock($data) {
        $has_selection = !empty($data['stock_status']);
        ?>
        <div class="gdm-scope-group">
            <div class="gdm-scope-header">
                <label class="gdm-scope-toggle">
                    <input type="checkbox" 
                           id="gdm_stock_enabled" 
                           name="gdm_stock_enabled" 
                           class="gdm-scope-checkbox"
                           value="1"
                           <?php checked($has_selection); ?>>
                    <strong><?php _e('📦 Estado de Stock', 'product-conditional-content'); ?></strong>
                </label>
            </div>
            
            <div class="gdm-scope-content" id="gdm-stock-content" style="display:none;">
                <label class="gdm-checkbox-item">
                    <input type="checkbox" name="gdm_stock_status[]" value="instock" <?php checked(in_array('instock', $data['stock_status'])); ?>>
                    <span><?php _e('✅ En Stock', 'product-conditional-content'); ?></span>
                </label>
                <label class="gdm-checkbox-item">
                    <input type="checkbox" name="gdm_stock_status[]" value="outofstock" <?php checked(in_array('outofstock', $data['stock_status'])); ?>>
                    <span><?php _e('❌ Sin Stock', 'product-conditional-content'); ?></span>
                </label>
                <label class="gdm-checkbox-item">
                    <input type="checkbox" name="gdm_stock_status[]" value="onbackorder" <?php checked(in_array('onbackorder', $data['stock_status'])); ?>>
                    <span><?php _e('⏳ Pedido Pendiente', 'product-conditional-content'); ?></span>
                </label>
                
                <div class="gdm-scope-actions">
                    <button type="button" class="button button-primary gdm-scope-accept" data-target="stock">
                        <span class="dashicons dashicons-yes"></span> <?php _e('Aceptar', 'product-conditional-content'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * ÁMBITO: PRECIO
     */
    private static function render_scope_precio($data) {
        $has_selection = !empty($data['precio_valor']);
        ?>
        <div class="gdm-scope-group">
            <div class="gdm-scope-header">
                <label class="gdm-scope-toggle">
                    <input type="checkbox" 
                           id="gdm_precio_enabled" 
                           name="gdm_precio_enabled" 
                           class="gdm-scope-checkbox"
                           value="1"
                           <?php checked($has_selection); ?>>
                    <strong><?php _e('💵 Filtro por Precio', 'product-conditional-content'); ?></strong>
                </label>
            </div>
            
            <div class="gdm-scope-content" id="gdm-precio-content" style="display:none;">
                <label><strong><?php _e('Condición:', 'product-conditional-content'); ?></strong></label>
                <select name="gdm_precio_condicion" id="gdm_precio_condicion" class="regular-text">
                    <option value="mayor_que" <?php selected($data['precio_condicion'], 'mayor_que'); ?>><?php _e('Mayor que', 'product-conditional-content'); ?></option>
                    <option value="menor_que" <?php selected($data['precio_condicion'], 'menor_que'); ?>><?php _e('Menor que', 'product-conditional-content'); ?></option>
                    <option value="igual_a" <?php selected($data['precio_condicion'], 'igual_a'); ?>><?php _e('Igual a', 'product-conditional-content'); ?></option>
                    <option value="entre" <?php selected($data['precio_condicion'], 'entre'); ?>><?php _e('Entre', 'product-conditional-content'); ?></option>
                </select>
                
                <div style="margin-top:10px;">
                    <label><strong><?php _e('Valor:', 'product-conditional-content'); ?></strong></label>
                    <input type="number" name="gdm_precio_valor" value="<?php echo esc_attr($data['precio_valor']); ?>" step="0.01" min="0" class="regular-text">
                    <?php echo get_woocommerce_currency_symbol(); ?>
                </div>
                
                <div id="gdm-precio-valor2-wrapper" style="margin-top:10px; <?php echo $data['precio_condicion'] !== 'entre' ? 'display:none;' : ''; ?>">
                    <label><strong><?php _e('Valor máximo:', 'product-conditional-content'); ?></strong></label>
                    <input type="number" name="gdm_precio_valor2" value="<?php echo esc_attr($data['precio_valor2']); ?>" step="0.01" min="0" class="regular-text">
                    <?php echo get_woocommerce_currency_symbol(); ?>
                </div>
                
                <div class="gdm-scope-actions">
                    <button type="button" class="button button-primary gdm-scope-accept" data-target="precio">
                        <span class="dashicons dashicons-yes"></span> <?php _e('Aceptar', 'product-conditional-content'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * ÁMBITO: TÍTULO
     */
    private static function render_scope_titulo($data) {
        $has_selection = !empty($data['titulo_texto']);
        ?>
        <div class="gdm-scope-group">
            <div class="gdm-scope-header">
                <label class="gdm-scope-toggle">
                    <input type="checkbox" 
                           id="gdm_titulo_enabled" 
                           name="gdm_titulo_enabled" 
                           class="gdm-scope-checkbox"
                           value="1"
                           <?php checked($has_selection); ?>>
                    <strong><?php _e('📝 Filtro por Título', 'product-conditional-content'); ?></strong>
                </label>
            </div>
            
            <div class="gdm-scope-content" id="gdm-titulo-content" style="display:none;">
                <label><strong><?php _e('Condición:', 'product-conditional-content'); ?></strong></label>
                <select name="gdm_titulo_condicion" class="regular-text">
                    <option value="contiene" <?php selected($data['titulo_condicion'], 'contiene'); ?>><?php _e('Contiene', 'product-conditional-content'); ?></option>
                    <option value="no_contiene" <?php selected($data['titulo_condicion'], 'no_contiene'); ?>><?php _e('No contiene', 'product-conditional-content'); ?></option>
                    <option value="empieza_con" <?php selected($data['titulo_condicion'], 'empieza_con'); ?>><?php _e('Empieza con', 'product-conditional-content'); ?></option>
                    <option value="termina_con" <?php selected($data['titulo_condicion'], 'termina_con'); ?>><?php _e('Termina con', 'product-conditional-content'); ?></option>
                    <option value="regex" <?php selected($data['titulo_condicion'], 'regex'); ?>><?php _e('Regex', 'product-conditional-content'); ?></option>
                </select>
                
                <div style="margin-top:10px;">
                    <label><strong><?php _e('Texto:', 'product-conditional-content'); ?></strong></label>
                    <input type="text" name="gdm_titulo_texto" value="<?php echo esc_attr($data['titulo_texto']); ?>" class="regular-text" placeholder="<?php esc_attr_e('Texto a buscar', 'product-conditional-content'); ?>">
                </div>
                
                <label style="margin-top:10px;">
                    <input type="checkbox" name="gdm_titulo_case_sensitive" value="1" <?php checked($data['titulo_case_sensitive'], '1'); ?>>
                    <?php _e('Distinguir mayúsculas/minúsculas', 'product-conditional-content'); ?>
                </label>
                
                <div class="gdm-scope-actions">
                    <button type="button" class="button button-primary gdm-scope-accept" data-target="titulo">
                        <span class="dashicons dashicons-yes"></span> <?php _e('Aceptar', 'product-conditional-content'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Estilos inline
     */
    private static function render_inline_styles() {
        ?>
        <style>
            .gdm-scope-group {
                margin-bottom: 15px;
                border: 1px solid #ddd;
                border-radius: 4px;
                background: #fff;
                overflow: hidden;
            }
            .gdm-scope-header {
                padding: 12px 15px;
                background: #f9f9f9;
                border-bottom: 1px solid #ddd;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 15px;
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
                padding-left: 15px;
                border-left: 2px solid #2271b1;
            }
            .gdm-summary-text {
                flex: 1;
                font-size: 12px;
                color: #135e96;
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
            }
            .gdm-scope-edit:hover {
                background: #2271b1 !important;
                color: #fff !important;
            }
            .gdm-scope-content {
                padding: 15px;
                display: none;
                background: #fafafa;
            }
            .gdm-scope-content.active {
                display: block;
            }
            .gdm-scope-actions {
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-top: 12px;
                padding-top: 12px;
                border-top: 1px solid #e0e0e0;
            }
            .gdm-filter-input {
                width: 100%;
                padding: 10px;
                border: 1px solid #ddd;
                border-radius: 4px;
                margin-bottom: 10px;
            }
            .gdm-category-list, .gdm-tag-list, .gdm-product-list, .gdm-attribute-list {
                max-height: 250px;
                overflow-y: auto;
                border: 1px solid #ddd;
                border-radius: 4px;
                padding: 8px;
                background: #fff;
                margin-bottom: 12px;
            }
            .gdm-checkbox-item {
                display: block;
                padding: 8px;
                cursor: pointer;
                border-radius: 3px;
            }
            .gdm-checkbox-item:hover {
                background: #e8f0fe;
            }
            .gdm-selection-counter {
                font-size: 12px;
                color: #666;
                font-weight: 600;
            }
            .gdm-attribute-group {
                margin-bottom: 15px;
                padding-bottom: 15px;
                border-bottom: 1px solid #ddd;
            }
            .gdm-attribute-group:last-child {
                border-bottom: none;
            }
            .gdm-empty-state {
                padding: 30px;
                text-align: center;
                color: #999;
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

        // Básico
        update_post_meta($post_id, '_gdm_prioridad', isset($_POST['gdm_prioridad']) ? absint($_POST['gdm_prioridad']) : 10);
        update_post_meta($post_id, '_gdm_reutilizable', isset($_POST['gdm_reutilizable']) ? '1' : '0');
        update_post_meta($post_id, '_gdm_aplicar_a', isset($_POST['gdm_aplicar_a']) ? array_map('sanitize_text_field', $_POST['gdm_aplicar_a']) : []);

        // Ámbitos
        update_post_meta($post_id, '_gdm_categorias_objetivo', isset($_POST['gdm_categorias_objetivo']) ? array_map('intval', $_POST['gdm_categorias_objetivo']) : []);
        update_post_meta($post_id, '_gdm_tags_objetivo', isset($_POST['gdm_tags_objetivo']) ? array_map('intval', $_POST['gdm_tags_objetivo']) : []);
        update_post_meta($post_id, '_gdm_productos_objetivo', isset($_POST['gdm_productos_objetivo']) ? array_map('intval', $_POST['gdm_productos_objetivo']) : []);
        update_post_meta($post_id, '_gdm_atributos', isset($_POST['gdm_atributos']) ? $_POST['gdm_atributos'] : []);
        update_post_meta($post_id, '_gdm_stock_status', isset($_POST['gdm_stock_status']) ? array_map('sanitize_text_field', $_POST['gdm_stock_status']) : []);
        update_post_meta($post_id, '_gdm_precio_condicion', isset($_POST['gdm_precio_condicion']) ? sanitize_text_field($_POST['gdm_precio_condicion']) : 'mayor_que');
        update_post_meta($post_id, '_gdm_precio_valor', isset($_POST['gdm_precio_valor']) ? floatval($_POST['gdm_precio_valor']) : 0);
        update_post_meta($post_id, '_gdm_precio_valor2', isset($_POST['gdm_precio_valor2']) ? floatval($_POST['gdm_precio_valor2']) : 0);
        update_post_meta($post_id, '_gdm_titulo_condicion', isset($_POST['gdm_titulo_condicion']) ? sanitize_text_field($_POST['gdm_titulo_condicion']) : 'contiene');
        update_post_meta($post_id, '_gdm_titulo_texto', isset($_POST['gdm_titulo_texto']) ? sanitize_text_field($_POST['gdm_titulo_texto']) : '');
        update_post_meta($post_id, '_gdm_titulo_case_sensitive', isset($_POST['gdm_titulo_case_sensitive']) ? '1' : '0');
        
        // Compatibilidad
        update_post_meta($post_id, '_gdm_todas_categorias', empty($_POST['gdm_categorias_objetivo']) ? '1' : '0');
        update_post_meta($post_id, '_gdm_cualquier_tag', empty($_POST['gdm_tags_objetivo']) ? '1' : '0');
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
            'categorias_objetivo' => get_post_meta($post_id, '_gdm_categorias_objetivo', true) ?: [],
            'tags_objetivo' => get_post_meta($post_id, '_gdm_tags_objetivo', true) ?: [],
            'productos_objetivo' => get_post_meta($post_id, '_gdm_productos_objetivo', true) ?: [],
            'atributos' => get_post_meta($post_id, '_gdm_atributos', true) ?: [],
            'stock_status' => get_post_meta($post_id, '_gdm_stock_status', true) ?: [],
            'precio_condicion' => get_post_meta($post_id, '_gdm_precio_condicion', true) ?: 'mayor_que',
            'precio_valor' => get_post_meta($post_id, '_gdm_precio_valor', true) ?: 0,
            'precio_valor2' => get_post_meta($post_id, '_gdm_precio_valor2', true) ?: 0,
            'titulo_condicion' => get_post_meta($post_id, '_gdm_titulo_condicion', true) ?: 'contiene',
            'titulo_texto' => get_post_meta($post_id, '_gdm_titulo_texto', true),
            'titulo_case_sensitive' => get_post_meta($post_id, '_gdm_titulo_case_sensitive', true),
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
            wp_send_json_error(['message' => __('Mínimo 3 caracteres', 'product-conditional-content')]);
        }
        
        $products = wc_get_products(['s' => $search, 'limit' => 50, 'return' => 'ids']);
        $results = [];
        foreach ($products as $product_id) {
            $product = wc_get_product($product_id);
            if ($product) {
                $results[] = ['id' => $product_id, 'title' => $product->get_name()];
            }
        }
        
        wp_send_json_success(['products' => $results]);
    }
}

GDM_Reglas_Metabox::init();