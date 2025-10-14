<?php
/**
 * Estructura de Menú del Admin
 * Compatible con WordPress 6.8.3
 */
if (!defined('ABSPATH')) exit;

final class GDM_Admin_Menu {
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'register_menu'], 9);
    }

    public static function register_menu() {
        // Menú principal - redirige a listado de reglas
        add_menu_page(
            __('Reglas de Contenido', 'product-conditional-content'),
            __('Reglas de Contenido', 'product-conditional-content'),
            'manage_options',
            'edit.php?post_type=gdm_regla', // CORREGIDO: apunta directamente al listado
            '',
            'dashicons-filter',
            25
        );

        // Submenú: Todas las Reglas (redundante pero necesario para estructura)
        add_submenu_page(
            'edit.php?post_type=gdm_regla',
            __('Todas las Reglas', 'product-conditional-content'),
            __('Todas las Reglas', 'product-conditional-content'),
            'manage_options',
            'edit.php?post_type=gdm_regla'
        );

        // Submenú: Agregar Nueva Regla
        add_submenu_page(
            'edit.php?post_type=gdm_regla',
            __('Agregar Nueva Regla', 'product-conditional-content'),
            __('Agregar Nueva', 'product-conditional-content'),
            'manage_options',
            'post-new.php?post_type=gdm_regla'
        );

        // Separador visual (usando submenu deshabilitado)
        add_submenu_page(
            'edit.php?post_type=gdm_regla',
            '',
            '<span style="display:block; margin: 5px 0; padding: 0; height: 1px; background: #dcdcde;"></span>',
            'manage_options',
            '#',
            '',
            10
        );

        // Submenú: Opciones de Producto (antes "Campos Personalizados")
        add_submenu_page(
            'edit.php?post_type=gdm_regla',
            __('Opciones de Producto', 'product-conditional-content'),
            __('Opciones de Producto', 'product-conditional-content'),
            'manage_options',
            'edit.php?post_type=gdm_opcion'
        );

        // Submenú: Agregar Nueva Opción
        add_submenu_page(
            'edit.php?post_type=gdm_regla',
            __('Agregar Nueva Opción', 'product-conditional-content'),
            __('Nueva Opción', 'product-conditional-content'),
            'manage_options',
            'post-new.php?post_type=gdm_opcion'
        );
    }
}
GDM_Admin_Menu::init();