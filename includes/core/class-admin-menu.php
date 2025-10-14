<?php
/**
 * Clase para declarar el menú principal del plugin en el admin de WordPress.
 */
if (!defined('ABSPATH')) exit;

final class GDM_Admin_Menu {
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'register_menu'], 9);
        add_action('admin_menu', [__CLASS__, 'remove_duplicate_submenu'], 999);
    }

    public static function register_menu() {
        add_menu_page(
            __('Reglas de Contenido', 'product-conditional-content'),
            __('Reglas de Contenido', 'product-conditional-content'),
            'manage_options',
            'gdm_content_rules',
            [__CLASS__, 'redirect_to_first_cpt'],
            'dashicons-filter',
            25
        );
    }

    /**
     * Redirect parent menu to first CPT listing page
     */
    public static function redirect_to_first_cpt() {
        wp_redirect(admin_url('edit.php?post_type=gdm_regla'));
        exit;
    }

    /**
     * Remove the duplicate submenu item that WordPress adds for the parent menu
     */
    public static function remove_duplicate_submenu() {
        remove_submenu_page('gdm_content_rules', 'gdm_content_rules');
    }
}
GDM_Admin_Menu::init();