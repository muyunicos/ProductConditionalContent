<?php
/**
 * Clase para declarar el menú y submenús del plugin en el admin de WordPress
 */

if (!defined('ABSPATH')) exit;

final class GDM_Admin_Menu {
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'register_menu'], 9);
    }

    /**
     * Registrar menú y submenús, usando filtros para que otros módulos puedan agregar más
     */
    public static function register_menu() {
        // Menú principal (puedes cambiar el 'menu_slug' en el futuro si lo deseas)
        $main_slug = 'gdm_content_rules';

        // Menú principal
        add_menu_page(
            __('Reglas de Contenido', 'product-conditional-content'), // Título página
            __('Reglas de Contenido', 'product-conditional-content'), // Título menú
            'manage_options',                                         // Capability
            $main_slug,                                               // Slug
            '',                                                       // Callback (puede quedar vacío si solo submenús)
            'dashicons-filter',                                       // Icono
            25                                                        // Posición
        );

        // Submenús básicos, puedes añadir más desde otros módulos usando el filtro
        $submenus = [
            [
                'parent_slug' => $main_slug,
                'page_title'  => __('Reglas de Contenido', 'product-conditional-content'),
                'menu_title'  => __('Reglas de Contenido', 'product-conditional-content'),
                'capability'  => 'manage_options',
                'menu_slug'   => 'gdm_content_rules_list',
                'callback'    => [self::class, 'submenu_placeholder'],
                'position'    => 1,
            ],
            [
                'parent_slug' => $main_slug,
                'page_title'  => __('Campos Personalizados', 'product-conditional-content'),
                'menu_title'  => __('Campos Personalizados', 'product-conditional-content'),
                'capability'  => 'manage_options',
                'menu_slug'   => 'gdm_product_fields',
                'callback'    => [self::class, 'submenu_placeholder'],
                'position'    => 2,
            ],
            // Puedes añadir más submenús aquí si lo necesitas
        ];

        /**
         * Permite que otros módulos o plugins añadan/quiten submenús fácilmente
         * Cada entrada debe ser igual a la estructura de $submenus[]
         */
        $submenus = apply_filters('gdm_admin_submenus', $submenus);

        // Añadir todos los submenús
        foreach ($submenus as $submenu) {
            add_submenu_page(
                $submenu['parent_slug'],
                $submenu['page_title'],
                $submenu['menu_title'],
                $submenu['capability'],
                $submenu['menu_slug'],
                $submenu['callback']
            );
        }
    }

    /**
     * Callback por defecto para submenús (placeholder)
     */
    public static function submenu_placeholder() {
        echo '<div class="wrap"><h1>' .
            esc_html(get_admin_page_title()) .
            '</h1><p>' .
            esc_html__('Esta sección será gestionada por el módulo correspondiente.', 'product-conditional-content') .
            '</p></div>';
    }
}

// Inicialización global
GDM_Admin_Menu::init();