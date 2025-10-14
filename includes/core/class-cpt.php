<?php
/**
 * Registro de Custom Post Types para Reglas y Campos Personalizados
 */
if (!defined('ABSPATH')) exit;

final class GDM_CPT {
    public static function init() {
        add_action('init', [__CLASS__, 'register_cpts']);
    }

    public static function register_cpts() {
        // CPT para Reglas de Contenido
        register_post_type('gdm_regla', [
            'labels' => [
                'name'               => __('Reglas de Contenido', 'product-conditional-content'),
                'singular_name'      => __('Regla de Contenido', 'product-conditional-content'),
                'menu_name'          => __('Reglas de Contenido', 'product-conditional-content'),
                'all_items'          => __('Reglas de Contenido', 'product-conditional-content'),
                'add_new'            => __('Agregar Regla', 'product-conditional-content'),
                'add_new_item'       => __('Agregar Nueva Regla', 'product-conditional-content'),
                'edit_item'          => __('Editar Regla', 'product-conditional-content'),
                'new_item'           => __('Nueva Regla', 'product-conditional-content'),
                'view_item'          => __('Ver Regla', 'product-conditional-content'),
                'search_items'       => __('Buscar Reglas', 'product-conditional-content'),
                'not_found'          => __('No se encontraron reglas', 'product-conditional-content'),
                'not_found_in_trash' => __('No hay reglas en la papelera', 'product-conditional-content'),
            ],
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => 'gdm_content_rules',
            'menu_position'      => 1,
            'menu_icon'          => 'dashicons-filter',
            'supports'           => ['title', 'editor', 'custom-fields'],
            'capability_type'    => 'post',
        ]);

        // CPT para Campos Personalizados
        register_post_type('gdm_campo', [
            'labels' => [
                'name'               => __('Campos Personalizados', 'product-conditional-content'),
                'singular_name'      => __('Campo Personalizado', 'product-conditional-content'),
                'menu_name'          => __('Campos Personalizados', 'product-conditional-content'),
                'all_items'          => __('Campos Personalizados', 'product-conditional-content'),
                'add_new'            => __('Agregar Campo', 'product-conditional-content'),
                'add_new_item'       => __('Agregar Nuevo Campo', 'product-conditional-content'),
                'edit_item'          => __('Editar Campo', 'product-conditional-content'),
                'new_item'           => __('Nuevo Campo', 'product-conditional-content'),
                'view_item'          => __('Ver Campo', 'product-conditional-content'),
                'search_items'       => __('Buscar Campos', 'product-conditional-content'),
                'not_found'          => __('No se encontraron campos', 'product-conditional-content'),
                'not_found_in_trash' => __('No hay campos en la papelera', 'product-conditional-content'),
            ],
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => 'gdm_content_rules',
            'menu_position'      => 3,
            'menu_icon'          => 'dashicons-list-view',
            'supports'           => ['title', 'custom-fields'],
            'capability_type'    => 'post',
        ]);
    }
}
GDM_CPT::init();