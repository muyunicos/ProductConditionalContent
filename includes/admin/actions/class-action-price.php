<?php
/**
 * Acción de Precio v7.0
 * Permite modificar el precio de productos con descuentos o adicionales
 * Compatible con WordPress 6.8.3, PHP 8.2, WooCommerce 10.2.2
 *
 * @package ProductConditionalContent
 * @since 7.0.0
 * @date 2025-10-16
 */

if (!defined('ABSPATH')) exit;

class GDM_Action_Price extends GDM_Action_Base {

    protected $action_id = 'price';
    protected $action_name = 'Modificador de Precio';
    protected $action_icon = '💰';
    protected $action_description = 'Aplica descuentos, incrementos o precios fijos con reglas de redondeo';
    protected $priority = 15;
    protected $supported_contexts = ['products', 'wc_cart'];

    /**
     * Obtener opciones por defecto
     */
    protected function get_default_options() {
        return [
            'tipo' => 'descuento_porcentaje',
            'valor' => 0,
            'aplicar_a' => 'activo',
            'redondeo' => 'ninguno',
            'mostrar_antes' => false,
            'badge_descuento' => false,
        ];
    }

    /**
     * Renderizar opciones del módulo
     */
    protected function render_options($rule_id, $options) {
        ?>
        <div class="gdm-price-options">

            <!-- Tipo de modificación -->
            <div class="gdm-field-row">
                <label class="gdm-field-label">
                    <strong><?php _e('⚙️ Tipo de Modificación', 'product-conditional-content'); ?></strong>
                </label>
                <select name="gdm_actions[price][options][tipo]"
                        id="gdm_action_price_tipo"
                        class="gdm-field-control">
                    <option value="descuento_porcentaje" <?php selected($options['tipo'], 'descuento_porcentaje'); ?>>
                        <?php _e('Descuento en Porcentaje (%)', 'product-conditional-content'); ?>
                    </option>
                    <option value="descuento_fijo" <?php selected($options['tipo'], 'descuento_fijo'); ?>>
                        <?php _e('Descuento Fijo (monto)', 'product-conditional-content'); ?>
                    </option>
                    <option value="adicional_porcentaje" <?php selected($options['tipo'], 'adicional_porcentaje'); ?>>
                        <?php _e('Adicional en Porcentaje (%)', 'product-conditional-content'); ?>
                    </option>
                    <option value="adicional_fijo" <?php selected($options['tipo'], 'adicional_fijo'); ?>>
                        <?php _e('Adicional Fijo (monto)', 'product-conditional-content'); ?>
                    </option>
                    <option value="precio_fijo" <?php selected($options['tipo'], 'precio_fijo'); ?>>
                        <?php _e('Establecer Precio Fijo', 'product-conditional-content'); ?>
                    </option>
                </select>
            </div>

            <!-- Valor del cambio -->
            <div class="gdm-field-row">
                <label class="gdm-field-label">
                    <strong id="gdm_action_price_valor_label">
                        <?php
                        if (strpos($options['tipo'], 'porcentaje') !== false) {
                            _e('💯 Porcentaje', 'product-conditional-content');
                        } elseif ($options['tipo'] === 'precio_fijo') {
                            _e('💵 Precio Fijo', 'product-conditional-content');
                        } else {
                            _e('💵 Monto', 'product-conditional-content');
                        }
                        ?>
                    </strong>
                </label>
                <div class="gdm-field-with-suffix">
                    <input type="number"
                           name="gdm_actions[price][options][valor]"
                           id="gdm_action_price_valor"
                           value="<?php echo esc_attr($options['valor']); ?>"
                           min="0"
                           step="0.01"
                           class="gdm-field-control">
                    <span class="gdm-field-suffix" id="gdm_action_price_simbolo">
                        <?php echo strpos($options['tipo'], 'porcentaje') !== false ? '%' : get_woocommerce_currency_symbol(); ?>
                    </span>
                </div>
                <p class="gdm-field-help" id="gdm_action_price_descripcion">
                    <?php echo esc_html($this->get_description_by_type($options['tipo'])); ?>
                </p>
            </div>

            <!-- Aplicar a precio regular o de oferta -->
            <div class="gdm-field-row">
                <label class="gdm-field-label">
                    <strong><?php _e('🎯 Aplicar a', 'product-conditional-content'); ?></strong>
                </label>
                <select name="gdm_actions[price][options][aplicar_a]" class="gdm-field-control">
                    <option value="regular" <?php selected($options['aplicar_a'], 'regular'); ?>>
                        <?php _e('Precio Regular', 'product-conditional-content'); ?>
                    </option>
                    <option value="sale" <?php selected($options['aplicar_a'], 'sale'); ?>>
                        <?php _e('Precio de Oferta (si existe)', 'product-conditional-content'); ?>
                    </option>
                    <option value="ambos" <?php selected($options['aplicar_a'], 'ambos'); ?>>
                        <?php _e('Ambos Precios', 'product-conditional-content'); ?>
                    </option>
                    <option value="activo" <?php selected($options['aplicar_a'], 'activo'); ?>>
                        <?php _e('Precio Activo (el que se muestra)', 'product-conditional-content'); ?>
                    </option>
                </select>
            </div>

            <!-- Redondeo -->
            <div class="gdm-field-row">
                <label class="gdm-field-label">
                    <strong><?php _e('🔢 Redondeo del Precio', 'product-conditional-content'); ?></strong>
                </label>
                <select name="gdm_actions[price][options][redondeo]" class="gdm-field-control">
                    <option value="ninguno" <?php selected($options['redondeo'], 'ninguno'); ?>>
                        <?php _e('Sin redondeo', 'product-conditional-content'); ?>
                    </option>
                    <option value="arriba" <?php selected($options['redondeo'], 'arriba'); ?>>
                        <?php _e('Redondear hacia arriba', 'product-conditional-content'); ?>
                    </option>
                    <option value="abajo" <?php selected($options['redondeo'], 'abajo'); ?>>
                        <?php _e('Redondear hacia abajo', 'product-conditional-content'); ?>
                    </option>
                    <option value="cercano" <?php selected($options['redondeo'], 'cercano'); ?>>
                        <?php _e('Redondear al más cercano', 'product-conditional-content'); ?>
                    </option>
                    <option value="terminacion_99" <?php selected($options['redondeo'], 'terminacion_99'); ?>>
                        <?php _e('Terminar en .99', 'product-conditional-content'); ?>
                    </option>
                    <option value="terminacion_95" <?php selected($options['redondeo'], 'terminacion_95'); ?>>
                        <?php _e('Terminar en .95', 'product-conditional-content'); ?>
                    </option>
                    <option value="terminacion_00" <?php selected($options['redondeo'], 'terminacion_00'); ?>>
                        <?php _e('Terminar en .00', 'product-conditional-content'); ?>
                    </option>
                </select>
            </div>

            <hr class="gdm-field-separator">

            <!-- Opciones avanzadas -->
            <div class="gdm-field-row">
                <label class="gdm-checkbox-label">
                    <input type="checkbox"
                           name="gdm_actions[price][options][mostrar_antes]"
                           value="1"
                           <?php checked($options['mostrar_antes'], true); ?>>
                    <strong><?php _e('💸 Mostrar precio anterior tachado', 'product-conditional-content'); ?></strong>
                </label>
                <p class="gdm-field-help">
                    <?php _e('Muestra el precio original tachado junto al nuevo precio (solo para descuentos)', 'product-conditional-content'); ?>
                </p>
            </div>

            <div class="gdm-field-row">
                <label class="gdm-checkbox-label">
                    <input type="checkbox"
                           name="gdm_actions[price][options][badge_descuento]"
                           value="1"
                           <?php checked($options['badge_descuento'], true); ?>>
                    <strong><?php _e('🏷️ Mostrar badge de descuento', 'product-conditional-content'); ?></strong>
                </label>
                <p class="gdm-field-help">
                    <?php _e('Agrega un badge visual con el porcentaje de descuento (ej: "20% OFF")', 'product-conditional-content'); ?>
                </p>
            </div>

            <hr class="gdm-field-separator">

            <!-- Calculadora de precios -->
            <div class="gdm-field-row">
                <label class="gdm-field-label">
                    <strong><?php _e('🧮 Calculadora de Precios', 'product-conditional-content'); ?></strong>
                </label>
                <div class="gdm-price-calculator">
                    <div class="gdm-calc-input-group">
                        <label><?php _e('Precio Original:', 'product-conditional-content'); ?></label>
                        <input type="number"
                               id="gdm_action_price_calc_original"
                               value="100.00"
                               step="0.01"
                               class="gdm-field-control">
                    </div>
                    <button type="button" class="button" id="gdm_action_price_calc_btn">
                        <span class="dashicons dashicons-calculator"></span>
                        <?php _e('Calcular', 'product-conditional-content'); ?>
                    </button>
                    <div id="gdm_action_price_calc_resultado" class="gdm-calc-result" style="display:none;">
                        <div class="gdm-calc-nuevo" id="gdm_action_price_calc_nuevo"></div>
                        <div class="gdm-calc-detalle" id="gdm_action_price_calc_detalle"></div>
                    </div>
                </div>
            </div>

        </div>
        <?php
    }

