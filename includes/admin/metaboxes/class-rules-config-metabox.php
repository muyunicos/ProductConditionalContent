<?php
/**
 * Metabox de Configuración General de Reglas (Sistema Modular v6.1)
 * Incluye ámbitos mejorados con scroll, búsqueda y selectores avanzados
 * Compatible con WordPress 6.8.3, PHP 8.2, WooCommerce 10.2.2
 * 
 * @package ProductConditionalContent
 * @since 6.1.0
 * @author MuyUnicos
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
            
            <!-- Aplica a -->
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
                    <?php _e('Define a qué productos se aplicará esta regla. Puedes combinar múltiples condiciones.', 'product-conditional-content'); ?>
                </p>
                
                <!-- CATEGORÍAS MEJORADAS -->
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
                        
                        <div class="gdm-scope-description" id="gdm-categorias-description">
                            <strong><?php _e('Selecciona las categorías a filtrar:', 'product-conditional-content'); ?></strong>
                            <div class="gdm-scope-selected-items"></div>
                        </div>
                        
                        <input type="text" 
                               id="gdm_category_filter" 
                               class="gdm-filter-input" 
                               placeholder="<?php esc_attr_e('🔍 Buscar categorías...', 'product-conditional-content'); ?>">
                        
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
                        
                        <button type="button" class="button gdm-scope-apply" id="gdm-categorias-apply">
                            <span class="dashicons dashicons-yes"></span>
                            <?php _e('Aplicar Selección', 'product-conditional-content'); ?>
                        </button>
                        
                        <span class="gdm-selection-counter" id="gdm-category-counter" style="display:none;">0</span>
                    </div>
                </div>
                
                <!-- TAGS MEJORADAS -->
                <div class="gdm-scope-group">
                    <label class="gdm-scope-toggle">
                        <input type="checkbox" 
                               id="gdm_cualquier_tag" 
                               name="gdm_cualquier_tag" 
                               value="1"
                               <?php checked($data['cualquier_tag'], '1'); ?>>
                        <strong><?php _e('🏷️ Cualquier Etiqueta', 'product-conditional-content'); ?></strong>
                    </label>
                    
                    <div class="gdm-scope-content" id="gdm-tags-wrapper" <?php echo $data['cualquier_tag'] === '1' ? 'style="display:none;"' : ''; ?>>
                        
                        <div class="gdm-scope-description" id="gdm-tags-description">
                            <strong><?php _e('Selecciona las etiquetas a filtrar:', 'product-conditional-content'); ?></strong>
                            <div class="gdm-scope-selected-items"></div>
                        </div>
                        
                        <input type="text" 
                               id="gdm_tag_filter" 
                               class="gdm-filter-input" 
                               placeholder="<?php esc_attr_e('🔍 Buscar etiquetas...', 'product-conditional-content'); ?>">
                        
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
                        
                        <button type="button" class="button gdm-scope-apply" id="gdm-tags-apply">
                            <span class="dashicons dashicons-yes"></span>
                            <?php _e('Aplicar Selección', 'product-conditional-content'); ?>
                        </button>
                        
                        <span class="gdm-selection-counter" id="gdm-tag-counter" style="display:none;">0</span>
                    </div>
                </div>
                
                <!-- PRODUCTOS ESPECÍFICOS (NUEVO) -->
                <div class="gdm-scope-group">
                    <label class="gdm-scope-toggle">
                        <input type="checkbox" 
                               id="gdm_productos_especificos_enabled" 
                               name="gdm_productos_especificos_enabled" 
                               value="1"
                               <?php checked($data['productos_especificos_enabled'], '1'); ?>>
                        <strong><?php _e('🛍️ Productos Específicos', 'product-conditional-content'); ?></strong>
                    </label>
                    
                    <div class="gdm-scope-content" id="gdm-productos-wrapper" <?php echo $data['productos_especificos_enabled'] !== '1' ? 'style="display:none;"' : ''; ?>>
                        
                        <input type="text" 
                               id="gdm_product_search" 
                               class="gdm-filter-input" 
                               placeholder="<?php esc_attr_e('🔍 Buscar productos por nombre (mínimo 3 caracteres)...', 'product-conditional-content'); ?>">
                        
                        <div class="gdm-product-list">
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
                                    <p><?php _e('Escribe para buscar productos', 'product-conditional-content'); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <span class="gdm-selection-counter" id="gdm-product-counter" style="display:none;">0</span>
                    </div>
                </div>
                
                <!-- ATRIBUTOS (NUEVO) -->
                <div class="gdm-scope-group">
                    <label class="gdm-scope-toggle">
                        <input type="checkbox" 
                               id="gdm_atributos_enabled" 
                               name="gdm_atributos_enabled" 
                               value="1"
                               <?php checked($data['atributos_enabled'], '1'); ?>>
                        <strong><?php _e('🎨 Atributos de Productos', 'product-conditional-content'); ?></strong>
                    </label>
                    
                    <div class="gdm-scope-content" id="gdm-atributos-wrapper" <?php echo $data['atributos_enabled'] !== '1' ? 'style="display:none;"' : ''; ?>>
                        
                        <?php
                        $product_attributes = wc_get_attribute_taxonomies();
                        if ($product_attributes) {
                            foreach ($product_attributes as $attribute) {
                                $taxonomy = wc_attribute_taxonomy_name($attribute->attribute_name);
                                $terms = get_terms([
                                    'taxonomy' => $taxonomy,
                                    'hide_empty' => false,
                                ]);
                                
                                if ($terms && !is_wp_error($terms)) {
                                    ?>
                                    <div class="gdm-attribute-group">
                                        <strong><?php echo esc_html($attribute->attribute_label); ?>:</strong>
                                        <div class="gdm-attribute-list">
                                            <?php foreach ($terms as $term) {
                                                $checked = isset($data['atributos'][$taxonomy]) && in_array($term->term_id, $data['atributos'][$taxonomy]);
                                                ?>
                                                <label class="gdm-checkbox-item">
                                                    <input type="checkbox" 
                                                           name="gdm_atributos[<?php echo esc_attr($taxonomy); ?>][]" 
                                                           value="<?php echo esc_attr($term->term_id); ?>"
                                                           <?php checked($checked); ?>>
                                                    <span><?php echo esc_html($term->name); ?></span>
                                                </label>
                                            <?php } ?>
                                        </div>
                                    </div>
                                    <?php
                                }
                            }
                        } else {
                            echo '<p class="description">' . __('No hay atributos de producto configurados', 'product-conditional-content') . '</p>';
                        }
                        ?>
                    </div>
                </div>
                
                <!-- STOCK STATUS (NUEVO) -->
                <div class="gdm-scope-group">
                    <label class="gdm-scope-toggle">
                        <input type="checkbox" 
                               id="gdm_stock_enabled" 
                               name="gdm_stock_enabled" 
                               value="1"
                               <?php checked($data['stock_enabled'], '1'); ?>>
                        <strong><?php _e('📦 Estado de Stock', 'product-conditional-content'); ?></strong>
                    </label>
                    
                    <div class="gdm-scope-content" id="gdm-stock-wrapper" <?php echo $data['stock_enabled'] !== '1' ? 'style="display:none;"' : ''; ?>>
                        
                        <label class="gdm-checkbox-item">
                            <input type="checkbox" 
                                   name="gdm_stock_status[]" 
                                   value="instock"
                                   <?php checked(in_array('instock', $data['stock_status'])); ?>>
                            <span><?php _e('✅ En Stock', 'product-conditional-content'); ?></span>
                        </label>
                        
                        <label class="gdm-checkbox-item">
                            <input type="checkbox" 
                                   name="gdm_stock_status[]" 
                                   value="outofstock"
                                   <?php checked(in_array('outofstock', $data['stock_status'])); ?>>
                            <span><?php _e('❌ Sin Stock', 'product-conditional-content'); ?></span>
                        </label>
                        
                        <label class="gdm-checkbox-item">
                            <input type="checkbox" 
                                   name="gdm_stock_status[]" 
                                   value="onbackorder"
                                   <?php checked(in_array('onbackorder', $data['stock_status'])); ?>>
                            <span><?php _e('⏳ En Pedido Pendiente', 'product-conditional-content'); ?></span>
                        </label>
                    </div>
                </div>
                
                <!-- PRECIO (NUEVO) -->
                <div class="gdm-scope-group">
                    <label class="gdm-scope-toggle">
                        <input type="checkbox" 
                               id="gdm_precio_enabled" 
                               name="gdm_precio_enabled" 
                               value="1"
                               <?php checked($data['precio_enabled'], '1'); ?>>
                        <strong><?php _e('💵 Filtro por Precio', 'product-conditional-content'); ?></strong>
                    </label>
                    
                    <div class="gdm-scope-content" id="gdm-precio-wrapper" <?php echo $data['precio_enabled'] !== '1' ? 'style="display:none;"' : ''; ?>>
                        
                        <label>
                            <strong><?php _e('Condición:', 'product-conditional-content'); ?></strong>
                        </label>
                        <select name="gdm_precio_condicion" id="gdm_precio_condicion" class="regular-text">
                            <option value="mayor_que" <?php selected($data['precio_condicion'], 'mayor_que'); ?>>
                                <?php _e('Mayor que', 'product-conditional-content'); ?>
                            </option>
                            <option value="menor_que" <?php selected($data['precio_condicion'], 'menor_que'); ?>>
                                <?php _e('Menor que', 'product-conditional-content'); ?>
                            </option>
                            <option value="igual_a" <?php selected($data['precio_condicion'], 'igual_a'); ?>>
                                <?php _e('Igual a', 'product-conditional-content'); ?>
                            </option>
                            <option value="entre" <?php selected($data['precio_condicion'], 'entre'); ?>>
                                <?php _e('Entre', 'product-conditional-content'); ?>
                            </option>
                        </select>
                        
                        <div style="margin-top: 10px;">
                            <label>
                                <strong><?php _e('Valor:', 'product-conditional-content'); ?></strong>
                            </label>
                            <input type="number" 
                                   name="gdm_precio_valor" 
                                   value="<?php echo esc_attr($data['precio_valor']); ?>" 
                                   step="0.01" 
                                   min="0"
                                   class="regular-text">
                            <?php echo get_woocommerce_currency_symbol(); ?>
                        </div>
                        
                        <div id="gdm-precio-valor2-wrapper" style="margin-top: 10px; <?php echo $data['precio_condicion'] !== 'entre' ? 'display:none;' : ''; ?>">
                            <label>
                                <strong><?php _e('Valor máximo:', 'product-conditional-content'); ?></strong>
                            </label>
                            <input type="number" 
                                   name="gdm_precio_valor2" 
                                   value="<?php echo esc_attr($data['precio_valor2']); ?>" 
                                   step="0.01" 
                                   min="0"
                                   class="regular-text">
                            <?php echo get_woocommerce_currency_symbol(); ?>
                        </div>
                    </div>
                </div>
                
                <!-- TÍTULO (NUEVO) -->
                <div class="gdm-scope-group">
                    <label class="gdm-scope-toggle">
                        <input type="checkbox" 
                               id="gdm_titulo_enabled" 
                               name="gdm_titulo_enabled" 
                               value="1"
                               <?php checked($data['titulo_enabled'], '1'); ?>>
                        <strong><?php _e('📝 Filtro por Título', 'product-conditional-content'); ?></strong>
                    </label>
                    
                    <div class="gdm-scope-content" id="gdm-titulo-wrapper" <?php echo $data['titulo_enabled'] !== '1' ? 'style="display:none;"' : ''; ?>>
                        
                        <label>
                            <strong><?php _e('Condición:', 'product-conditional-content'); ?></strong>
                        </label>
                        <select name="gdm_titulo_condicion" class="regular-text">
                            <option value="contiene" <?php selected($data['titulo_condicion'], 'contiene'); ?>>
                                <?php _e('Contiene', 'product-conditional-content'); ?>
                            </option>
                            <option value="no_contiene" <?php selected($data['titulo_condicion'], 'no_contiene'); ?>>
                                <?php _e('No contiene', 'product-conditional-content'); ?>
                            </option>
                            <option value="empieza_con" <?php selected($data['titulo_condicion'], 'empieza_con'); ?>>
                                <?php _e('Empieza con', 'product-conditional-content'); ?>
                            </option>
                            <option value="termina_con" <?php selected($data['titulo_condicion'], 'termina_con'); ?>>
                                <?php _e('Termina con', 'product-conditional-content'); ?>
                            </option>
                            <option value="regex" <?php selected($data['titulo_condicion'], 'regex'); ?>>
                                <?php _e('Expresión Regular (Regex)', 'product-conditional-content'); ?>
                            </option>
                        </select>
                        
                        <div style="margin-top: 10px;">
                            <label>
                                <strong><?php _e('Texto:', 'product-conditional-content'); ?></strong>
                            </label>
                            <input type="text" 
                                   name="gdm_titulo_texto" 
                                   value="<?php echo esc_attr($data['titulo_texto']); ?>" 
                                   class="regular-text"
                                   placeholder="<?php esc_attr_e('Texto a buscar', 'product-conditional-content'); ?>">
                        </div>
                        
                        <label style="margin-top: 10px;">
                            <input type="checkbox" 
                                   name="gdm_titulo_case_sensitive" 
                                   value="1" 
                                   <?php checked($data['titulo_case_sensitive'], '1'); ?>>
                            <?php _e('Distinguir mayúsculas/minúsculas', 'product-conditional-content'); ?>
                        </label>
                    </div>
                </div>
                
            </div>
            
        </div>
        
        <style>
            /* Estilos integrados para los ámbitos mejorados */
            .gdm-scope-group {
                margin-bottom: 15px;
                border: 1px solid #ddd;
                border-radius: 4px;
                background: #fff;
            }
            
            .gdm-scope-toggle {
                display: flex;
                align-items: center;
                padding: 12px 15px;
                background: #f9f9f9;
                border-bottom: 1px solid #ddd;
                cursor: pointer;
                user-select: none;
                transition: background 0.2s ease;
            }
            
            .gdm-scope-toggle:hover {
                background: #f0f0f1;
            }
            
            .gdm-scope-toggle input[type="checkbox"] {
                margin: 0 10px 0 0;
            }
            
            .gdm-scope-content {
                padding: 15px;
            }
            
            .gdm-filter-input {
                width: 100%;
                padding: 10px 12px;
                border: 1px solid #ddd;
                border-radius: 4px;
                margin-bottom: 10px;
                box-sizing: border-box;
            }
            
            .gdm-filter-input:focus {
                border-color: #2271b1;
                outline: none;
                box-shadow: 0 0 0 1px #2271b1;
            }
            
            .gdm-category-list,
            .gdm-tag-list,
            .gdm-product-list,
            .gdm-attribute-list {
                max-height: 250px;
                overflow-y: auto;
                border: 1px solid #ddd;
                border-radius: 4px;
                padding: 8px;
                background: #fafafa;
                margin-bottom: 12px;
            }
            
            .gdm-category-list::-webkit-scrollbar,
            .gdm-tag-list::-webkit-scrollbar,
            .gdm-product-list::-webkit-scrollbar,
            .gdm-attribute-list::-webkit-scrollbar {
                width: 8px;
            }
            
            .gdm-category-list::-webkit-scrollbar-thumb,
            .gdm-tag-list::-webkit-scrollbar-thumb,
            .gdm-product-list::-webkit-scrollbar-thumb,
            .gdm-attribute-list::-webkit-scrollbar-thumb {
                background: #c1c1c1;
                border-radius: 4px;
            }
            
            .gdm-scope-description {
                padding: 10px;
                background: #fff3cd;
                border: 1px solid #ffc107;
                border-radius: 4px;
                margin-bottom: 12px;
                font-size: 13px;
                color: #856404;
                display: none;
            }
            
            .gdm-scope-description.active {
                display: block;
            }
            
            .gdm-scope-selected-items {
                margin-top: 5px;
                font-size: 12px;
            }
            
            .gdm-scope-apply {
                width: 100%;
                padding: 10px 15px;
                background: #2271b1;
                color: #fff;
                border: none;
                border-radius: 4px;
                font-weight: 600;
                cursor: pointer;
                transition: background 0.2s ease;
            }
            
            .gdm-scope-apply:hover {
                background: #135e96;
            }
            
            .gdm-scope-apply .dashicons {
                vertical-align: middle;
                margin-right: 5px;
            }
            
            .gdm-selection-counter {
                display: inline-block;
                background: #2271b1;
                color: #fff;
                padding: 2px 8px;
                border-radius: 10px;
                font-size: 11px;
                font-weight: 600;
                margin-left: 10px;
            }
            
            .gdm-empty-state {
                padding: 30px;
                text-align: center;
                color: #999;
            }
            
            .gdm-attribute-group {
                margin-bottom: 15px;
                padding-bottom: 15px;
                border-bottom: 1px solid #ddd;
            }
            
            .gdm-attribute-group:last-child {
                border-bottom: none;
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

        // Categorías
        $todas_categorias = isset($_POST['gdm_todas_categorias']) ? '1' : '0';
        update_post_meta($post_id, '_gdm_todas_categorias', $todas_categorias);
        
        $categorias_objetivo = isset($_POST['gdm_categorias_objetivo']) ? array_map('intval', $_POST['gdm_categorias_objetivo']) : [];
        update_post_meta($post_id, '_gdm_categorias_objetivo', $categorias_objetivo);

        // Tags
        $cualquier_tag = isset($_POST['gdm_cualquier_tag']) ? '1' : '0';
        update_post_meta($post_id, '_gdm_cualquier_tag', $cualquier_tag);
        
        $tags_objetivo = isset($_POST['gdm_tags_objetivo']) ? array_map('intval', $_POST['gdm_tags_objetivo']) : [];
        update_post_meta($post_id, '_gdm_tags_objetivo', $tags_objetivo);
        
        // Productos específicos (NUEVO)
        $productos_especificos_enabled = isset($_POST['gdm_productos_especificos_enabled']) ? '1' : '0';
        update_post_meta($post_id, '_gdm_productos_especificos_enabled', $productos_especificos_enabled);
        
        $productos_objetivo = isset($_POST['gdm_productos_objetivo']) ? array_map('intval', $_POST['gdm_productos_objetivo']) : [];
        update_post_meta($post_id, '_gdm_productos_objetivo', $productos_objetivo);
        
        // Atributos (NUEVO)
        $atributos_enabled = isset($_POST['gdm_atributos_enabled']) ? '1' : '0';
        update_post_meta($post_id, '_gdm_atributos_enabled', $atributos_enabled);
        
        $atributos = isset($_POST['gdm_atributos']) ? $_POST['gdm_atributos'] : [];
        update_post_meta($post_id, '_gdm_atributos', $atributos);
        
        // Stock (NUEVO)
        $stock_enabled = isset($_POST['gdm_stock_enabled']) ? '1' : '0';
        update_post_meta($post_id, '_gdm_stock_enabled', $stock_enabled);
        
        $stock_status = isset($_POST['gdm_stock_status']) ? array_map('sanitize_text_field', $_POST['gdm_stock_status']) : [];
        update_post_meta($post_id, '_gdm_stock_status', $stock_status);
        
        // Precio (NUEVO)
        $precio_enabled = isset($_POST['gdm_precio_enabled']) ? '1' : '0';
        update_post_meta($post_id, '_gdm_precio_enabled', $precio_enabled);
        
        $precio_condicion = isset($_POST['gdm_precio_condicion']) ? sanitize_text_field($_POST['gdm_precio_condicion']) : 'mayor_que';
        update_post_meta($post_id, '_gdm_precio_condicion', $precio_condicion);
        
        $precio_valor = isset($_POST['gdm_precio_valor']) ? floatval($_POST['gdm_precio_valor']) : 0;
        update_post_meta($post_id, '_gdm_precio_valor', $precio_valor);
        
        $precio_valor2 = isset($_POST['gdm_precio_valor2']) ? floatval($_POST['gdm_precio_valor2']) : 0;
        update_post_meta($post_id, '_gdm_precio_valor2', $precio_valor2);
        
        // Título (NUEVO)
        $titulo_enabled = isset($_POST['gdm_titulo_enabled']) ? '1' : '0';
        update_post_meta($post_id, '_gdm_titulo_enabled', $titulo_enabled);
        
        $titulo_condicion = isset($_POST['gdm_titulo_condicion']) ? sanitize_text_field($_POST['gdm_titulo_condicion']) : 'contiene';
        update_post_meta($post_id, '_gdm_titulo_condicion', $titulo_condicion);
        
        $titulo_texto = isset($_POST['gdm_titulo_texto']) ? sanitize_text_field($_POST['gdm_titulo_texto']) : '';
        update_post_meta($post_id, '_gdm_titulo_texto', $titulo_texto);
        
        $titulo_case_sensitive = isset($_POST['gdm_titulo_case_sensitive']) ? '1' : '0';
        update_post_meta($post_id, '_gdm_titulo_case_sensitive', $titulo_case_sensitive);
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
            
            // NUEVOS
            'productos_especificos_enabled' => get_post_meta($post_id, '_gdm_productos_especificos_enabled', true),
            'productos_objetivo' => get_post_meta($post_id, '_gdm_productos_objetivo', true) ?: [],
            'atributos_enabled' => get_post_meta($post_id, '_gdm_atributos_enabled', true),
            'atributos' => get_post_meta($post_id, '_gdm_atributos', true) ?: [],
            'stock_enabled' => get_post_meta($post_id, '_gdm_stock_enabled', true),
            'stock_status' => get_post_meta($post_id, '_gdm_stock_status', true) ?: [],
            'precio_enabled' => get_post_meta($post_id, '_gdm_precio_enabled', true),
            'precio_condicion' => get_post_meta($post_id, '_gdm_precio_condicion', true) ?: 'mayor_que',
            'precio_valor' => get_post_meta($post_id, '_gdm_precio_valor', true) ?: 0,
            'precio_valor2' => get_post_meta($post_id, '_gdm_precio_valor2', true) ?: 0,
            'titulo_enabled' => get_post_meta($post_id, '_gdm_titulo_enabled', true),
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