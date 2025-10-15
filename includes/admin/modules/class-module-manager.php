<?php
/**
 * Metabox de Ámbito de Aplicación Mejorado
 * Incluye: Categorías, Tags, Productos Específicos, Atributos, Stock, Precio, Título
 * Compatible con WordPress 6.8.3, PHP 8.2, WooCommerce 10.2.2
 * 
 * @package ProductConditionalContent
 * @since 6.1.0
 * @date 2025-10-15
 */

if (!defined('ABSPATH')) exit;

final class GDM_Scope_Metabox {
    
    public static function init() {
        add_action('add_meta_boxes', [__CLASS__, 'add_metabox']);
        add_action('save_post_gdm_regla', [__CLASS__, 'save_metabox'], 10, 2);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        
        // AJAX para búsqueda de productos
        add_action('wp_ajax_gdm_search_products', [__CLASS__, 'ajax_search_products']);
    }
    
    /**
     * Encolar assets
     */
    public static function enqueue_assets($hook) {
        $screen = get_current_screen();
        if ($screen->id !== 'gdm_regla') {
            return;
        }
        
        wp_enqueue_style(
            'gdm-scope-selector',
            GDM_PLUGIN_URL . 'assets/admin/css/scope-selector.css',
            [],
            GDM_VERSION
        );
        
        wp_enqueue_script(
            'gdm-scope-selector',
            GDM_PLUGIN_URL . 'assets/admin/js/metaboxes/scope-selector.js',
            ['jquery'],
            GDM_VERSION,
            true
        );
        
        wp_localize_script('gdm-scope-selector', 'gdmScopeSelector', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gdm_scope_nonce'),
            'i18n' => [
                'searching' => __('Buscando...', 'product-conditional-content'),
                'noResults' => __('No se encontraron resultados', 'product-conditional-content'),
                'selectAtLeast' => __('Selecciona al menos un elemento', 'product-conditional-content'),
            ]
        ]);
    }
    
    /**
     * Registrar metabox
     */
    public static function add_metabox() {
        add_meta_box(
            'gdm_scope_metabox',
            __('🎯 Ámbito de Aplicación Avanzado', 'product-conditional-content'),
            [__CLASS__, 'render_metabox'],
            'gdm_regla',
            'normal',
            'default'
        );
    }
    
    /**
     * Renderizar metabox
     */
    public static function render_metabox($post) {
        wp_nonce_field('gdm_save_scope_data', 'gdm_scope_nonce');
        
        $data = self::get_scope_data($post->ID);
        ?>
        <div class="gdm-scope-container">
            
            <p class="description" style="margin-bottom: 20px;">
                <?php _e('Define las condiciones específicas para que esta regla se aplique. Puedes combinar múltiples condiciones.', 'product-conditional-content'); ?>
            </p>
            
            <!-- CATEGORÍAS -->
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
            
            <!-- TAGS -->
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
            
            <!-- PRODUCTOS ESPECÍFICOS -->
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
                           placeholder="<?php esc_attr_e('🔍 Buscar productos por nombre...', 'product-conditional-content'); ?>">
                    
                    <div class="gdm-product-list">
                        <div class="gdm-empty-state">
                            <p><?php _e('Escribe al menos 3 caracteres para buscar productos', 'product-conditional-content'); ?></p>
                        </div>
                    </div>
                    
                    <input type="hidden" name="gdm_productos_objetivo" id="gdm_productos_objetivo" value="<?php echo esc_attr(json_encode($data['productos_objetivo'])); ?>">
                    
                    <span class="gdm-selection-counter" id="gdm-product-counter" style="display:none;">0</span>
                </div>
            </div>
            
            <!-- ATRIBUTOS -->
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
                                <div style="margin-bottom: 15px;">
                                    <strong><?php echo esc_html($attribute->attribute_label); ?>:</strong>
                                    <div style="margin-top: 5px; max-height: 150px; overflow-y: auto; border: 1px solid #ddd; padding: 8px; border-radius: 3px;">
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
                        echo '<p>' . __('No hay atributos de producto configurados', 'product-conditional-content') . '</p>';
                    }
                    ?>
                </div>
            </div>
            
            <!-- STOCK STATUS -->
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
                        <span><?php _e('En Stock', 'product-conditional-content'); ?></span>
                    </label>
                    
                    <label class="gdm-checkbox-item">
                        <input type="checkbox" 
                               name="gdm_stock_status[]" 
                               value="outofstock"
                               <?php checked(in_array('outofstock', $data['stock_status'])); ?>>
                        <span><?php _e('Sin Stock', 'product-conditional-content'); ?></span>
                    </label>
                    
                    <label class="gdm-checkbox-item">
                        <input type="checkbox" 
                               name="gdm_stock_status[]" 
                               value="onbackorder"
                               <?php checked(in_array('onbackorder', $data['stock_status'])); ?>>
                        <span><?php _e('En Pedido Pendiente', 'product-conditional-content'); ?></span>
                    </label>
                </div>
            </div>
            
            <!-- PRECIO -->
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
                    <select name="gdm_precio_condicion" class="regular-text">
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
            
            <!-- TÍTULO -->
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
        
        <script>
        jQuery(document).ready(function($) {
            // Toggle de secciones
            $('.gdm-scope-toggle input[type="checkbox"]').on('change', function() {
                var $content = $(this).closest('.gdm-scope-group').find('.gdm-scope-content');
                if ($(this).is(':checked')) {
                    $content.slideUp();
                } else {
                    $content.slideDown();
                }
            });
            
            // Toggle de precio "entre"
            $('[name="gdm_precio_condicion"]').on('change', function() {
                if ($(this).val() === 'entre') {
                    $('#gdm-precio-valor2-wrapper').slideDown();
                } else {
                    $('#gdm-precio-valor2-wrapper').slideUp();
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Guardar datos del metabox
     */
    public static function save_metabox($post_id, $post) {
        if (!isset($_POST['gdm_scope_nonce']) || !wp_verify_nonce($_POST['gdm_scope_nonce'], 'gdm_save_scope_data')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
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
        
        // Productos específicos
        $productos_especificos_enabled = isset($_POST['gdm_productos_especificos_enabled']) ? '1' : '0';
        update_post_meta($post_id, '_gdm_productos_especificos_enabled', $productos_especificos_enabled);
        
        $productos_objetivo = isset($_POST['gdm_productos_objetivo']) ? json_decode(stripslashes($_POST['gdm_productos_objetivo']), true) : [];
        update_post_meta($post_id, '_gdm_productos_objetivo', $productos_objetivo);
        
        // Atributos
        $atributos_enabled = isset($_POST['gdm_atributos_enabled']) ? '1' : '0';
        update_post_meta($post_id, '_gdm_atributos_enabled', $atributos_enabled);
        
        $atributos = isset($_POST['gdm_atributos']) ? $_POST['gdm_atributos'] : [];
        update_post_meta($post_id, '_gdm_atributos', $atributos);
        
        // Stock
        $stock_enabled = isset($_POST['gdm_stock_enabled']) ? '1' : '0';
        update_post_meta($post_id, '_gdm_stock_enabled', $stock_enabled);
        
        $stock_status = isset($_POST['gdm_stock_status']) ? array_map('sanitize_text_field', $_POST['gdm_stock_status']) : [];
        update_post_meta($post_id, '_gdm_stock_status', $stock_status);
        
        // Precio
        $precio_enabled = isset($_POST['gdm_precio_enabled']) ? '1' : '0';
        update_post_meta($post_id, '_gdm_precio_enabled', $precio_enabled);
        
        $precio_condicion = isset($_POST['gdm_precio_condicion']) ? sanitize_text_field($_POST['gdm_precio_condicion']) : 'mayor_que';
        update_post_meta($post_id, '_gdm_precio_condicion', $precio_condicion);
        
        $precio_valor = isset($_POST['gdm_precio_valor']) ? floatval($_POST['gdm_precio_valor']) : 0;
        update_post_meta($post_id, '_gdm_precio_valor', $precio_valor);
        
        $precio_valor2 = isset($_POST['gdm_precio_valor2']) ? floatval($_POST['gdm_precio_valor2']) : 0;
        update_post_meta($post_id, '_gdm_precio_valor2', $precio_valor2);
        
        // Título
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
     * Obtener datos del ámbito
     */
    private static function get_scope_data($post_id) {
        static $cache = [];
        
        if (isset($cache[$post_id])) {
            return $cache[$post_id];
        }
        
        $data = [
            // Categorías
            'todas_categorias' => get_post_meta($post_id, '_gdm_todas_categorias', true) ?: '1',
            'categorias_objetivo' => get_post_meta($post_id, '_gdm_categorias_objetivo', true) ?: [],
            
            // Tags
            'cualquier_tag' => get_post_meta($post_id, '_gdm_cualquier_tag', true) ?: '1',
            'tags_objetivo' => get_post_meta($post_id, '_gdm_tags_objetivo', true) ?: [],
            
            // Productos
            'productos_especificos_enabled' => get_post_meta($post_id, '_gdm_productos_especificos_enabled', true),
            'productos_objetivo' => get_post_meta($post_id, '_gdm_productos_objetivo', true) ?: [],
            
            // Atributos
            'atributos_enabled' => get_post_meta($post_id, '_gdm_atributos_enabled', true),
            'atributos' => get_post_meta($post_id, '_gdm_atributos', true) ?: [],
            
            // Stock
            'stock_enabled' => get_post_meta($post_id, '_gdm_stock_enabled', true),
            'stock_status' => get_post_meta($post_id, '_gdm_stock_status', true) ?: [],
            
            // Precio
            'precio_enabled' => get_post_meta($post_id, '_gdm_precio_enabled', true),
            'precio_condicion' => get_post_meta($post_id, '_gdm_precio_condicion', true) ?: 'mayor_que',
            'precio_valor' => get_post_meta($post_id, '_gdm_precio_valor', true) ?: 0,
            'precio_valor2' => get_post_meta($post_id, '_gdm_precio_valor2', true) ?: 0,
            
            // Título
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
        check_ajax_referer('gdm_scope_nonce', 'nonce');
        
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

GDM_Scope_Metabox::init();