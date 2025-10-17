<?php
/**
 * Acción Título v7.0 (Versión Simplificada)
 * Permite modificar el título de productos
 * Compatible con WordPress 6.8.3, PHP 8.2, WooCommerce 10.2.2
 *
 * @package ProductConditionalContent
 * @since 7.0.0
 * @date 2025-10-16
 */

if (!defined('ABSPATH')) exit;

class GDM_Action_Title extends GDM_Action_Base {

    protected $action_id = 'product-title';
    protected $action_name = 'Título de Producto';
    protected $action_icon = '📝';
    protected $action_description = 'Modifica el título del producto';
    protected $priority = 5;
    protected $supported_contexts = ['products'];

    protected function get_default_options() {
        return [
            'tipo' => 'replace',
            'content' => '',
        ];
    }

    protected function render_options($rule_id, $options) {
        ?>
        <div class="gdm-title-options">
            <div class="gdm-field-row">
                <label class="gdm-field-label">
                    <strong><?php _e('⚙️ Tipo de Modificación', 'product-conditional-content'); ?></strong>
                </label>
                <select name="gdm_actions[title][options][tipo]" class="gdm-field-control">
                    <option value="replace" <?php selected($options['tipo'], 'replace'); ?>>
                        <?php _e('Reemplazar completamente', 'product-conditional-content'); ?>
                    </option>
                    <option value="append" <?php selected($options['tipo'], 'append'); ?>>
                        <?php _e('Agregar al final', 'product-conditional-content'); ?>
                    </option>
                    <option value="prepend" <?php selected($options['tipo'], 'prepend'); ?>>
                        <?php _e('Agregar al inicio', 'product-conditional-content'); ?>
                    </option>
                </select>
            </div>

            <div class="gdm-field-row">
                <label class="gdm-field-label">
                    <strong><?php _e('📝 Nuevo Título / Texto', 'product-conditional-content'); ?></strong>
                </label>
                <input type="text"
                       name="gdm_actions[title][options][content]"
                       value="<?php echo esc_attr($options['content']); ?>"
                       class="gdm-field-control"
                       placeholder="<?php esc_attr_e('Nuevo título', 'product-conditional-content'); ?>">
            </div>
        </div>
        <?php
    }

    protected function sanitize_options($post_data) {
        $options = isset($post_data['options']) ? $post_data['options'] : [];
        return [
            'tipo' => $this->sanitize_text($options['tipo'] ?? 'replace'),
            'content' => $this->sanitize_text($options['content'] ?? ''),
        ];
    }

    protected function generate_execution_code($options) {
        $tipo = $options['tipo'];
        $content = addslashes($options['content']);

        return "\$product = wc_get_product(\$object_id);
        if (\$product) {
            \$current_title = \$product->get_name();
            \$new_content = '{$content}';

            switch ('{$tipo}') {
                case 'replace':
                    \$product->set_name(\$new_content);
                    break;
                case 'append':
                    \$product->set_name(\$current_title . ' ' . \$new_content);
                    break;
                case 'prepend':
                    \$product->set_name(\$new_content . ' ' . \$current_title);
                    break;
            }

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
