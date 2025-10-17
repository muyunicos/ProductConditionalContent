<?php
/**
 * Ámbito: Productos Específicos
 * Filtrar productos por IDs específicos
 * Compatible con WordPress 6.8.3, PHP 8.2, WooCommerce 10.2.2
 * 
 * @package ProductConditionalContent
 * @since 6.1.0
 * @date 2025-10-15
 */

if (!defined('ABSPATH')) exit;

class GDM_Condition_Products extends GDM_Condition_Base {
    
    protected $condition_id = 'products';
    protected $condition_name = 'Productos Específicos';
    protected $condition_description = 'La regla solo se aplica a productos específicos';
    protected $condition_icon = '🛍️';
    protected $priority = 30;
    protected $supported_contexts = ['products'];
    
    protected function condition_init() {
        add_action('wp_ajax_gdm_search_products', [$this, 'ajax_search_products']);
    }
    
    protected function render_content($post_id, $data) {
        ?>
        <input type="text" 
               id="gdm-<?php echo esc_attr($this->condition_id); ?>-search" 
               class="gdm-filter-input" 
               placeholder="<?php esc_attr_e('🔍 Buscar productos (mín. 3 caracteres)...', 'product-conditional-content'); ?>">
        
        <div class="gdm-<?php echo esc_attr($this->condition_id); ?>-list gdm-condition-list">
            <?php if (!empty($data['objetivo'])): ?>
                <?php foreach ($data['objetivo'] as $product_id): 
                    $product = wc_get_product($product_id);
                    if ($product):
                ?>
                    <label class="gdm-checkbox-item">
                        <input type="checkbox" 
                               name="gdm_<?php echo esc_attr($this->condition_id); ?>_objetivo[]" 
                               value="<?php echo esc_attr($product_id); ?>"
                               class="gdm-condition-item-checkbox"
                               checked>
                        <span><?php echo esc_html($product->get_name()); ?></span>
                        <span class="gdm-item-price"><?php echo $product->get_price_html(); ?></span>
                    </label>
                <?php endif; endforeach; ?>
            <?php else: ?>
                <div class="gdm-empty-state">
                    <p><?php _e('Busca productos para agregar', 'product-conditional-content'); ?></p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    public function save($post_id) {
        $objetivo = isset($_POST["gdm_{$this->condition_id}_objetivo"]) 
            ? array_map('intval', $_POST["gdm_{$this->condition_id}_objetivo"]) 
            : [];
        
        $this->save_field($post_id, 'objetivo', $objetivo);
    }
    
    protected function get_default_data() {
        return ['objetivo' => []];
    }
    
    protected function has_selection($data) {
        return !empty($data['objetivo']);
    }
    
    protected function get_summary($data) {
        if (empty($data['objetivo'])) {
            return '';
        }
        
        $names = [];
        foreach ($data['objetivo'] as $prod_id) {
            $product = wc_get_product($prod_id);
            if ($product) {
                $names[] = $product->get_name();
            }
        }
        
        if (count($names) <= 3) {
            return implode(', ', $names);
        }
        
        return implode(', ', array_slice($names, 0, 3)) . 
               ' <em>y ' . (count($names) - 3) . ' más</em>';
    }
    
    protected function get_counter_text($data) {
        $count = count($data['objetivo']);
        return $count > 0 ? "{$count} seleccionados" : 'Ninguno seleccionado';
    }
    
    public function matches_product($product_id, $rule_id) {
        $data = $this->get_condition_data($rule_id);
        
        if (empty($data['objetivo'])) {
            return true;
        }
        
        return in_array($product_id, $data['objetivo']);
    }
    
    /**
     * AJAX: Buscar productos
     * ✅ MEJORA #1: Validación de permisos y caché
     */
    public function ajax_search_products() {
        check_ajax_referer('gdm_admin_nonce', 'nonce');
        
        // ✅ Verificar permisos
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Permisos insuficientes', 'product-conditional-content')]);
        }
        
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        
        if (strlen($search) < 3) {
            wp_send_json_error(['message' => __('Mínimo 3 caracteres', 'product-conditional-content')]);
        }
        
        // ✅ Implementar caché
        $cache_key = 'gdm_product_search_' . md5($search);
        $products = wp_cache_get($cache_key, 'gdm_conditions');
        
        if ($products === false) {
            $products = wc_get_products([
                's' => $search, 
                'limit' => 50, 
                'return' => 'ids',
                'orderby' => 'title',
                'order' => 'ASC'
            ]);
            wp_cache_set($cache_key, $products, 'gdm_conditions', 300); // 5 minutos
        }
        
        $results = [];
        
        foreach ($products as $product_id) {
            $product = wc_get_product($product_id);
            if ($product) {
                $results[] = [
                    'id' => $product_id,
                    'title' => $product->get_name(),
                    'price' => $product->get_price_html(),
                ];
            }
        }
        
        wp_send_json_success(['products' => $results]);
    }
    
