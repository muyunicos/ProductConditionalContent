<?php
if (!defined('ABSPATH')) exit;

final class GDM_Reglas_Metabox {
    public static function init() {
        add_action('add_meta_boxes', [__CLASS__, 'add_metabox']);
        add_action('save_post_gdm_regla', [__CLASS__, 'save_metabox']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    public static function enqueue_assets($hook) {
        $screen = get_current_screen();
        // Solo en pantallas relevantes (gdm_regla, gdm_campo)
        if (in_array($screen->id, ['gdm_regla', 'gdm_campo'])) {
            wp_enqueue_script('gdm-admin', GDM_PLUGIN_URL . 'assets/admin/gdm-admin.js', ['jquery', 'jquery-ui-sortable'], GDM_VERSION, true);
            wp_enqueue_style('gdm-admin', GDM_PLUGIN_URL . 'assets/admin/gdm-admin.css', [], GDM_VERSION);
            // shared-styles.css ya se encola globalmente vía plugin-init.php
            wp_localize_script('gdm-admin', 'gdmAdmin', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('gdm_admin_nonce'),
                'currentPostId' => get_the_ID(),
            ]);
        }
    }

    public static function add_metabox() {
        add_meta_box(
            'gdm_regla_advanced',
            __('Opciones Avanzadas de la Regla', 'product-conditional-content'),
            [__CLASS__, 'render_metabox'],
            'gdm_regla',
            'normal',
            'high'
        );
    }

    public static function render_metabox($post) {
        // Recuperar datos guardados
        $variantes = get_post_meta($post->ID, '_gdm_regla_variantes', true) ?: [];
        $condiciones = get_post_meta($post->ID, '_gdm_regla_condiciones', true) ?: [];
        // ...otros datos necesarios

        wp_nonce_field('gdm_regla_metabox', 'gdm_regla_metabox_nonce');

        ?>
        <div id="gdm-admin-metabox">
            <!-- Aquí va tu HTML avanzado, por ejemplo: -->
            <div style="margin-bottom:16px;">
                <label for="gdm_regla_descripcion"><strong><?php _e('Descripción avanzada:', 'product-conditional-content'); ?></strong></label>
                <textarea id="gdm_regla_descripcion" name="gdm_regla_descripcion" rows="3" class="widefat"><?php echo esc_textarea(get_post_meta($post->ID, '_gdm_regla_descripcion', true)); ?></textarea>
            </div>
            <div>
                <h3><?php _e('Condiciones y variantes', 'product-conditional-content'); ?></h3>
                <!-- Botón añadir variante, contador, tabla, etc, igual que tu HTML original -->
                <button type="button" class="button" id="gdm-add-repeater-row"><?php _e('Agregar Variante', 'product-conditional-content'); ?></button>
                <span id="gdm-variant-counter" style="margin-left:10px;"></span>
                <table class="widefat" style="margin-top:10px;">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="gdm-select-all-variants" /></th>
                            <th><?php _e('Condición', 'product-conditional-content'); ?></th>
                            <th><?php _e('Clave', 'product-conditional-content'); ?></th>
                            <th><?php _e('Valor', 'product-conditional-content'); ?></th>
                            <th><?php _e('Acciones', 'product-conditional-content'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="gdm-repeater-tbody">
                        <!-- Aquí se insertan las filas dinámicamente vía JS o puedes renderizar variantes existentes -->
                        <?php if (is_array($variantes) && count($variantes)): ?>
                            <?php foreach ($variantes as $i => $var): ?>
                                <tr class="gdm-repeater-row" data-index="<?php echo $i; ?>">
                                    <td><input type="checkbox" class="gdm-row-checkbox" /></td>
                                    <td>
                                        <select name="gdm_condiciones[<?php echo $i; ?>][type]" class="gdm-cond-type">
                                            <option value="tag" <?php selected($var['type'], 'tag'); ?>>Tag</option>
                                            <option value="meta" <?php selected($var['type'], 'meta'); ?>>Meta</option>
                                        </select>
                                    </td>
                                    <td><input type="text" name="gdm_condiciones[<?php echo $i; ?>][key]" class="gdm-cond-key" value="<?php echo esc_attr($var['key']); ?>" /></td>
                                    <td><input type="text" name="gdm_condiciones[<?php echo $i; ?>][value]" class="gdm-cond-value" value="<?php echo esc_attr($var['value']); ?>" /></td>
                                    <td><span class="sort-handle dashicons dashicons-move"></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                <button type="button" class="button button-secondary" id="gdm-delete-selected" disabled><?php _e('Eliminar Seleccionadas', 'product-conditional-content'); ?></button>
                <script type="text/template" id="gdm-repeater-template">
                    <tr class="gdm-repeater-row" data-index="__INDEX__">
                        <td><input type="checkbox" class="gdm-row-checkbox" /></td>
                        <td>
                            <select name="gdm_condiciones[__INDEX__][type]" class="gdm-cond-type">
                                <option value="tag">Tag</option>
                                <option value="meta">Meta</option>
                            </select>
                        </td>
                        <td><input type="text" name="gdm_condiciones[__INDEX__][key]" class="gdm-cond-key" value="" /></td>
                        <td><input type="text" name="gdm_condiciones[__INDEX__][value]" class="gdm-cond-value" value="" /></td>
                        <td><span class="sort-handle dashicons dashicons-move"></span></td>
                    </tr>
                </script>
            </div>
            <!-- Puedes seguir con tus filtros, shortcodes, etc -->
        </div>
        <?php
    }

    public static function save_metabox($post_id) {
        if (!isset($_POST['gdm_regla_metabox_nonce']) || !wp_verify_nonce($_POST['gdm_regla_metabox_nonce'], 'gdm_regla_metabox')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        update_post_meta($post_id, '_gdm_regla_descripcion', sanitize_textarea_field($_POST['gdm_regla_descripcion'] ?? ''));
        // Guardar variantes (ejemplo básico, puedes mejorar la sanitización)
        $condiciones = $_POST['gdm_condiciones'] ?? [];
        update_post_meta($post_id, '_gdm_regla_condiciones', $condiciones);
    }
}

GDM_Reglas_Metabox::init();