    /**
     * Obtener descripción según tipo
     */
    private function get_description_by_type($tipo) {
        $descriptions = [
            'descuento_porcentaje' => __('Ejemplo: 20 para aplicar 20% de descuento', 'product-conditional-content'),
            'descuento_fijo' => __('Ejemplo: 50 para descontar 50 del precio', 'product-conditional-content'),
            'adicional_porcentaje' => __('Ejemplo: 15 para incrementar el precio en 15%', 'product-conditional-content'),
            'adicional_fijo' => __('Ejemplo: 30 para agregar 30 al precio', 'product-conditional-content'),
            'precio_fijo' => __('El precio del producto será exactamente este valor', 'product-conditional-content'),
        ];

        return $descriptions[$tipo] ?? '';
    }

    /**
     * Sanitizar opciones
     */
    protected function sanitize_options($post_data) {
        $options = isset($post_data['options']) ? $post_data['options'] : [];

        return [
            'tipo' => $this->sanitize_text($options['tipo'] ?? 'descuento_porcentaje'),
            'valor' => $this->sanitize_decimal($options['valor'] ?? 0, 0.0),
            'aplicar_a' => $this->sanitize_text($options['aplicar_a'] ?? 'activo'),
            'redondeo' => $this->sanitize_text($options['redondeo'] ?? 'ninguno'),
            'mostrar_antes' => !empty($options['mostrar_antes']),
            'badge_descuento' => !empty($options['badge_descuento']),
        ];
    }

