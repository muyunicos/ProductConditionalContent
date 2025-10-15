<?php
/**
 * Módulo de Precio
 * Permite modificar el precio de productos con descuentos o adicionales
 * Compatible con WordPress 6.8.3, PHP 8.2, WooCommerce 10.2.2
 * 
 * @package ProductConditionalContent
 * @since 6.1.0
 * @date 2025-10-15
 */

if (!defined('ABSPATH')) exit;

class GDM_Module_Price extends GDM_Module_Base {
    
    protected $module_id = 'precio';
    protected $module_name = 'Precio';
    protected $module_icon = '💰';
    protected $priority = 'default';
    
    /**
     * Renderizar metabox
     */
    public function render_metabox($post) {
        $data = $this->get_module_data($post->ID);
        ?>
        <div class="gdm-module-precio">
            
            <!-- Tipo de modificación -->
            <div class="gdm-field-group">
                <label>
                    <strong><?php _e('⚙️ Tipo de Modificación:', 'product-conditional-content'); ?></strong>
                </label>
                <select name="gdm_precio_tipo" id="gdm_precio_tipo" class="regular-text">
                    <option value="descuento_porcentaje" <?php selected($data['tipo'], 'descuento_porcentaje'); ?>>
                        <?php _e('Descuento en Porcentaje (%)', 'product-conditional-content'); ?>
                    </option>
                    <option value="descuento_fijo" <?php selected($data['tipo'], 'descuento_fijo'); ?>>
                        <?php _e('Descuento Fijo (monto)', 'product-conditional-content'); ?>
                    </option>
                    <option value="adicional_porcentaje" <?php selected($data['tipo'], 'adicional_porcentaje'); ?>>
                        <?php _e('Adicional en Porcentaje (%)', 'product-conditional-content'); ?>
                    </option>
                    <option value="adicional_fijo" <?php selected($data['tipo'], 'adicional_fijo'); ?>>
                        <?php _e('Adicional Fijo (monto)', 'product-conditional-content'); ?>
                    </option>
                    <option value="precio_fijo" <?php selected($data['tipo'], 'precio_fijo'); ?>>
                        <?php _e('Establecer Precio Fijo', 'product-conditional-content'); ?>
                    </option>
                </select>
            </div>
            
            <!-- Valor del cambio -->
            <div class="gdm-field-group">
                <label>
                    <strong id="gdm-precio-valor-label">
                        <?php 
                        if (strpos($data['tipo'], 'porcentaje') !== false) {
                            _e('💯 Porcentaje:', 'product-conditional-content');
                        } elseif ($data['tipo'] === 'precio_fijo') {
                            _e('💵 Precio Fijo:', 'product-conditional-content');
                        } else {
                            _e('💵 Monto:', 'product-conditional-content');
                        }
                        ?>
                    </strong>
                </label>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <input type="number" 
                           name="gdm_precio_valor" 
                           id="gdm_precio_valor"
                           value="<?php echo esc_attr($data['valor']); ?>" 
                           min="0" 
                           step="0.01" 
                           class="regular-text">
                    <span id="gdm-precio-simbolo">
                        <?php echo strpos($data['tipo'], 'porcentaje') !== false ? '%' : get_woocommerce_currency_symbol(); ?>
                    </span>
                </div>
                <p class="gdm-field-description" id="gdm-precio-descripcion">
                    <?php $this->get_description_by_type($data['tipo']); ?>
                </p>
            </div>
            
            <!-- Aplicar a precio regular o de oferta -->
            <div class="gdm-field-group">
                <label>
                    <strong><?php _e('🎯 Aplicar a:', 'product-conditional-content'); ?></strong>
                </label>
                <select name="gdm_precio_aplicar_a" class="regular-text">
                    <option value="regular" <?php selected($data['aplicar_a'], 'regular'); ?>>
                        <?php _e('Precio Regular', 'product-conditional-content'); ?>
                    </option>
                    <option value="sale" <?php selected($data['aplicar_a'], 'sale'); ?>>
                        <?php _e('Precio de Oferta (si existe)', 'product-conditional-content'); ?>
                    </option>
                    <option value="ambos" <?php selected($data['aplicar_a'], 'ambos'); ?>>
                        <?php _e('Ambos Precios', 'product-conditional-content'); ?>
                    </option>
                    <option value="activo" <?php selected($data['aplicar_a'], 'activo'); ?>>
                        <?php _e('Precio Activo (el que se muestra)', 'product-conditional-content'); ?>
                    </option>
                </select>
            </div>
            
            <!-- Redondeo -->
            <div class="gdm-field-group">
                <label>
                    <strong><?php _e('🔢 Redondeo del Precio:', 'product-conditional-content'); ?></strong>
                </label>
                <select name="gdm_precio_redondeo" class="regular-text">
                    <option value="ninguno" <?php selected($data['redondeo'], 'ninguno'); ?>>
                        <?php _e('Sin redondeo', 'product-conditional-content'); ?>
                    </option>
                    <option value="arriba" <?php selected($data['redondeo'], 'arriba'); ?>>
                        <?php _e('Redondear hacia arriba', 'product-conditional-content'); ?>
                    </option>
                    <option value="abajo" <?php selected($data['redondeo'], 'abajo'); ?>>
                        <?php _e('Redondear hacia abajo', 'product-conditional-content'); ?>
                    </option>
                    <option value="cercano" <?php selected($data['redondeo'], 'cercano'); ?>>
                        <?php _e('Redondear al más cercano', 'product-conditional-content'); ?>
                    </option>
                    <option value="terminacion_99" <?php selected($data['redondeo'], 'terminacion_99'); ?>>
                        <?php _e('Terminar en .99', 'product-conditional-content'); ?>
                    </option>
                    <option value="terminacion_95" <?php selected($data['redondeo'], 'terminacion_95'); ?>>
                        <?php _e('Terminar en .95', 'product-conditional-content'); ?>
                    </option>
                    <option value="terminacion_00" <?php selected($data['redondeo'], 'terminacion_00'); ?>>
                        <?php _e('Terminar en .00', 'product-conditional-content'); ?>
                    </option>
                </select>
            </div>
            
            <hr class="gdm-separator">
            
            <!-- Opciones avanzadas -->
            <div class="gdm-field-group">
                <label>
                    <input type="checkbox" 
                           name="gdm_precio_mostrar_antes" 
                           value="1" 
                           <?php checked($data['mostrar_antes'], '1'); ?>>
                    <strong><?php _e('💸 Mostrar precio anterior tachado', 'product-conditional-content'); ?></strong>
                </label>
                <p class="gdm-field-description">
                    <?php _e('Muestra el precio original tachado junto al nuevo precio (solo para descuentos)', 'product-conditional-content'); ?>
                </p>
            </div>
            
            <div class="gdm-field-group">
                <label>
                    <input type="checkbox" 
                           name="gdm_precio_badge_descuento" 
                           value="1" 
                           <?php checked($data['badge_descuento'], '1'); ?>>
                    <strong><?php _e('🏷️ Mostrar badge de descuento', 'product-conditional-content'); ?></strong>
                </label>
                <p class="gdm-field-description">
                    <?php _e('Agrega un badge visual con el porcentaje de descuento (ej: "20% OFF")', 'product-conditional-content'); ?>
                </p>
            </div>
            
            <hr class="gdm-separator">
            
            <!-- Calculadora de precios -->
            <div class="gdm-field-group">
                <label>
                    <strong><?php _e('🧮 Calculadora de Precios:', 'product-conditional-content'); ?></strong>
                </label>
                <div style="padding: 15px; background: #f9f9f9; border-left: 4px solid #2271b1; border-radius: 3px;">
                    <div style="margin-bottom: 10px;">
                        <label style="display: block; margin-bottom: 5px; color: #666; font-size: 12px;">
                            <?php _e('Precio Original:', 'product-conditional-content'); ?>
                        </label>
                        <input type="number" 
                               id="gdm-precio-calc-original" 
                               value="100.00" 
                               step="0.01" 
                               class="regular-text">
                    </div>
                    <button type="button" class="button" id="gdm-precio-calc-btn">
                        <span class="dashicons dashicons-calculator"></span>
                        <?php _e('Calcular', 'product-conditional-content'); ?>
                    </button>
                    <div id="gdm-precio-calc-resultado" style="margin-top: 15px; padding: 10px; background: #fff; border: 1px solid #ddd; border-radius: 3px; display: none;">
                        <div style="font-weight: 600; color: #2271b1; font-size: 18px;" id="gdm-precio-calc-nuevo"></div>
                        <div style="color: #666; font-size: 12px; margin-top: 5px;" id="gdm-precio-calc-detalle"></div>
                    </div>
                </div>
            </div>
            
        </div>
        
        <style>
            .gdm-module-precio .gdm-field-group {
                margin-bottom: 20px;
            }
        </style>
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
        
        echo esc_html($descriptions[$tipo] ?? '');
    }
    
