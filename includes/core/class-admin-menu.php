<?php
if (!defined('ABSPATH')) exit;

final class GDM_Admin_Menu {
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'register_menu'], 9);
    }

    public static function register_menu() {
        // Menú principal
        add_menu_page(
            __('Reglas de Contenido', 'product-conditional-content'),
            __('Reglas de Contenido', 'product-conditional-content'),
            'manage_options',
            'gdm_content_rules',
            '',
            'dashicons-filter',
            25
        );

        // Submenú: Listado de Reglas
        add_submenu_page(
            'gdm_content_rules',
            __('Reglas de Contenido', 'product-conditional-content'),
            __('Reglas de Contenido', 'product-conditional-content'),
            'manage_options',
            'edit.php?post_type=gdm_regla'
        );

        // Submenú: Agregar Regla
        add_submenu_page(
            'gdm_content_rules',
            __('Agregar Regla', 'product-conditional-content'),
            __('Agregar Regla', 'product-conditional-content'),
            'manage_options',
            'post-new.php?post_type=gdm_regla'
        );

        // Submenú: Listado de Campos
        add_submenu_page(
            'gdm_content_rules',
            __('Campos Personalizados', 'product-conditional-content'),
            __('Campos Personalizados', 'product-conditional-content'),
            'manage_options',
            'edit.php?post_type=gdm_campo'
        );

        // Submenú: Agregar Campo
        add_submenu_page(
            'gdm_content_rules',
            __('Agregar Campo', 'product-conditional-content'),
            __('Agregar Campo', 'product-conditional-content'),
            'manage_options',
            'post-new.php?post_type=gdm_campo'
        );
    }
}
GDM_Admin_Menu::init();