    /**
     * ✅ FIX #1: Corregido selector y lógica de duplicados
     * ✅ MEJORA #4: Debounce optimizado
     */
    protected function render_scripts() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            var searchTimeout;
            var $searchInput = $('#gdm-<?php echo esc_js($this->condition_id); ?>-search');
            var $list = $('.gdm-<?php echo esc_js($this->condition_id); ?>-list');
            
            // ✅ Búsqueda con debounce optimizado
            $searchInput.on('input', function() {
                clearTimeout(searchTimeout);
                var search = this.value.trim();
                
                if (search.length < 3) {
                    $list.html('<div class="gdm-empty-state"><p>Escribe al menos 3 caracteres</p></div>');
                    return;
                }
                
                searchTimeout = setTimeout(function() {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'gdm_search_products',
                            nonce: '<?php echo wp_create_nonce('gdm_admin_nonce'); ?>',
                            search: search
                        },
                        success: function(response) {
                            if (response.success && response.data.products.length > 0) {
                                // ✅ Obtener IDs ya seleccionados
                                var selectedIds = [];
                                $list.find('input:checked').each(function() {
                                    selectedIds.push($(this).val());
                                });
                                
                                var html = '';
                                response.data.products.forEach(function(product) {
                                    var productIdStr = product.id.toString();
                                    
                                    // ✅ No duplicar productos existentes
                                    if ($list.find('input[value="' + productIdStr + '"]').length > 0) {
                                        return;
                                    }
                                    
                                    var isChecked = selectedIds.includes(productIdStr);
                                    
                                    html += '<label class="gdm-checkbox-item">' +
                                           '<input type="checkbox" name="gdm_productos_objetivo[]" value="' + product.id + '" ' + 
                                           (isChecked ? 'checked' : '') + ' class="gdm-condition-item-checkbox">' +
                                           '<span>' + product.title + '</span>' +
                                           '<span class="gdm-item-price">' + product.price + '</span>' +
                                           '</label>';
                                });
                                
                                // ✅ APPEND en vez de reemplazar
                                if (html) {
                                    $list.append(html);
                                }
                            } else {
                                $list.html('<div class="gdm-empty-state"><p>No se encontraron productos</p></div>');
                            }
                        },
                        error: function() {
                            $list.html('<div class="gdm-empty-state"><p>Error en la búsqueda</p></div>');
                        }
                    });
                }, 300); // ✅ Debounce de 300ms
            });
            
            // ✅ Actualizar contador dinámicamente
            $(document).on('change', '.gdm-<?php echo esc_js($this->condition_id); ?>-list input', function() {
                var count = $list.find('input:checked').length;
                $('#gdm-<?php echo esc_js($this->condition_id); ?>-counter').text(
                    count > 0 ? count + ' seleccionados' : 'Ninguno seleccionado'
                );
            });
        });
        </script>
        <?php
    }
}
public function get_supported_context() {
    return $this->$supported_contexts ?? [];
}
public function supports_context($context) {
    return in_array($context, $this->get_supported_categories());
}
