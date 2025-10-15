<?php
/**
 * Ámbito: Rango de Precio
 * Filtrar productos por rango de precio
 * Compatible con WordPress 6.8.3, PHP 8.2, WooCommerce 10.2.2
 * 
 * @package ProductConditionalContent
 * @since 6.1.0
 * @date 2025-10-15
 */

if (!defined('ABSPATH')) exit;

class GDM_Scope_Price extends GDM_Scope_Base {
    
    protected $scope_id = 'precio';
    protected $scope_name = 'Rango de Precio';
    protected $scope_icon = '💵';
    protected $priority = 60;
    
    protected function render_content($post_id, $data) {
        $currency = get_woocommerce_currency_symbol();
        ?>
        <div class="gdm-<?php echo esc_attr($this->scope_id); ?>-fields">
            <div class="gdm-field-group">
                <label><strong><?php _e('Condición:', 'product-conditional-content'); ?></strong></label>
                <select name="gdm_<?php echo esc_attr($this->scope_id); ?>_condicion" 
                        id="gdm-<?php echo esc_attr($this->scope_id); ?>-condicion" 
                        class="regular-text">
                    <option value="mayor_que" <?php selected($data['condicion'], 'mayor_que'); ?>>
                        <?php _e('Mayor que', 'product-conditional-content'); ?>
                    </option>
                    <option value="menor_que" <?php selected($data['condicion'], 'menor_que'); ?>>
                        <?php _e('Menor que', 'product-conditional-content'); ?>
                    </option>
                    <option value="entre" <?php selected($data['condicion'], 'entre'); ?>>
                        <?php _e('Entre', 'product-conditional-content'); ?>
                    </option>
                    <option value="igual_a" <?php selected($data['condicion'], 'igual_a'); ?>>
                        <?php _e('Igual a', 'product-conditional-content'); ?>
                    </option>
                </select>
            </div>
            
            <div class="gdm-field-group">
                <label><strong><?php _e('Valor mínimo:', 'product-conditional-content'); ?></strong></label>
                <div class="gdm-price-input">
                    <input type="number" 
                           name="gdm_<?php echo esc_attr($this->scope_id); ?>_min" 
                           value="<?php echo esc_attr($data['min']); ?>" 
                           step="0.01" 
                           min="0"
                           class="regular-text">
                    <span class="gdm-currency"><?php echo esc_html($currency); ?></span>
                </div>
            </div>
            
            <div class="gdm-field-group gdm-max-wrapper" style="<?php echo $data['condicion'] !== 'entre' ? 'display:none;' : ''; ?>">
                <label><strong><?php _e('Valor máximo:', 'product-conditional-content'); ?></strong></label>
                <div class="gdm-price-input">
                    <input type="number" 
                           name="gdm_<?php echo esc_attr($this->scope_id); ?>_max" 
                           value="<?php echo esc_attr($data['max']); ?>" 
                           step="0.01" 
                           min="0"
                           class="regular-text">
                    <span class="gdm-currency"><?php echo esc_html($currency); ?></span>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function save($post_id) {
        $this->save_field($post_id, 'condicion', isset($_POST["gdm_{$this->scope_id}_condicion"]) ? sanitize_text_field($_POST["gdm_{$this->scope_id}_condicion"]) : 'mayor_que');
        $this->save_field($post_id, 'min', isset($_POST["gdm_{$this->scope_id}_min"]) ? floatval($_POST["gdm_{$this->scope_id}_min"]) : 0);
        $this->save_field($post_id, 'max', isset($_POST["gdm_{$this->scope_id}_max"]) ? floatval($_POST["gdm_{$this->scope_id}_max"]) : 0);
    }
    
    protected function get_default_data() {
        return [
            'condicion' => 'mayor_que',
            'min' => 0,
            'max' => 0,
        ];
    }
    
    protected function has_selection($data) {
        return !empty($data['min']) || !empty($data['max']);
    }
    
    protected function get_summary($data) {
        if (!$this->has_selection($data)) {
            return '';
        }
        
        $currency = get_woocommerce_currency_symbol();
        $conditions = [
            'mayor_que' => sprintf(__('Mayor que %s%s', 'product-conditional-content'), $currency, number_format($data['min'], 2)),
            'menor_que' => sprintf(__('Menor que %s%s', 'product-conditional-content'), $currency, number_format($data['min'], 2)),
            'entre' => sprintf(__('Entre %s%s y %s%s', 'product-conditional-content'), $currency, number_format($data['min'], 2), $currency, number_format($data['max'], 2)),
            'igual_a' => sprintf(__('Igual a %s%s', 'product-conditional-content'), $currency, number_format($data['min'], 2)),
        ];
        
        return $conditions[$data['condicion']] ?? '';
    }
    
    protected function get_counter_text($data) {
        return $this->has_selection($data) ? 'Configurado' : 'Sin configurar';
    }
    
    public function matches_product($product_id, $rule_id) {
        $data = $this->get_scope_data($rule_id);
        
        if (!$this->has_selection($data)) {
            return true;
        }
        
        $product = wc_get_product($product_id);
        if (!$product) {
            return false;
        }
        
        $price = floatval($product->get_price());
        
        switch ($data['condicion']) {
            case 'mayor_que':
                return $price > $data['min'];
            case 'menor_que':
                return $price < $data['min'];
            case 'entre':
                return $price >= $data['min'] && $price <= $data['max'];
            case 'igual_a':
                return abs($price - $data['min']) < 0.01;
            default:
                return true;
        }
    }
    
    protected function render_styles() {
        ?>
        <style>
            .gdm-<?php echo esc_attr($this->scope_id); ?>-fields {
                display: flex;
                flex-direction: column;
                gap: 15px;
            }
            .gdm-price-input {
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .gdm-currency {
                font-weight: 600;
                color: #2271b1;
            }
        </style>
        <?php
    }
    
    protected function render_scripts() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            $('#gdm-<?php echo esc_js($this->scope_id); ?>-condicion').on('change', function() {
                if ($(this).val() === 'entre') {
                    $('.gdm-max-wrapper').slideDown();
                } else {
                    $('.gdm-max-wrapper').slideUp();
                }
            });
        });
        </script>
        <?php
    }
}