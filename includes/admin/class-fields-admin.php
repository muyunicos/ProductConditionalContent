<?php
if (!defined('ABSPATH')) exit;

/**
 * Clase para gestionar campos personalizados condicionales desde el admin
 *
 * - Añade el submenú "Campos Personalizados" bajo "Reglas de Contenido"
 * - Permite agregar, editar (con modal), borrar y guardar campos personalizados
 * - Carga JS/CSS solo en la página correspondiente
 * - AJAX para guardar configuración
 */
final class GDM_Fields_Admin {
    const OPTION_KEY = 'gdm_product_custom_fields';

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
        add_action('wp_ajax_gdm_save_fields', [__CLASS__, 'ajax_save_fields']);
    }

    /**
     * Añade el submenú en "Reglas de Contenido"
     */
    public static function add_admin_menu() {
        add_submenu_page(
            'gdm_content_rules', // Slug del menú principal (coincide con class-admin-menu.php)
            __('Campos Personalizados', 'product-conditional-content'),
            __('Campos Personalizados', 'product-conditional-content'),
            'manage_options',
            'gdm_product_fields',
            [__CLASS__, 'admin_page'],
            2 // Posición
        );
    }

    /**
     * Carga assets solo en la página de campos personalizados
     */
    public static function enqueue_scripts($hook) {
        // Verifica si estamos en la página correcta
        $screen = get_current_screen();
        if ($screen && $screen->id === 'toplevel_page_gdm_product_fields' || $screen->id === 'gdm_content_rules_page_gdm_product_fields') {
            wp_enqueue_script('gdm-fields-admin', GDM_PLUGIN_URL . 'assets/admin/fields-admin.js', ['jquery'], GDM_VERSION, true);
            wp_enqueue_style('gdm-fields-admin', GDM_PLUGIN_URL . 'assets/admin/fields-admin.css', [], GDM_VERSION);
            wp_localize_script('gdm-fields-admin', 'gdmFieldsAdmin', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('gdm_fields_admin_nonce'),
                'fields'  => self::get_fields(),
            ]);
        }
    }

    /**
     * Página de gestión de campos personalizados
     */
    public static function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Campos Personalizados de Producto', 'product-conditional-content'); ?></h1>
            <form id="gdm-fields-form" method="post" autocomplete="off">
                <table class="wp-list-table widefat fixed striped" id="gdm-fields-table">
                    <thead>
                        <tr>
                            <th style="width:32px;"><input type="checkbox" id="gdm-select-all-fields" /></th>
                            <th><?php esc_html_e('ID', 'product-conditional-content'); ?></th>
                            <th><?php esc_html_e('Nombre', 'product-conditional-content'); ?></th>
                            <th><?php esc_html_e('Tipo', 'product-conditional-content'); ?></th>
                            <th><?php esc_html_e('Precio', 'product-conditional-content'); ?></th>
                            <th><?php esc_html_e('Requerido', 'product-conditional-content'); ?></th>
                            <th><?php esc_html_e('Editar', 'product-conditional-content'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Las filas se rellenan por JS -->
                    </tbody>
                </table>
                <div class="gdm-fields-actions">
                    <button type="button" class="button" id="gdm-add-field"><?php esc_html_e('Agregar Campo', 'product-conditional-content'); ?></button>
                    <button type="button" class="button button-secondary" id="gdm-delete-selected-fields" disabled><?php esc_html_e('Eliminar Seleccionados', 'product-conditional-content'); ?></button>
                    <button type="submit" class="button button-primary"><?php esc_html_e('Guardar Campos', 'product-conditional-content'); ?></button>
                </div>
            </form>
            <div id="gdm-fields-saved" style="display:none;margin-top:15px;" class="notice notice-success"><p><?php esc_html_e('¡Campos guardados!', 'product-conditional-content'); ?></p></div>
            <!-- Modal de edición avanzada de campo -->
            <div id="gdm-field-modal" style="display:none;">
                <!-- El contenido del modal lo controla el JS (fields-admin.js) -->
            </div>
            <!-- Plantilla Underscore.js para filas de la tabla -->
            <script type="text/template" id="gdm-field-row-template">
                <tr>
                    <td><input type="checkbox" class="gdm-field-row-checkbox" /></td>
                    <td><%= id %></td>
                    <td><%= label %></td>
                    <td><%= type %></td>
                    <td><%= price %></td>
                    <td><input type="checkbox" class="gdm-field-row-required" <% if (required) { %>checked<% } %> disabled /></td>
                    <td>
                        <button type="button" class="button gdm-edit-field"><?php esc_html_e('Editar', 'product-conditional-content'); ?></button>
                    </td>
                </tr>
            </script>
        </div>
        <?php
    }

    /**
     * Guarda los campos personalizados vía AJAX
     */
    public static function ajax_save_fields() {
        check_ajax_referer('gdm_fields_admin_nonce', 'nonce');
        $fields = isset($_POST['fields']) ? json_decode(stripslashes($_POST['fields']), true) : [];
        if (!is_array($fields)) wp_send_json_error('Formato inválido');
        update_option(self::OPTION_KEY, $fields);
        wp_send_json_success();
    }

    /**
     * Devuelve los campos actuales (para JS)
     */
    public static function get_fields() {
        $fields = get_option(self::OPTION_KEY, []);
        // Puedes filtrar/ordenar aquí si lo necesitas
        return $fields;
    }
}

GDM_Fields_Admin::init();