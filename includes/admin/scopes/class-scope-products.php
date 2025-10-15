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

class GDM_Scope_Products extends GDM_Scope_Base {
    
    protected $scope_id = 'productos';
    protected $scope_name = 'Productos Específicos';
    protected $scope_icon = '🛍️';
    protected $priority = 30;
    
    protected function scope_init() {
        add_action('wp_ajax_gdm_search_products', [$this, 'ajax_search_products']);
    }
    
    protected function render_content($post_id, $data) {
        ?>
        <input type="text" 
               id="gdm-<?php echo esc_attr($this->scope_id); ?>-search" 
               class="gdm-filter-input" 
               placeholder="<?php esc_attr_e('🔍 Buscar productos (mín. 3 caracteres)...', 'product-conditional-content'); ?>">
        
        <div class="gdm-<?php echo esc_attr($this->scope_id); ?>-list gdm-scope-list">
            <?php if (!empty($data['objetivo'])): ?>
                <?php foreach ($data['objetivo'] as $product_id): 
                    $product = wc_get_product($product_id);
                    if ($product):
                ?>
                    <label class="gdm-checkbox-item">
                        <input type="checkbox" 
                               name="gdm_<?php echo esc_attr($this->scope_id); ?>_objetivo[]" 
                               value="<?php echo esc_attr($product_id); ?>"
                               class="gdm-scope-item-checkbox"
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
        $objetivo = isset($_POST["gdm_{$this->scope_id}_objetivo"]) 
            ? array_map('intval', $_POST["gdm_{$this->scope_id}_objetivo"]) 
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
        $data = $this->get_scope_data($rule_id);
        
        if (empty($data['objetivo'])) {
            return true;
        }
        
        return in_array($product_id, $data['objetivo']);
    }
    
    /**
     * AJAX: Buscar productos
     */
    public function ajax_search_products() {
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
                $results[] = [
                    'id' => $product_id,
                    'title' => $product->get_name(),
                    'price' => $product->get_price_html(),
                ];
            }
        }
        
        wp_send_json_success(['products' => $results]);
    }
    
    protected function render_styles() {
        ?>
        <style>
            .gdm-item-price {
                color: #2271b1;
                font-size: 11px;
                margin-left: 5px;
            }
        </style>
        <?php
    }
    
    protected function render_scripts() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            let searchTimeout;
            
            $('#gdm-<?php echo esc_js($this->scope_id); ?>-search').on('keyup', function() {
                clearTimeout(searchTimeout);
                var search = $(this).val();
                
                if (search.length < 3) {
                    $('.gdm-<?php echo esc_js($this->scope_id); ?>-list').html(
                        '<div class="gdm-empty-state"><p>Escribe al menos 3 caracteres</p></div>'
                    );
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
                                var html = '';
                                response.data.products.forEach(function(product) {
                                    html += '<label class="gdm-checkbox-item">' +
                                           '<input type="checkbox" name="gdm_productos_objetivo[]" value="' + product.id + '" class="gdm-scope-item-checkbox">' +
                                           '<span>' + product.title + '</span>' +
                                           '<span class="gdm-item-price">' + product.price + '</span>' +
                                           '</label>';
                                });
                                $('.gdm-<?php echo esc_js($this->scope_id); ?>-list').html(html);
                            } else {
                                $('.gdm-<?php echo esc_js($this->scope_id); ?>-list').html(
                                    '<div class="gdm-empty-state"><p>No se encontraron productos</p></div>'
                                );
                            }
                        }
                    });
                }, 500);
            });
            
            $(document).on('change', '.gdm-<?php echo esc_js($this->scope_id); ?>-list input', function() {
                var count = $('.gdm-<?php echo esc_js($this->scope_id); ?>-list input:checked').length;
                $('#gdm-<?php echo esc_js($this->scope_id); ?>-counter').text(
                    count > 0 ? count + ' seleccionados' : 'Ninguno seleccionado'
                );
            });
        });
        </script>
        <?php
    }
}