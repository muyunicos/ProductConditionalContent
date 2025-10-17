<?php
/**
 * Acción Galería v7.0 (Versión Simplificada)
 * Permite modificar la galería de imágenes de productos
 * Compatible con WordPress 6.8.3, PHP 8.2, WooCommerce 10.2.2
 *
 * @package ProductConditionalContent
 * @since 7.0.0
 * @date 2025-10-16
 */

if (!defined('ABSPATH')) exit;

class GDM_Action_Gallery extends GDM_Action_Base {

    protected $action_id = 'product-gallery';
    protected $action_name = 'Galería de Producto';
    protected $action_icon = '🖼️';
    protected $action_description = 'Modifica la galería de imágenes del producto';
    protected $priority = 20;
    protected $supported_contexts = ['products'];

    protected function get_default_options() {
        return [
            'tipo' => 'replace',
            'images' => [],
        ];
    }

    protected function render_options($rule_id, $options) {
        ?>
        <div class="gdm-gallery-options">
            <div class="gdm-field-row">
                <label class="gdm-field-label">
                    <strong><?php _e('⚙️ Tipo de Modificación', 'product-conditional-content'); ?></strong>
                </label>
                <select name="gdm_actions[gallery][options][tipo]" class="gdm-field-control">
                    <option value="replace" <?php selected($options['tipo'], 'replace'); ?>>
                        <?php _e('Reemplazar galería completa', 'product-conditional-content'); ?>
                    </option>
                    <option value="append" <?php selected($options['tipo'], 'append'); ?>>
                        <?php _e('Agregar imágenes al final', 'product-conditional-content'); ?>
                    </option>
                    <option value="prepend" <?php selected($options['tipo'], 'prepend'); ?>>
                        <?php _e('Agregar imágenes al inicio', 'product-conditional-content'); ?>
                    </option>
                </select>
            </div>

            <div class="gdm-field-row">
                <label class="gdm-field-label">
                    <strong><?php _e('🖼️ Imágenes de la Galería', 'product-conditional-content'); ?></strong>
                </label>
                <p class="gdm-field-help">
                    <?php _e('IDs de imágenes separados por comas (ej: 123,456,789)', 'product-conditional-content'); ?>
                </p>
                <input type="text"
                       name="gdm_actions[gallery][options][images]"
                       value="<?php echo esc_attr(is_array($options['images']) ? implode(',', $options['images']) : $options['images']); ?>"
                       class="gdm-field-control"
                       placeholder="123,456,789">
            </div>
        </div>
        <?php
    }

    protected function sanitize_options($post_data) {
        $options = isset($post_data['options']) ? $post_data['options'] : [];
        $images = isset($options['images']) ? $options['images'] : '';

        // Convertir string a array de IDs
        if (is_string($images)) {
            $images = array_filter(array_map('absint', explode(',', $images)));
        }

        return [
            'tipo' => $this->sanitize_text($options['tipo'] ?? 'replace'),
            'images' => $images,
        ];
    }

    protected function generate_execution_code($options) {
        $tipo = $options['tipo'];
        $images_json = json_encode($options['images']);

        return "\$product = wc_get_product(\$object_id);
        if (\$product) {
            \$new_images = json_decode('{$images_json}', true);
            \$current_images = \$product->get_gallery_image_ids();

            switch ('{$tipo}') {
                case 'replace':
                    \$product->set_gallery_image_ids(\$new_images);
                    break;
                case 'append':
                    \$product->set_gallery_image_ids(array_merge(\$current_images, \$new_images));
                    break;
                case 'prepend':
                    \$product->set_gallery_image_ids(array_merge(\$new_images, \$current_images));
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