    /**
     * Guardar datos del módulo
     */
    public function save_module_data($post_id, $post) {
        if (!$this->is_module_active($post_id)) {
            return;
        }
        
        // Tipo de modificación
        $tipo = isset($_POST['gdm_precio_tipo']) ? sanitize_text_field($_POST['gdm_precio_tipo']) : 'descuento_porcentaje';
        update_post_meta($post_id, '_gdm_precio_tipo', $tipo);
        
        // Valor
        $valor = isset($_POST['gdm_precio_valor']) ? floatval($_POST['gdm_precio_valor']) : 0;
        update_post_meta($post_id, '_gdm_precio_valor', $valor);
        
        // Aplicar a
        $aplicar_a = isset($_POST['gdm_precio_aplicar_a']) ? sanitize_text_field($_POST['gdm_precio_aplicar_a']) : 'activo';
        update_post_meta($post_id, '_gdm_precio_aplicar_a', $aplicar_a);
        
        // Redondeo
        $redondeo = isset($_POST['gdm_precio_redondeo']) ? sanitize_text_field($_POST['gdm_precio_redondeo']) : 'ninguno';
        update_post_meta($post_id, '_gdm_precio_redondeo', $redondeo);
        
        // Opciones avanzadas
        $mostrar_antes = isset($_POST['gdm_precio_mostrar_antes']) ? '1' : '0';
        update_post_meta($post_id, '_gdm_precio_mostrar_antes', $mostrar_antes);
        
        $badge_descuento = isset($_POST['gdm_precio_badge_descuento']) ? '1' : '0';
        update_post_meta($post_id, '_gdm_precio_badge_descuento', $badge_descuento);
    }
    
