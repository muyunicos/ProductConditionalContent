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

class GDM_Condition_Price extends GDM_Condition_Base {
    
    protected $condition_id = 'precio';
    protected $condition_name = 'Rango de Precio';
    protected $condition_icon = '💵';
    protected $priority = 60;
    
    /**
     * ✅ NUEVO: Obtener configuración de moneda de WooCommerce
     */
    private function get_currency_config() {
        return [
            'symbol' => get_woocommerce_currency_symbol(),
            'code' => get_woocommerce_currency(),
            'position' => get_option('woocommerce_currency_pos', 'left'),
            'decimal_separator' => wc_get_price_decimal_separator(),
            'thousand_separator' => wc_get_price_thousand_separator(),
            'decimals' => wc_get_price_decimals(),
        ];
    }
    
    /**
     * ✅ NUEVO: Formatear precio según configuración de WooCommerce
     */
    private function format_price_display($price) {
        return wc_price($price);
    }
    
    /**
     * ✅ NUEVO: Formatear precio para input (sin formato, solo número)
     */
    private function format_price_input($price) {
        return number_format(
            (float) $price,
            wc_get_price_decimals(),
            '.',
            ''
        );
    }
    
    protected function render_content($post_id, $data) {
        $currency = $this->get_currency_config();
        $currency_symbol = $currency['symbol'];
        $currency_position = $currency['position'];
        $decimals = $currency['decimals'];
        
        // ✅ Calcular step dinámico según decimales configurados
        $step = $decimals > 0 ? '0.' . str_repeat('0', $decimals - 1) . '1' : '1';
        ?>
        <div class="gdm-<?php echo esc_attr($this->condition_id); ?>-fields">
            
            <!-- Información de moneda actual -->
            <div class="gdm-currency-info">
                <small class="description">
                    💱 <?php printf(
                        __('Moneda configurada: <strong>%s (%s)</strong>', 'product-conditional-content'),
                        $currency['code'],
                        $currency_symbol
                    ); ?>
                    <?php if ($decimals > 0): ?>
                        | <?php printf(__('Decimales: %d', 'product-conditional-content'), $decimals); ?>
                    <?php endif; ?>
                </small>
            </div>
            
            <div class="gdm-field-group">
                <label><strong><?php _e('Condición:', 'product-conditional-content'); ?></strong></label>
                <select name="gdm_<?php echo esc_attr($this->condition_id); ?>_condicion" 
                        id="gdm-<?php echo esc_attr($this->condition_id); ?>-condicion" 
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
                <div class="gdm-price-input" data-currency-position="<?php echo esc_attr($currency_position); ?>">
                    
                    <?php if (in_array($currency_position, ['left', 'left_space'])): ?>
                        <span class="gdm-currency gdm-currency-left"><?php echo esc_html($currency_symbol); ?></span>
                    <?php endif; ?>
                    
                    <input type="number" 
                           name="gdm_<?php echo esc_attr($this->condition_id); ?>_min" 
                           id="gdm-<?php echo esc_attr($this->condition_id); ?>-min"
                           value="<?php echo esc_attr($this->format_price_input($data['min'])); ?>" 
                           step="<?php echo esc_attr($step); ?>" 
                           min="0"
                           class="regular-text gdm-price-field"
                           placeholder="0<?php echo $decimals > 0 ? $currency['decimal_separator'] . str_repeat('0', $decimals) : ''; ?>">
                    
                    <?php if (in_array($currency_position, ['right', 'right_space'])): ?>
                        <span class="gdm-currency gdm-currency-right"><?php echo esc_html($currency_symbol); ?></span>
                    <?php endif; ?>
                </div>
                
                <!-- ✅ Preview del valor formateado -->
                <p class="description gdm-price-preview">
                    <?php _e('Vista previa:', 'product-conditional-content'); ?> 
                    <strong class="gdm-preview-min"><?php echo $data['min'] > 0 ? $this->format_price_display($data['min']) : '-'; ?></strong>
                </p>
            </div>
            
            <div class="gdm-field-group gdm-max-wrapper" style="<?php echo $data['condicion'] !== 'entre' ? 'display:none;' : ''; ?>">
                <label><strong><?php _e('Valor máximo:', 'product-conditional-content'); ?></strong></label>
                <div class="gdm-price-input" data-currency-position="<?php echo esc_attr($currency_position); ?>">
                    
                    <?php if (in_array($currency_position, ['left', 'left_space'])): ?>
                        <span class="gdm-currency gdm-currency-left"><?php echo esc_html($currency_symbol); ?></span>
                    <?php endif; ?>
                    
                    <input type="number" 
                           name="gdm_<?php echo esc_attr($this->condition_id); ?>_max" 
                           id="gdm-<?php echo esc_attr($this->condition_id); ?>-max"
                           value="<?php echo esc_attr($this->format_price_input($data['max'])); ?>" 
                           step="<?php echo esc_attr($step); ?>" 
                           min="0"
                           class="regular-text gdm-price-field"
                           placeholder="0<?php echo $decimals > 0 ? $currency['decimal_separator'] . str_repeat('0', $decimals) : ''; ?>">
                    
                    <?php if (in_array($currency_position, ['right', 'right_space'])): ?>
                        <span class="gdm-currency gdm-currency-right"><?php echo esc_html($currency_symbol); ?></span>
                    <?php endif; ?>
                </div>
                
                <!-- ✅ Preview del valor formateado -->
                <p class="description gdm-price-preview">
                    <?php _e('Vista previa:', 'product-conditional-content'); ?> 
                    <strong class="gdm-preview-max"><?php echo $data['max'] > 0 ? $this->format_price_display($data['max']) : '-'; ?></strong>
                </p>
            </div>
            
        </div>
        <?php
    }
    
