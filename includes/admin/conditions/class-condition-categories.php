<?php
/**
 * Ámbito: Categorías Determinadas
 * Filtrar productos por categorías
 * Compatible con WordPress 6.8.3, PHP 8.2, WooCommerce 10.2.2
 * 
 * @package ProductConditionalContent
 * @since 6.1.0
 * @date 2025-10-15
 */

if (!defined('ABSPATH')) exit;

class GDM_Condition_Categories extends GDM_Condition_Base {
    
    protected $condition_id = 'product-categories';
    protected $condition_name = 'Categorías Determinadas';
    protected $condition_description = 'Filtra según categoria de productos';
    protected $condition_icon = '📂';
    protected $priority = 10;
    protected $supported_contexts = ['products'];
    
    protected function render_content($post_id, $data) {
        ?>
        <input type="text" 
               id="gdm-<?php echo esc_attr($this->condition_id); ?>-filter" 
               class="gdm-filter-input" 
               placeholder="<?php esc_attr_e('🔍 Buscar categorías...', 'product-conditional-content'); ?>"
               aria-label="<?php esc_attr_e('Filtrar categorías', 'product-conditional-content'); ?>">
        
        <div class="gdm-<?php echo esc_attr($this->condition_id); ?>-list gdm-condition-list" role="listbox">
            <?php
            $categories = get_terms([
                'taxonomy' => 'product_cat',
                'hide_empty' => false,
                'orderby' => 'name',
                'order' => 'ASC'
            ]);
            
            if ($categories && !is_wp_error($categories)) {
                foreach ($categories as $cat) {
                    $checked = in_array($cat->term_id, $data['objetivo']);
                    ?>
                    <label class="gdm-checkbox-item" role="option">
                        <input type="checkbox" 
                               name="gdm_<?php echo esc_attr($this->condition_id); ?>_objetivo[]" 
                               value="<?php echo esc_attr($cat->term_id); ?>"
                               class="gdm-condition-item-checkbox"
                               <?php checked($checked); ?>>
                        <span><?php echo esc_html($cat->name); ?></span>
                        <span class="gdm-item-count">(<?php echo esc_html($cat->count); ?>)</span>
                    </label>
                    <?php
                }
            }
            ?>
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
        foreach ($data['objetivo'] as $cat_id) {
            $cat = get_term($cat_id, 'product_cat');
            if ($cat && !is_wp_error($cat)) {
                $names[] = $cat->name;
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
        return $count > 0 ? "{$count} seleccionadas" : 'Ninguna seleccionada';
    }
    
    public function matches_product($product_id, $rule_id) {
        $data = $this->get_condition_data($rule_id);
        
        if (empty($data['objetivo'])) {
            return true;
        }
        
        $product_cats = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
        
        return !empty(array_intersect($data['objetivo'], $product_cats));
    }
    
    /**
     * ✅ MEJORA #4: Debounce optimizado + caché de elementos
     */
    protected function render_scripts() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            (function() {
                var searchTimeout;
                var $filter = $('#gdm-<?php echo esc_js($this->condition_id); ?>-filter');
                var $list = $('.gdm-<?php echo esc_js($this->condition_id); ?>-list');
                var $items = $list.find('.gdm-checkbox-item'); // ✅ Cachear elementos
                
                // ✅ Filtro con debounce
                $filter.on('input', function() {
                    clearTimeout(searchTimeout);
                    var search = this.value.toLowerCase();
                    
                    searchTimeout = setTimeout(function() {
                        if (search === '') {
                            $items.show(); // ✅ Optimización para búsqueda vacía
                        } else {
                            $items.each(function() {
                                var $this = $(this);
                                $this.toggle($this.text().toLowerCase().indexOf(search) > -1);
                            });
                        }
                    }, 150); // ✅ Debounce de 150ms
                });
            })();
            
            // Actualizar contador
            $('.gdm-<?php echo esc_js($this->condition_id); ?>-list input').on('change', function() {
                var count = $('.gdm-<?php echo esc_js($this->condition_id); ?>-list input:checked').length;
                $('#gdm-<?php echo esc_js($this->condition_id); ?>-counter').text(
                    count > 0 ? count + ' seleccionadas' : 'Ninguna seleccionada'
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