    /**
     * Generar código ejecutable
     */
    protected function generate_execution_code($options) {
        $tipo = $options['tipo'];
        $valor = $options['valor'];
        $redondeo = $options['redondeo'];

        // Generar código PHP como string para ejecutar dinámicamente
        $code = "
        \$product = wc_get_product(\$object_id);
        if (!\$product) return false;

        \$precio_original = (float) \$product->get_price();
        \$nuevo_precio = \$precio_original;

        // Aplicar modificación
        switch ('{$tipo}') {
            case 'descuento_porcentaje':
                \$nuevo_precio = \$precio_original - (\$precio_original * {$valor} / 100);
                break;
            case 'descuento_fijo':
                \$nuevo_precio = \$precio_original - {$valor};
                break;
            case 'adicional_porcentaje':
                \$nuevo_precio = \$precio_original + (\$precio_original * {$valor} / 100);
                break;
            case 'adicional_fijo':
                \$nuevo_precio = \$precio_original + {$valor};
                break;
            case 'precio_fijo':
                \$nuevo_precio = {$valor};
                break;
        }

        // Aplicar redondeo
        switch ('{$redondeo}') {
            case 'arriba':
                \$nuevo_precio = ceil(\$nuevo_precio);
                break;
            case 'abajo':
                \$nuevo_precio = floor(\$nuevo_precio);
                break;
            case 'cercano':
                \$nuevo_precio = round(\$nuevo_precio);
                break;
            case 'terminacion_99':
                \$nuevo_precio = floor(\$nuevo_precio) + 0.99;
                break;
            case 'terminacion_95':
                \$nuevo_precio = floor(\$nuevo_precio) + 0.95;
                break;
            case 'terminacion_00':
                \$nuevo_precio = round(\$nuevo_precio);
                break;
        }

        // Asegurar que el precio no sea negativo
        \$nuevo_precio = max(0, \$nuevo_precio);

        return \$nuevo_precio;
        ";

        return $code;
    }