    public function save($post_id) {
        // ✅ Sanitizar precios respetando configuración de WooCommerce
        $min = isset($_POST["gdm_{$this->condition_id}_min"]) 
            ? wc_format_decimal($_POST["gdm_{$this->condition_id}_min"]) 
            : 0;
        
        $max = isset($_POST["gdm_{$this->condition_id}_max"]) 
            ? wc_format_decimal($_POST["gdm_{$this->condition_id}_max"]) 
            : 0;
        
        $this->save_field($post_id, 'condicion', isset($_POST["gdm_{$this->condition_id}_condicion"]) ? sanitize_text_field($_POST["gdm_{$this->condition_id}_condicion"]) : 'mayor_que');
        $this->save_field($post_id, 'min', $min);
        $this->save_field($post_id, 'max', $max);
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
        
        $conditions = [
            'mayor_que' => sprintf(
                __('Mayor que %s', 'product-conditional-content'),
                $this->format_price_display($data['min'])
            ),
            'menor_que' => sprintf(
                __('Menor que %s', 'product-conditional-content'),
                $this->format_price_display($data['min'])
            ),
            'entre' => sprintf(
                __('Entre %s y %s', 'product-conditional-content'),
                $this->format_price_display($data['min']),
                $this->format_price_display($data['max'])
            ),
            'igual_a' => sprintf(
                __('Igual a %s', 'product-conditional-content'),
                $this->format_price_display($data['min'])
            ),
        ];
        
        return $conditions[$data['condicion']] ?? '';
    }
    
    protected function get_counter_text($data) {
        return $this->has_selection($data) ? 'Configurado' : 'Sin configurar';
    }
    
    /**
     * ✅ FIX #2: Soporte para productos variables y agrupados
     * ✅ MEJORA: Usar funciones de WooCommerce para obtener precios
     */
    public function matches_product($product_id, $rule_id) {
        $data = $this->get_condition_data($rule_id);
        
        if (!$this->has_selection($data)) {
            return true;
        }
        
        $product = wc_get_product($product_id);
        if (!$product) {
            return false;
        }
        
        // ✅ Obtener precio según tipo de producto usando API de WooCommerce
        if ($product->is_type('variable')) {
            // Para productos variables, usar precio mínimo
            $price = (float) $product->get_variation_price('min', true); // true = incluye impuestos si está configurado
        } elseif ($product->is_type('grouped')) {
            // Para productos agrupados, usar precio mínimo de hijos
            $children = $product->get_children();
            $prices = [];
            foreach ($children as $child_id) {
                $child = wc_get_product($child_id);
                if ($child) {
                    $prices[] = (float) $child->get_price();
                }
            }
            $price = !empty($prices) ? min($prices) : 0;
        } else {
            // Para productos simples, usar precio actual (con descuento si aplica)
            $price = (float) $product->get_price();
        }
        
        // Validar precio vacío
        if ($price <= 0) {
            return false;
        }
        
        // ✅ Convertir valores guardados a float para comparación
        $min = (float) $data['min'];
        $max = (float) $data['max'];
        
        // ✅ Tolerancia para comparación de decimales según configuración
        $decimals = wc_get_price_decimals();
        $tolerance = $decimals > 0 ? pow(0.1, $decimals) : 0.01;
        
        switch ($data['condicion']) {
            case 'mayor_que':
                return $price > $min;
                
            case 'menor_que':
                return $price < $min;
                
            case 'entre':
                return $price >= $min && $price <= $max;
                
            case 'igual_a':
                return abs($price - $min) < $tolerance;
                
            default:
                return true;
        }
    }
    
