<?php
/**
 * Acción Destacado v7.0 (Versión Simplificada)
 * Permite modificar el estado "destacado" de productos
 * Compatible con WordPress 6.8.3, PHP 8.2, WooCommerce 10.2.2
 *
 * @package ProductConditionalContent
 * @since 7.0.0
 * @date 2025-10-16
 */

if (!defined('ABSPATH')) exit;

class GDM_Action_Featured extends GDM_Action_Base {

    protected $action_id = 'featured-product';
    protected $action_name = 'Producto Destacado';
    protected $action_icon = '⭐';
    protected $action_description = 'Marca o desmarca productos como destacados';
    protected $priority = 30;
    protected $supported_contexts = ['products'];

    protected function get_default_options() {
        return [
            'accion' => 'marcar',
            'prioridad' => 10,
        ];
    }

    protected function render_options($rule_id, $options) {
        ?>
        <div class="gdm-featured-options">
            <div class="gdm-field-row">
                <label class="gdm-field-label">
                    <strong><?php _e('⚙️ Acción sobre el Estado Destacado', 'product-conditional-content'); ?></strong>
                </label>
                <select name="gdm_actions[featured][options][accion]" class="gdm-field-control">
                    <option value="marcar" <?php selected($options['accion'], 'marcar'); ?>>
                        <?php _e('⭐ Marcar como Destacado', 'product-conditional-content'); ?>
                    </option>
                    <option value="desmarcar" <?php selected($options['accion'], 'desmarcar'); ?>>
                        <?php _e('☆ Quitar Destacado', 'product-conditional-content'); ?>
                    </option>
                </select>
            </div>

            <div class="gdm-field-row">
                <label class="gdm-field-label">
                    <strong><?php _e('🔢 Prioridad', 'product-conditional-content'); ?></strong>
                </label>
                <input type="number"
                       name="gdm_actions[featured][options][prioridad]"
                       value="<?php echo esc_attr($options['prioridad']); ?>"
                       min="1"
                       max="100"
                       class="gdm-field-control small-text">
                <p class="gdm-field-help">
                    <?php _e('Mayor número = mayor prioridad si hay múltiples reglas', 'product-conditional-content'); ?>
                </p>
            </div>
        </div>
        <?php
    }

    protected function sanitize_options($post_data) {
        $options = isset($post_data['options']) ? $post_data['options'] : [];
        return [
            'accion' => $this->sanitize_text($options['accion'] ?? 'marcar'),
            'prioridad' => $this->sanitize_number($options['prioridad'] ?? 10, 10),
        ];
    }

    protected function generate_execution_code($options) {
        $accion = $options['accion'];
        return "\$product = wc_get_product(\$object_id);
        if (\$product) {
            \$featured = ('{$accion}' === 'marcar');
            \$product->set_featured(\$featured);
            \$product->save();
            return true;
        }
        return false;";
    }
}
public function get_supported_context() {
    return $this->$supported_contexts ?? [];
}
public function supports_context($context) {
    return in_array($context, $this->get_supported_categories());
}