    /**
     * Renderizar scripts específicos
     */
    protected function render_scripts() {
        $currency_symbol = get_woocommerce_currency_symbol();
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Actualizar labels y símbolos según tipo
            $('#gdm_action_price_tipo').on('change', function() {
                var tipo = $(this).val();
                var esPorcentaje = tipo.indexOf('porcentaje') !== -1;
                var esFijo = tipo === 'precio_fijo';

                // Actualizar símbolo
                if (esPorcentaje) {
                    $('#gdm_action_price_simbolo').text('%');
                    $('#gdm_action_price_valor_label').text('💯 Porcentaje');
                } else if (esFijo) {
                    $('#gdm_action_price_simbolo').text('<?php echo $currency_symbol; ?>');
                    $('#gdm_action_price_valor_label').text('💵 Precio Fijo');
                } else {
                    $('#gdm_action_price_simbolo').text('<?php echo $currency_symbol; ?>');
                    $('#gdm_action_price_valor_label').text('💵 Monto');
                }

                // Actualizar descripción
                var descripciones = {
                    'descuento_porcentaje': <?php echo json_encode(__('Ejemplo: 20 para aplicar 20% de descuento', 'product-conditional-content')); ?>,
                    'descuento_fijo': <?php echo json_encode(__('Ejemplo: 50 para descontar 50 del precio', 'product-conditional-content')); ?>,
                    'adicional_porcentaje': <?php echo json_encode(__('Ejemplo: 15 para incrementar el precio en 15%', 'product-conditional-content')); ?>,
                    'adicional_fijo': <?php echo json_encode(__('Ejemplo: 30 para agregar 30 al precio', 'product-conditional-content')); ?>,
                    'precio_fijo': <?php echo json_encode(__('El precio del producto será exactamente este valor', 'product-conditional-content')); ?>
                };

                $('#gdm_action_price_descripcion').text(descripciones[tipo] || '');
            });

            // Calculadora
            $('#gdm_action_price_calc_btn').on('click', function() {
                var original = parseFloat($('#gdm_action_price_calc_original').val()) || 100;
                var tipo = $('#gdm_action_price_tipo').val();
                var valor = parseFloat($('#gdm_action_price_valor').val()) || 0;
                var redondeo = $('[name="gdm_actions[price][options][redondeo]"]').val();
                var nuevo = original;
                var detalle = '';

                // Calcular nuevo precio
                switch(tipo) {
                    case 'descuento_porcentaje':
                        nuevo = original - (original * valor / 100);
                        detalle = original + ' - ' + valor + '% = ';
                        break;
                    case 'descuento_fijo':
                        nuevo = original - valor;
                        detalle = original + ' - ' + valor + ' = ';
                        break;
                    case 'adicional_porcentaje':
                        nuevo = original + (original * valor / 100);
                        detalle = original + ' + ' + valor + '% = ';
                        break;
                    case 'adicional_fijo':
                        nuevo = original + valor;
                        detalle = original + ' + ' + valor + ' = ';
                        break;
                    case 'precio_fijo':
                        nuevo = valor;
                        detalle = <?php echo json_encode(__('Precio fijo: ', 'product-conditional-content')); ?>;
                        break;
                }

                // Aplicar redondeo
                switch(redondeo) {
                    case 'arriba':
                        nuevo = Math.ceil(nuevo);
                        break;
                    case 'abajo':
                        nuevo = Math.floor(nuevo);
                        break;
                    case 'cercano':
                        nuevo = Math.round(nuevo);
                        break;
                    case 'terminacion_99':
                        nuevo = Math.floor(nuevo) + 0.99;
                        break;
                    case 'terminacion_95':
                        nuevo = Math.floor(nuevo) + 0.95;
                        break;
                    case 'terminacion_00':
                        nuevo = Math.round(nuevo);
                        break;
                }

                // Asegurar no negativo
                nuevo = Math.max(0, nuevo);

                // Mostrar resultado
                $('#gdm_action_price_calc_nuevo').text('<?php echo $currency_symbol; ?>' + nuevo.toFixed(2));
                $('#gdm_action_price_calc_detalle').text(detalle + nuevo.toFixed(2));
                $('#gdm_action_price_calc_resultado').slideDown();
            });

            // Auto-calcular al cambiar valores
            $('#gdm_action_price_tipo, #gdm_action_price_valor, [name="gdm_actions[price][options][redondeo]"]').on('change', function() {
                if ($('#gdm_action_price_calc_resultado').is(':visible')) {
                    $('#gdm_action_price_calc_btn').click();
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Renderizar estilos específicos
     */
    protected function render_styles() {
        ?>
        <style>
            .gdm-price-options .gdm-field-row {
                margin-bottom: 20px;
            }

            .gdm-price-options .gdm-field-label {
                display: block;
                margin-bottom: 8px;
            }

            .gdm-price-options .gdm-field-control {
                width: 100%;
                max-width: 400px;
            }

            .gdm-price-options .gdm-field-with-suffix {
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .gdm-price-options .gdm-field-suffix {
                font-weight: 600;
                color: #2271b1;
                min-width: 30px;
            }

            .gdm-price-options .gdm-field-help {
                margin: 5px 0 0;
                color: #666;
                font-size: 13px;
            }

            .gdm-price-options .gdm-field-separator {
                margin: 20px 0;
                border: 0;
                border-top: 1px solid #ddd;
            }

            .gdm-price-options .gdm-checkbox-label {
                display: flex;
                align-items: flex-start;
                gap: 8px;
            }

            .gdm-price-calculator {
                padding: 15px;
                background: #f9f9f9;
                border-left: 4px solid #2271b1;
                border-radius: 3px;
            }

            .gdm-price-calculator .gdm-calc-input-group {
                margin-bottom: 10px;
            }

            .gdm-price-calculator .gdm-calc-input-group label {
                display: block;
                margin-bottom: 5px;
                color: #666;
                font-size: 12px;
            }

            .gdm-price-calculator .gdm-calc-result {
                margin-top: 15px;
                padding: 10px;
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 3px;
            }

            .gdm-price-calculator .gdm-calc-nuevo {
                font-weight: 600;
                color: #2271b1;
                font-size: 18px;
            }

            .gdm-price-calculator .gdm-calc-detalle {
                color: #666;
                font-size: 12px;
                margin-top: 5px;
            }
        </style>
        <?php
    }
}
