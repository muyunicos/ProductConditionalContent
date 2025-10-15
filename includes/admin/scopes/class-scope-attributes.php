<?php
/**
 * Ámbito: Atributos de Productos
 * Filtrar productos por atributos (color, talla, etc.)
 * Compatible con WordPress 6.8.3, PHP 8.2, WooCommerce 10.2.2
 * 
 * @package ProductConditionalContent
 * @since 6.1.0
 * @date 2025-10-15
 */

if (!defined('ABSPATH')) exit;

class GDM_Scope_Attributes extends GDM_Scope_Base {
    
    protected $scope_id = 'atributos';
    protected $scope_name = 'Atributos de Productos';
    protected $scope_icon = '🎨';
    protected $priority = 40;
    
    protected function render_content($post_id, $data) {
        $product_attributes = wc_get_attribute_taxonomies();
        
        if (!$product_attributes) {
            echo '<p class="description">' . __('No hay atributos de producto configurados', 'product-conditional-content') . '</p>';
            return;
        }
        
        ?>
        <div class="gdm-<?php echo esc_attr($this->scope_id); ?>-list">
            <?php foreach ($product_attributes as $attribute): 
                $taxonomy = wc_attribute_taxonomy_name($attribute->attribute_name);
                $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
                
                if (empty($terms) || is_wp_error($terms)) continue;
            ?>
                <div class="gdm-attribute-group">
                    <strong class="gdm-attribute-title">
                        <?php echo esc_html($attribute->attribute_label); ?>:
                    </strong>
                    
                    <div class="gdm-attribute-terms">
                        <?php foreach ($terms as $term): 
                            $checked = isset($data['valores'][$taxonomy]) && in_array($term->term_id, $data['valores'][$taxonomy]);
                        ?>
                            <label class="gdm-checkbox-item gdm-term-item">
                                <input type="checkbox" 
                                       name="gdm_<?php echo esc_attr($this->scope_id); ?>_valores[<?php echo esc_attr($taxonomy); ?>][]" 
                                       value="<?php echo esc_attr($term->term_id); ?>"
                                       class="gdm-scope-item-checkbox"
                                       <?php checked($checked); ?>>
                                <span><?php echo esc_html($term->name); ?></span>
                                <span class="gdm-item-count">(<?php echo esc_html($term->count); ?>)</span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }
    
    public function save($post_id) {
        $valores = isset($_POST["gdm_{$this->scope_id}_valores"]) && is_array($_POST["gdm_{$this->scope_id}_valores"])
            ? $_POST["gdm_{$this->scope_id}_valores"]
            : [];
        
        // Sanitizar valores
        $sanitized = [];
        foreach ($valores as $taxonomy => $term_ids) {
            $sanitized[sanitize_text_field($taxonomy)] = array_map('intval', $term_ids);
        }
        
        $this->save_field($post_id, 'valores', $sanitized);
    }
    
    protected function get_default_data() {
        return ['valores' => []];
    }
    
    protected function has_selection($data) {
        return !empty($data['valores']);
    }
    
    protected function get_summary($data) {
        if (empty($data['valores'])) {
            return '';
        }
        
        $summary = [];
        foreach ($data['valores'] as $taxonomy => $term_ids) {
            $count = count($term_ids);
            $attr_name = wc_attribute_label($taxonomy);
            $summary[] = "{$attr_name} ({$count})";
        }
        
        return implode(', ', $summary);
    }
    
    protected function get_counter_text($data) {
        if (empty($data['valores'])) {
            return 'Ninguno seleccionado';
        }
        
        $total = 0;
        foreach ($data['valores'] as $term_ids) {
            $total += count($term_ids);
        }
        
        return "{$total} valores seleccionados";
    }
    
    public function matches_product($product_id, $rule_id) {
        $data = $this->get_scope_data($rule_id);
        
        if (empty($data['valores'])) {
            return true;
        }
        
        $product = wc_get_product($product_id);
        if (!$product) {
            return false;
        }
        
        // Verificar cada atributo
        foreach ($data['valores'] as $taxonomy => $required_term_ids) {
            $product_terms = wp_get_post_terms($product_id, $taxonomy, ['fields' => 'ids']);
            
            // Si el producto no tiene ninguno de los términos requeridos, no cumple
            if (empty(array_intersect($required_term_ids, $product_terms))) {
                return false;
            }
        }
        
        return true;
    }
    
    protected function render_styles() {
        ?>
        <style>
            .gdm-attribute-group {
                margin-bottom: 20px;
                padding-bottom: 15px;
                border-bottom: 1px solid #e0e0e0;
            }
            .gdm-attribute-group:last-child {
                border-bottom: none;
            }
            .gdm-attribute-title {
                display: block;
                margin-bottom: 10px;
                color: #2271b1;
                font-size: 14px;
            }
            .gdm-attribute-terms {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 8px;
            }
            .gdm-term-item {
                margin: 0 !important;
            }
        </style>
        <?php
    }
    
    protected function render_scripts() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            $('.gdm-<?php echo esc_js($this->scope_id); ?>-list input').on('change', function() {
                var count = $('.gdm-<?php echo esc_js($this->scope_id); ?>-list input:checked').length;
                $('#gdm-<?php echo esc_js($this->scope_id); ?>-counter').text(
                    count > 0 ? count + ' valores seleccionados' : 'Ninguno seleccionado'
                );
            });
        });
        </script>
        <?php
    }
}