    protected function render_styles() {
        $currency = $this->get_currency_config();
        ?>
        <style>
            .gdm-<?php echo esc_attr($this->condition_id); ?>-fields {
                display: flex;
                flex-direction: column;
                gap: 15px;
            }
            
            /* Información de moneda */
            .gdm-currency-info {
                padding: 10px 12px;
                background: #e8f0fe;
                border-left: 3px solid #2271b1;
                border-radius: 3px;
                margin-bottom: 5px;
            }
            .gdm-currency-info small {
                color: #135e96;
                font-size: 12px;
            }
            
            /* Container de input con moneda */
            .gdm-price-input {
                display: flex;
                align-items: center;
                gap: <?php echo in_array($currency['position'], ['left_space', 'right_space']) ? '8px' : '4px'; ?>;
            }
            
            .gdm-price-field {
                flex: 1;
                text-align: right;
                font-family: 'Courier New', monospace;
                font-size: 14px;
            }
            
            .gdm-currency {
                font-weight: 600;
                color: #2271b1;
                font-size: 16px;
                flex-shrink: 0;
            }
            
            /* Posiciones de moneda */
            .gdm-currency-left {
                order: -1;
            }
            
            .gdm-currency-right {
                order: 1;
            }
            
            /* Preview formateado */
            .gdm-price-preview {
                margin-top: 5px !important;
                font-size: 12px;
            }
            .gdm-price-preview strong {
                color: #00a32a;
                font-family: 'Courier New', monospace;
            }
        </style>
        <?php
    }
    
    protected function render_scripts() {
        $currency = $this->get_currency_config();
        ?>
        <script>
        jQuery(document).ready(function($) {
            var currencyConfig = <?php echo json_encode($currency); ?>;
            
            // ✅ Toggle de campo máximo
            $('#gdm-<?php echo esc_js($this->condition_id); ?>-condicion').on('change', function() {
                if ($(this).val() === 'entre') {
                    $('.gdm-max-wrapper').slideDown();
                } else {
                    $('.gdm-max-wrapper').slideUp();
                }
            });
            
            // ✅ NUEVO: Preview en tiempo real con formato WooCommerce
            function formatPrice(amount) {
                if (!amount || amount == 0) return '-';
                
                var decimals = currencyConfig.decimals;
                var decimalSep = currencyConfig.decimal_separator;
                var thousandSep = currencyConfig.thousand_separator;
                var symbol = currencyConfig.symbol;
                var position = currencyConfig.position;
                
                // Formatear número
                var parts = parseFloat(amount).toFixed(decimals).split('.');
                var integerPart = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousandSep);
                var formatted = decimals > 0 ? integerPart + decimalSep + parts[1] : integerPart;
                
                // Aplicar posición de símbolo
                switch(position) {
                    case 'left':
                        return symbol + formatted;
                    case 'right':
                        return formatted + symbol;
                    case 'left_space':
                        return symbol + ' ' + formatted;
                    case 'right_space':
                        return formatted + ' ' + symbol;
                    default:
                        return symbol + formatted;
                }
            }
            
            // Actualizar preview al cambiar valores
            $('#gdm-<?php echo esc_js($this->condition_id); ?>-min').on('input', function() {
                var preview = formatPrice($(this).val());
                $('.gdm-preview-min').text(preview);
            });
            
            $('#gdm-<?php echo esc_js($this->condition_id); ?>-max').on('input', function() {
                var preview = formatPrice($(this).val());
                $('.gdm-preview-max').text(preview);
            });
            
            // ✅ Validación: máximo debe ser mayor que mínimo
            $('.gdm-condition-save[data-target="<?php echo esc_js($this->condition_id); ?>"]').on('click', function(e) {
                var condicion = $('#gdm-<?php echo esc_js($this->condition_id); ?>-condicion').val();
                
                if (condicion === 'entre') {
                    var min = parseFloat($('#gdm-<?php echo esc_js($this->condition_id); ?>-min').val()) || 0;
                    var max = parseFloat($('#gdm-<?php echo esc_js($this->condition_id); ?>-max').val()) || 0;
                    
                    if (max > 0 && max <= min) {
                        e.preventDefault();
                        e.stopImmediatePropagation();
                        alert('⚠️ El valor máximo debe ser mayor que el mínimo.');
                        $('#gdm-<?php echo esc_js($this->condition_id); ?>-max').focus();
                        return false;
                    }
                }
            });
        });
        </script>
        <?php
    }
}