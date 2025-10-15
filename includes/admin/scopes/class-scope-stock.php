<?php
/**
 * Ámbito: Estado de Stock
 * Filtrar productos por estado de stock
 * Compatible con WordPress 6.8.3, PHP 8.2, WooCommerce 10.2.2
 * 
 * @package ProductConditionalContent
 * @since 6.1.0
 * @date 2025-10-15
 */

if (!defined('ABSPATH')) exit;

class GDM_Scope_Stock extends GDM_Scope_Base {
    
    protected $scope_id = 'stock';
    protected $scope_name = 'Estado de Stock';
    protected $scope_icon = '📦';
    protected $priority = 50;
    
    protected function render_content($post_id, $data) {
        ?>
        <div class="gdm-<?php echo esc_attr($this->scope_id); ?>-options">
            <label class="gdm-checkbox-item">
                <input type="checkbox" 
                       name="gdm_<?php echo esc_attr($this->scope_id); ?>_status[]" 
                       value="instock"
                       class="gdm-scope-item-checkbox"
                       <?php checked(in_array('instock', $data['status'])); ?>>
                <span class="gdm-status-badge gdm-status-instock">✅ En Stock</span>
            </label>
            
            <label class="gdm-checkbox-item">
                <input type="checkbox" 
                       name="gdm_<?php echo esc_attr($this->scope_id); ?>_status[]" 
                       value="outofstock"
                       class="gdm-scope-item-checkbox"
                       <?php checked(in_array('outofstock', $data['status'])); ?>>
                <span class="gdm-status-badge gdm-status-outofstock">❌ Sin Stock</span>
            </label>
            
            <label class="gdm-checkbox-item">
                <input type="checkbox" 
                       name="gdm_<?php echo esc_attr($this->scope_id); ?>_status[]" 
                       value="onbackorder"
                       class="gdm-scope-item-checkbox"
                       <?php checked(in_array('onbackorder', $data['status'])); ?>>
                <span class="gdm-status-badge gdm-status-backorder">⏳ Pedido Pendiente</span>
            </label>
        </div>
        <?php
    }
    
    public function save($post_id) {
        $status = isset($_POST["gdm_{$this->scope_id}_status"]) 
            ? array_map('sanitize_text_field', $_POST["gdm_{$this->scope_id}_status"]) 
            : [];
        
        $this->save_field($post_id, 'status', $status);
    }
    
    protected function get_default_data() {
        return ['status' => []];
    }
    
    protected function has_selection($data) {
        return !empty($data['status']);
    }
    
    protected function get_summary($data) {
        if (empty($data['status'])) {
            return '';
        }
        
        $labels = [
            'instock' => '✅ En Stock',
            'outofstock' => '❌ Sin Stock',
            'onbackorder' => '⏳ Pedido Pendiente',
        ];
        
        $selected = array_map(function($status) use ($labels) {
            return $labels[$status] ?? $status;
        }, $data['status']);
        
        return implode(', ', $selected);
    }
    
    protected function get_counter_text($data) {
        $count = count($data['status']);
        return $count > 0 ? "{$count} estados seleccionados" : 'Ninguno seleccionado';
    }
    
    public function matches_product($product_id, $rule_id) {
        $data = $this->get_scope_data($rule_id);
        
        if (empty($data['status'])) {
            return true;
        }
        
        $product = wc_get_product($product_id);
        if (!$product) {
            return false;
        }
        
        return in_array($product->get_stock_status(), $data['status']);
    }
    
    protected function render_styles() {
        ?>
        <style>
            .gdm-<?php echo esc_attr($this->scope_id); ?>-options {
                display: flex;
                flex-direction: column;
                gap: 10px;
            }
            .gdm-status-badge {
                display: inline-flex;
                align-items: center;
                padding: 6px 12px;
                border-radius: 4px;
                font-weight: 500;
                font-size: 13px;
            }
            .gdm-status-instock {
                background: #d4edda;
                color: #155724;
            }
            .gdm-status-outofstock {
                background: #f8d7da;
                color: #721c24;
            }
            .gdm-status-backorder {
                background: #fff3cd;
                color: #856404;
            }
        </style>
        <?php
    }
    
    protected function render_scripts() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            $('.gdm-<?php echo esc_js($this->scope_id); ?>-options input').on('change', function() {
                var count = $('.gdm-<?php echo esc_js($this->scope_id); ?>-options input:checked').length;
                $('#gdm-<?php echo esc_js($this->scope_id); ?>-counter').text(
                    count > 0 ? count + ' estados seleccionados' : 'Ninguno seleccionado'
                );
            });
        });
        </script>
        <?php
    }
}