<?php
/**
 * Gestión de campos personalizados en productos (Admin)
 * Compatible con WordPress 6.8.3, PHP 8.2, WooCommerce 10.2.2
 * 
 * @package ProductConditionalContent
 * @since 5.0.1
 */

if (!defined('ABSPATH')) exit;

final class GDM_Fields_Admin {
    
    public static function initialize_fields_product_panel() {
        // Hook para añadir tab de campos en producto
        add_filter('woocommerce_product_data_tabs', [__CLASS__, 'add_product_tab']);
        add_action('woocommerce_product_data_panels', [__CLASS__, 'add_product_panel']);
        add_action('woocommerce_process_product_meta', [__CLASS__, 'save_product_fields']);
    }

    /**
     * Añadir tab personalizado en producto
     */
    public static function add_product_tab($tabs) {
        $tabs['gdm_fields'] = [
            'label'    => __('Opciones Personalizadas', 'product-conditional-content'),
            'target'   => 'gdm_product_fields_panel',
            'class'    => ['show_if_simple', 'show_if_variable'],
            'priority' => 75,
        ];
        return $tabs;
    }

    /**
     * Renderizar panel de campos personalizados
     */
    public static function add_product_panel() {
        global $post;
        
        $opciones_asignadas = get_post_meta($post->ID, '_gdm_opciones_asignadas', true) ?: [];
        
        // Obtener todas las opciones disponibles
        $opciones_disponibles = get_posts([
            'post_type' => 'gdm_opcion',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC'
        ]);
        
        ?>
        <div id="gdm_product_fields_panel" class="panel woocommerce_options_panel">
            <div class="options_group">
                <p class="form-field">
                    <label><?php _e('Opciones de Producto', 'product-conditional-content'); ?></label>
                    <select id="gdm_opciones_asignadas" 
                            name="gdm_opciones_asignadas[]" 
                            class="wc-enhanced-select" 
                            multiple="multiple" 
                            style="width:50%;">
                        <?php foreach ($opciones_disponibles as $opcion): ?>
                            <option value="<?php echo esc_attr($opcion->ID); ?>" 
                                    <?php selected(in_array($opcion->ID, $opciones_asignadas)); ?>>
                                <?php echo esc_html($opcion->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="description">
                        <?php _e('Selecciona las opciones personalizadas que se mostrarán en este producto.', 'product-conditional-content'); ?>
                    </span>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Guardar campos del producto
     */
    public static function save_product_fields($product_id) {
        if (!current_user_can('edit_post', $product_id)) {
            return;
        }

        $opciones = isset($_POST['gdm_opciones_asignadas']) && is_array($_POST['gdm_opciones_asignadas'])
            ? array_map('intval', $_POST['gdm_opciones_asignadas'])
            : [];

        update_post_meta($product_id, '_gdm_opciones_asignadas', $opciones);
    }
}

GDM_Fields_Admin::initialize_fields_product_panel();