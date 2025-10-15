<?php
/**
 * Gestión de reglas en productos (Admin)
 * Compatible con WordPress 6.8.3, PHP 8.2, WooCommerce 10.2.2
 * 
 * @package ProductConditionalContent
 * @since 5.0.1
 */

if (!defined('ABSPATH')) exit;

final class GDM_Rules_Admin {
    
    public static function initialize_rules_product_panel() {
        // Hook para añadir tab de reglas en producto
        add_filter('woocommerce_product_data_tabs', [__CLASS__, 'add_product_tab']);
        add_action('woocommerce_product_data_panels', [__CLASS__, 'add_product_panel']);
        add_action('woocommerce_process_product_meta', [__CLASS__, 'save_product_rules']);
    }

    /**
     * Añadir tab personalizado en producto
     */
    public static function add_product_tab($tabs) {
        $tabs['gdm_rules'] = [
            'label'    => __('Reglas de Contenido', 'product-conditional-content'),
            'target'   => 'gdm_product_rules_panel',
            'class'    => ['show_if_simple', 'show_if_variable'],
            'priority' => 70,
        ];
        return $tabs;
    }

    /**
     * Renderizar panel de reglas
     */
    public static function add_product_panel() {
        global $post;
        
        $reglas_asignadas = get_post_meta($post->ID, '_gdm_reglas_asignadas', true) ?: [];
        
        // Obtener todas las reglas disponibles
        $reglas_disponibles = get_posts([
            'post_type' => 'gdm_regla',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC'
        ]);
        
        ?>
        <div id="gdm_product_rules_panel" class="panel woocommerce_options_panel">
            <div class="options_group">
                <p class="form-field">
                    <label><?php _e('Reglas Aplicables', 'product-conditional-content'); ?></label>
                    <select id="gdm_reglas_asignadas" 
                            name="gdm_reglas_asignadas[]" 
                            class="wc-enhanced-select" 
                            multiple="multiple" 
                            style="width:50%;">
                        <?php foreach ($reglas_disponibles as $regla): ?>
                            <option value="<?php echo esc_attr($regla->ID); ?>" 
                                    <?php selected(in_array($regla->ID, $reglas_asignadas)); ?>>
                                <?php echo esc_html($regla->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="description">
                        <?php _e('Selecciona las reglas que se aplicarán a este producto específicamente.', 'product-conditional-content'); ?>
                    </span>
                </p>
                
                <p class="form-field">
                    <label for="gdm_habilitar_descripcion_manual">
                        <input type="checkbox" 
                               id="gdm_habilitar_descripcion_manual" 
                               name="gdm_habilitar_descripcion_manual" 
                               value="1" 
                               <?php checked(get_post_meta($post->ID, '_gdm_habilitar_descripcion_manual', true), '1'); ?> />
                        <?php _e('Habilitar descripción manual', 'product-conditional-content'); ?>
                    </label>
                    <span class="description" style="display:block;margin-left:25px;">
                        <?php _e('Si está desmarcado, las reglas reemplazarán completamente la descripción del producto.', 'product-conditional-content'); ?>
                    </span>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Guardar reglas del producto
     */
    public static function save_product_rules($product_id) {
        if (!current_user_can('edit_post', $product_id)) {
            return;
        }

        $reglas = isset($_POST['gdm_reglas_asignadas']) && is_array($_POST['gdm_reglas_asignadas'])
            ? array_map('intval', $_POST['gdm_reglas_asignadas'])
            : [];

        update_post_meta($product_id, '_gdm_reglas_asignadas', $reglas);

        $habilitar_manual = isset($_POST['gdm_habilitar_descripcion_manual']) ? '1' : '0';
        update_post_meta($product_id, '_gdm_habilitar_descripcion_manual', $habilitar_manual);
    }
}

GDM_Rules_Admin::initialize_rules_product_panel();