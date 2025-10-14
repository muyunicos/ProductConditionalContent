<?php
/**
 * Clase para declarar el menú principal del plugin en el admin de WordPress.
 */
if (!defined('ABSPATH')) exit;

final class GDM_Admin_Menu {
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'register_menu'], 9);
    }

    public static function register_menu() {
        add_menu_page(
            __('Reglas de Contenido', 'product-conditional-content'),
            __('Reglas de Contenido', 'product-conditional-content'),
            'manage_options',
            'gdm_content_rules',
            '',
            'dashicons-filter',
            25
        );
    }
}
GDM_Admin_Menu::init();