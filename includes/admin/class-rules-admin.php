<?php
if (!defined('ABSPATH')) exit;

/**
 * Clase para gestionar reglas de contenido en el admin.
 *
 * Responsabilidad SOLA: UI y gestión de reglas de contenido en el admin.
 * - Permite agregar, editar, borrar y guardar reglas y variantes condicionales vía AJAX.
 * - Carga JS/CSS solo en la página correspondiente.
 * - Sanitiza y valida los datos antes de guardar.
 * - NO debe registrar submenús (lo hace class-admin-menu.php).
 */
final class GDM_Rules_Admin {
    const OPTION_KEY = 'gdm_content_rules';

    public static function init() {
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
        add_action('wp_ajax_gdm_save_rules', [__CLASS__, 'ajax_save_rules']);
    }

    /**
     * Carga los assets solo en la página de reglas
     */
    public static function enqueue_scripts($hook) {
        $screen = get_current_screen();
        if ($screen && ($screen->id === 'gdm_content_rules_page_gdm_content_rules_list' || $screen->id === 'toplevel_page_gdm_content_rules')) {
            wp_enqueue_script('gdm-rules-admin', GDM_PLUGIN_URL . 'assets/admin/rules-admin.js', ['jquery'], GDM_VERSION, true);
            wp_enqueue_style('gdm-rules-admin', GDM_PLUGIN_URL . 'assets/admin/rules-admin.css', [], GDM_VERSION);
            wp_localize_script('gdm-rules-admin', 'gdmRulesAdmin', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('gdm_rules_admin_nonce'),
                'rules'   => self::get_rules(),
            ]);
        }
    }

    /**
     * Página principal de gestión de reglas y variantes (solo admin)
     */
    public static function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Reglas de Contenido', 'product-conditional-content'); ?></h1>
            <form id="gdm-rules-form" method="post" autocomplete="off">
                <table class="wp-list-table widefat fixed striped" id="gdm-rules-table">
                    <thead>
                        <tr>
                            <th style="width:32px;"><input type="checkbox" id="gdm-select-all-rules" /></th>
                            <th><?php esc_html_e('ID', 'product-conditional-content'); ?></th>
                            <th><?php esc_html_e('Nombre', 'product-conditional-content'); ?></th>
                            <th><?php esc_html_e('Prioridad', 'product-conditional-content'); ?></th>
                            <th><?php esc_html_e('Condiciones', 'product-conditional-content'); ?></th>
                            <th><?php esc_html_e('Estado', 'product-conditional-content'); ?></th>
                            <th><?php esc_html_e('Editar', 'product-conditional-content'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Las filas se rellenan por JS -->
                    </tbody>
                </table>
                <div class="gdm-rules-actions">
                    <button type="button" class="button" id="gdm-add-rule"><?php esc_html_e('Agregar Regla', 'product-conditional-content'); ?></button>
                    <button type="button" class="button button-secondary" id="gdm-delete-selected-rules" disabled><?php esc_html_e('Eliminar Seleccionadas', 'product-conditional-content'); ?></button>
                    <button type="submit" class="button button-primary"><?php esc_html_e('Guardar Reglas', 'product-conditional-content'); ?></button>
                </div>
            </form>
            <div id="gdm-rules-saved" style="display:none;margin-top:15px;" class="notice notice-success"><p><?php esc_html_e('¡Reglas guardadas!', 'product-conditional-content'); ?></p></div>
            <!-- Modal de edición avanzada de regla -->
            <div id="gdm-rule-modal" style="display:none;">
                <!-- El contenido lo maneja rules-admin.js -->
            </div>
            <!-- Plantilla Underscore.js para filas de la tabla -->
            <script type="text/template" id="gdm-rule-row-template">
                <tr>
                    <td><input type="checkbox" class="gdm-rule-row-checkbox" /></td>
                    <td><%= id %></td>
                    <td><%= name %></td>
                    <td><%= priority %></td>
                    <td><%= conditions %></td>
                    <td><%= enabled ? '<?php esc_html_e('Activa', 'product-conditional-content'); ?>' : '<?php esc_html_e('Inactiva', 'product-conditional-content'); ?>' %></td>
                    <td>
                        <button type="button" class="button gdm-edit-rule"><?php esc_html_e('Editar', 'product-conditional-content'); ?></button>
                    </td>
                </tr>
            </script>
        </div>
        <?php
    }

    /**
     * Guarda las reglas por AJAX
     */
    public static function ajax_save_rules() {
        if (!current_user_can('manage_options')) wp_send_json_error('Sin permisos');
        check_ajax_referer('gdm_rules_admin_nonce', 'nonce');
        $rules = isset($_POST['rules']) ? json_decode(stripslashes($_POST['rules']), true) : [];
        if (!is_array($rules)) wp_send_json_error('Formato inválido');

        $sanitized = [];
        foreach ($rules as $r) {
            $sanitized[] = [
                'id'        => sanitize_key($r['id'] ?? ''),
                'name'      => sanitize_text_field($r['name'] ?? ''),
                'priority'  => isset($r['priority']) ? intval($r['priority']) : 10,
                'conditions'=> sanitize_text_field($r['conditions'] ?? ''),
                'enabled'   => !empty($r['enabled']),
            ];
        }
        update_option(self::OPTION_KEY, $sanitized);
        wp_send_json_success();
    }

    /**
     * Devuelve las reglas actuales para JS
     */
    public static function get_rules() {
        $rules = get_option(self::OPTION_KEY, []);
        return is_array($rules) ? $rules : [];
    }
}

GDM_Rules_Admin::init();