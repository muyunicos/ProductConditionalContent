<?php
/**
 * Acción Descripción v7.0 (Versión Simplificada)
 * Permite modificar la descripción de productos
 * Compatible con WordPress 6.8.3, PHP 8.2, WooCommerce 10.2.2
 *
 * @package ProductConditionalContent
 * @since 7.0.0
 * @date 2025-10-16
 */

if (!defined('ABSPATH')) exit;

class GDM_Action_Description extends GDM_Action_Base {

    protected $action_id = 'description';
    protected $action_name = 'Descripción';
    protected $action_icon = '📝';
    protected $action_description = 'Modifica la descripción del producto';
    protected $priority = 10;
    protected $supported_contexts = ['products', 'posts'];

    protected function get_default_options() {
        return [
            'tipo' => 'replace',
            'content' => '',
        ];
    }

    protected function render_options($rule_id, $options) {
        ?>
        <div class="gdm-description-options">
            <div class="gdm-field-row">
                <label class="gdm-field-label">
                    <strong><?php _e('⚙️ Tipo de Modificación', 'product-conditional-content'); ?></strong>
                </label>
                <select name="gdm_actions[description][options][tipo]" class="gdm-field-control">
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
                    <strong><?php _e('📝 Contenido', 'product-conditional-content'); ?></strong>
                </label>
                <?php
                wp_editor($options['content'], 'gdm_action_description_content', [
                    'textarea_name' => 'gdm_actions[description][options][content]',
                    'textarea_rows' => 10,
                    'media_buttons' => true,
                    'teeny' => false,
                ]);
                ?>
            </div>
        </div>
        <?php
    }

    protected function sanitize_options($post_data) {
        $options = isset($post_data['options']) ? $post_data['options'] : [];
        return [
            'tipo' => $this->sanitize_text($options['tipo'] ?? 'replace'),
            'content' => $this->sanitize_html($options['content'] ?? ''),
        ];
    }

    protected function generate_execution_code($options) {
        $tipo = $options['tipo'];
        $content = addslashes($options['content']);

        return "\$product = wc_get_product(\$object_id);
        if (\$product) {
            \$description = \$product->get_description();
            \$new_content = '{$content}';

            switch ('{$tipo}') {
                case 'replace':
                    \$product->set_description(\$new_content);
                    break;
                case 'append':
                    \$product->set_description(\$description . \$new_content);
                    break;
                case 'prepend':
                    \$product->set_description(\$new_content . \$description);
                    break;
            }

            \$product->save();
            return true;
        }
        return false;";
    }
}
