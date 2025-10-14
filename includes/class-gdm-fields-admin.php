<?php
if (!defined('ABSPATH')) exit;

/**
 * Clase para gestionar campos personalizados condicionales desde el admin
 */
final class GDM_Fields_Admin {
    const OPTION_KEY = 'gdm_product_custom_fields';

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
        add_action('wp_ajax_gdm_save_fields', [__CLASS__, 'ajax_save_fields']);
    }

    public static function add_admin_menu() {
        add_menu_page(
            'Campos Personalizados Producto',
            'Campos Producto',
            'manage_options',
            'gdm_product_fields',
            [__CLASS__, 'admin_page'],
            'dashicons-list-view',
            26
        );
    }

    public static function enqueue_scripts($hook) {
        if ($hook === 'toplevel_page_gdm_product_fields') {
            wp_enqueue_script('gdm-fields-admin', GDM_PLUGIN_URL . 'assets/admin/fields-admin.js', ['jquery'], GDM_VERSION, true);
            wp_enqueue_style('gdm-fields-admin', GDM_PLUGIN_URL . 'assets/admin/fields-admin.css', [], GDM_VERSION);
            wp_localize_script('gdm-fields-admin', 'gdmFieldsAdmin', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('gdm_fields_admin_nonce'),
            ]);
        }
    }

    public static function admin_page() {
        ?>
        <div class="wrap">
            <h1>Campos Personalizados de Producto</h1>
            <form id="gdm-fields-form" method="post">
                <table class="wp-list-table widefat fixed striped" id="gdm-fields-table">
                    <thead>
                        <tr>
                            <th>Label</th>
                            <th>ID</th>
                            <th>Tipo</th>
                            <th>Opciones</th>
                            <th>Precio (opciones)</th>
                            <th>Condición</th>
                            <th>Requerido</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Las filas se rellenan por JS -->
                    </tbody>
                </table>
                <button type="button" class="button" id="gdm-add-field">Agregar Campo</button>
                <button type="submit" class="button button-primary">Guardar Campos</button>
            </form>
            <div id="gdm-fields-saved" style="display:none;margin-top:15px;" class="notice notice-success"><p>¡Campos guardados!</p></div>
            <script type="text/template" id="gdm-field-row-template">
                <tr>
                    <td><input type="text" class="gdm-field-label" value="<%= label %>" /></td>
                    <td><input type="text" class="gdm-field-id" value="<%= id %>" /></td>
                    <td>
                        <select class="gdm-field-type">
                            <option value="text" <%= type==="text"?"selected":"" %>>Texto</option>
                            <option value="textarea" <%= type==="textarea"?"selected":"" %>>Área de texto</option>
                            <option value="select" <%= type==="select"?"selected":"" %>>Select</option>
                            <option value="checkbox" <%= type==="checkbox"?"selected":"" %>>Checkbox</option>
                            <option value="radio" <%= type==="radio"?"selected":"" %>>Radio</option>
                        </select>
                    </td>
                    <td>
                        <input type="text" class="gdm-field-options" value="<%= options %>" placeholder="opcion1:label1,opcion2:label2" />
                        <span class="description">Sólo select/radio</span>
                    </td>
                    <td>
                        <input type="text" class="gdm-field-prices" value="<%= prices %>" placeholder="opcion1:10,opcion2:20" />
                        <span class="description">Sólo select/radio/checkbox</span>
                    </td>
                    <td>
                        <input type="text" class="gdm-field-conditional" value='<%= conditional %>' placeholder='{"show_if":{"option_color":"rojo"}}' />
                        <span class="description">JSON. Ej: {"show_if":{"option_color":"rojo"}}</span>
                    </td>
                    <td>
                        <input type="checkbox" class="gdm-field-required" <%= required?"checked":"" %> />
                    </td>
                    <td>
                        <button type="button" class="button gdm-delete-field">Eliminar</button>
                    </td>
                </tr>
            </script>
        </div>
        <?php
    }

    public static function ajax_save_fields() {
        check_ajax_referer('gdm_fields_admin_nonce', 'nonce');
        $fields = isset($_POST['fields']) ? json_decode(stripslashes($_POST['fields']), true) : [];
        if (!is_array($fields)) wp_send_json_error('Formato inválido');

        update_option(self::OPTION_KEY, $fields);
        wp_send_json_success();
    }

    /** Devolver los campos actuales (para JS) */
    public static function get_fields() {
        return get_option(self::OPTION_KEY, []);
    }
}

GDM_Fields_Admin::init();