    /**
     * Obtener datos por defecto
     */
    protected function get_default_data() {
        return [
            'tipo' => 'descuento_porcentaje',
            'valor' => 0,
            'aplicar_a' => 'activo',
            'redondeo' => 'ninguno',
            'mostrar_antes' => '0',
            'badge_descuento' => '0',
        ];
    }
    
    /**
     * Obtener datos del módulo con caché
     */
    private function get_module_data($post_id) {
        $cache_key = "price_{$post_id}";
        
        if (isset(self::$cache[$cache_key])) {
            return self::$cache[$cache_key];
        }
        
        $data = [
            'tipo' => get_post_meta($post_id, '_gdm_precio_tipo', true) ?: 'descuento_porcentaje',
            'valor' => get_post_meta($post_id, '_gdm_precio_valor', true) ?: 0,
            'aplicar_a' => get_post_meta($post_id, '_gdm_precio_aplicar_a', true) ?: 'activo',
            'redondeo' => get_post_meta($post_id, '_gdm_precio_redondeo', true) ?: 'ninguno',
            'mostrar_antes' => get_post_meta($post_id, '_gdm_precio_mostrar_antes', true),
            'badge_descuento' => get_post_meta($post_id, '_gdm_precio_badge_descuento', true),
        ];
        
        self::$cache[$cache_key] = $data;
        return $data;
    }
    
    /**
     * Encolar assets
     */
    public function enqueue_assets($hook) {
        $screen = get_current_screen();
        if ($screen->id !== 'gdm_regla') {
            return;
        }
        
        wp_add_inline_script('jquery', "
        jQuery(document).ready(function($) {
            // Actualizar labels y símbolos según tipo
            $('#gdm_precio_tipo').on('change', function() {
                var tipo = $(this).val();
                var esPorcentaje = tipo.indexOf('porcentaje') !== -1;
                var esFijo = tipo === 'precio_fijo';
                
                // Actualizar símbolo
                if (esPorcentaje) {
                    $('#gdm-precio-simbolo').text('%');
                    $('#gdm-precio-valor-label').text('💯 Porcentaje:');
                } else if (esFijo) {
                    $('#gdm-precio-simbolo').text('" . get_woocommerce_currency_symbol() . "');
                    $('#gdm-precio-valor-label').text('💵 Precio Fijo:');
                } else {
                    $('#gdm-precio-simbolo').text('" . get_woocommerce_currency_symbol() . "');
                    $('#gdm-precio-valor-label').text('💵 Monto:');
                }
                
                // Actualizar descripción
                var descripciones = {
                    'descuento_porcentaje': 'Ejemplo: 20 para aplicar 20% de descuento',
                    'descuento_fijo': 'Ejemplo: 50 para descontar 50 del precio',
                    'adicional_porcentaje': 'Ejemplo: 15 para incrementar el precio en 15%',
                    'adicional_fijo': 'Ejemplo: 30 para agregar 30 al precio',
                    'precio_fijo': 'El precio del producto será exactamente este valor'
                };
                
                $('#gdm-precio-descripcion').text(descripciones[tipo] || '');
            });
            
            // Calculadora
            $('#gdm-precio-calc-btn').on('click', function() {
                var original = parseFloat($('#gdm-precio-calc-original').val()) || 100;
                var tipo = $('#gdm_precio_tipo').val();
                var valor = parseFloat($('#gdm_precio_valor').val()) || 0;
                var redondeo = $('[name=\"gdm_precio_redondeo\"]').val();
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
                        detalle = 'Precio fijo: ';
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
                
                // Mostrar resultado
                $('#gdm-precio-calc-nuevo').text('" . get_woocommerce_currency_symbol() . "' + nuevo.toFixed(2));
                $('#gdm-precio-calc-detalle').text(detalle + nuevo.toFixed(2));
                $('#gdm-precio-calc-resultado').slideDown();
            });
            
            // Auto-calcular al cambiar valores
            $('#gdm_precio_tipo, #gdm_precio_valor, [name=\"gdm_precio_redondeo\"]').on('change', function() {
                if ($('#gdm-precio-calc-resultado').is(':visible')) {
                    $('#gdm-precio-calc-btn').click();
                }
            });
        });
        ");
    }
}

// Registrar módulo
add_action('init', function() {
    GDM_Module_Manager::instance()->register_module('precio', [
        'class' => 'GDM_Module_Price',
        'label' => __('Precio', 'product-conditional-content'),
        'icon' => '💰',
        'file' => __FILE__,
        'enabled' => true,
        'priority' => 25,
    ]);
}, 5);