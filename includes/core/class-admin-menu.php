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
        $main_slug = 'gdm_content_rules';

        // Menú principal
        add_menu_page(
            __('Reglas de Contenido', 'product-conditional-content'),
            __('Reglas de Contenido', 'product-conditional-content'),
            'manage_options',
            $main_slug,
            '', // El callback puede quedar vacío porque el primer submenú lo genera WordPress automáticamente
            'dashicons-filter',
            25
        );

        // Submenús secundarios
        $submenus = [
            [
                'parent_slug' => $main_slug,
                'page_title'  => __('Reglas de Contenido', 'product-conditional-content'),
                'menu_title'  => __('Reglas de Contenido', 'product-conditional-content'),
                'capability'  => 'manage_options',
                'menu_slug'   => 'gdm_content_rules_list',
                'callback'    => [\GDM_Rules_Admin::class, 'admin_page'],
                'position'    => 1,
            ],
            [
                'parent_slug' => $main_slug,
                'page_title'  => __('Agregar Regla', 'product-conditional-content'),
                'menu_title'  => __('Agregar Regla', 'product-conditional-content'),
                'capability'  => 'manage_options',
                'menu_slug'   => 'gdm_add_rule',
                'callback'    => [\GDM_Rules_Admin::class, 'add_page'],
                'position'    => 2,
            ],
            [
                'parent_slug' => $main_slug,
                'page_title'  => __('Campos Personalizados', 'product-conditional-content'),
                'menu_title'  => __('Campos Personalizados', 'product-conditional-content'),
                'capability'  => 'manage_options',
                'menu_slug'   => 'gdm_product_fields',
                'callback'    => [\GDM_Fields_Admin::class, 'admin_page'],
                'position'    => 3,
            ],
            [
                'parent_slug' => $main_slug,
                'page_title'  => __('Agregar Campo', 'product-conditional-content'),
                'menu_title'  => __('Agregar Campo', 'product-conditional-content'),
                'capability'  => 'manage_options',
                'menu_slug'   => 'gdm_add_field',
                'callback'    => [\GDM_Fields_Admin::class, 'add_page'],
                'position'    => 4,
            ],
        ];

        // Permite que otros módulos o plugins añadan/quiten submenús fácilmente
        $submenus = apply_filters('gdm_admin_submenus', $submenus);

        // Añadir todos los submenús manualmente (excepto el principal)
        foreach ($submenus as $submenu) {
            if ($submenu['menu_slug'] === $main_slug) continue;
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