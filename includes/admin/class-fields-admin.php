<?php
if (!defined('ABSPATH')) exit;

/**
 * Clase para gestionar campos personalizados condicionales desde el admin.
 *
 * Responsabilidad SOLA: UI y gestión de campos personalizados en el admin.
 * - Añade el submenú "Campos Personalizados" bajo "Reglas de Contenido" (no frontend).
 * - Permite agregar, editar (con modal), borrar y guardar campos personalizados vía AJAX.
 * - Carga JS/CSS solo en la página correspondiente.
 * - Sanitiza y valida los datos antes de guardar.
 */
final class GDM_Fields_Admin {
    const OPTION_KEY = 'gdm_product_custom_fields';

    public static function init() {
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
        add_action('wp_ajax_gdm_save_fields', [__CLASS__, 'ajax_save_fields']);
    }

    /**
     * Carga assets solo en la página de campos personalizados
     */
    public static function enqueue_scripts($hook) {
        $screen = get_current_screen();
        if ($screen && ($screen->id === 'toplevel_page_gdm_product_fields' || $screen->id === 'gdm_content_rules_page_gdm_product_fields')) {
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
     * Página de gestión de campos personalizados (solo admin)
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
        // Solo admins
        if (!current_user_can('manage_options')) wp_send_json_error('Sin permisos');
        check_ajax_referer('gdm_fields_admin_nonce', 'nonce');
        $fields = isset($_POST['fields']) ? json_decode(stripslashes($_POST['fields']), true) : [];
        if (!is_array($fields)) wp_send_json_error('Formato inválido');

        // Sanitización básica por campo
        $sanitized = [];
        foreach ($fields as $f) {
            $sanitized[] = [
                'id'        => sanitize_key($f['id'] ?? ''),
                'label'     => sanitize_text_field($f['label'] ?? ''),
                'type'      => in_array($f['type'] ?? '', ['text','textarea','select','checkbox','radio']) ? $f['type'] : 'text',
                'price'     => is_numeric($f['price'] ?? '') ? floatval($f['price']) : '',
                'required'  => !empty($f['required']),
                // Opciones y condicionales si existen
                'options'   => isset($f['options']) && is_array($f['options']) ? array_map(function($opt){
                    return [
                        'value' => sanitize_key($opt['value'] ?? ''),
                        'label' => sanitize_text_field($opt['label'] ?? ''),
                        'price' => isset($opt['price']) && is_numeric($opt['price']) ? floatval($opt['price']) : '',
                    ];
                }, $f['options']) : [],
                'conditional' => isset($f['conditional']) && is_array($f['conditional']) ? $f['conditional'] : [],
                'placeholder' => sanitize_text_field($f['placeholder'] ?? ''),
                'maxlength'   => isset($f['maxlength']) ? intval($f['maxlength']) : null,
            ];
        }

        update_option(self::OPTION_KEY, $sanitized);
        wp_send_json_success();
    }

    /**
     * Devuelve los campos actuales (para JS)
     */
    public static function get_fields() {
        $fields = get_option(self::OPTION_KEY, []);
        return is_array($fields) ? $fields : [];
    }
}

GDM_Fields_Admin::init();