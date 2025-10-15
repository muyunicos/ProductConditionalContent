<?php
/**
 * Custom Post Types
 * Compatible con WordPress 6.8.3, PHP 8.2
 * 
 * Configuración correcta del menú sin duplicaciones:
 * - gdm_regla: Menú principal con icono
 * - gdm_opcion: Submenú bajo gdm_regla
 * 
 * @package ProductConditionalContent
 * @since 5.0.1
 */
if (!defined('ABSPATH')) exit;

final class GDM_CPT {
    public static function initialize_custom_post_types() {
        add_action('init', [__CLASS__, 'register_cpts']);
        add_filter('parent_file', [__CLASS__, 'fix_menu_highlight']);
    }

    public static function register_cpts() {
        // CPT para Reglas de Contenido (Menú Principal)
        register_post_type('gdm_regla', [
            'labels' => [
                'name'               => __('Reglas de Contenido', 'product-conditional-content'),
                'singular_name'      => __('Regla de Contenido', 'product-conditional-content'),
                'menu_name'          => __('Reglas de Contenido', 'product-conditional-content'),
                'all_items'          => __('Todas las Reglas', 'product-conditional-content'),
                'add_new'            => __('Agregar Nueva', 'product-conditional-content'),
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
            'show_in_menu'       => true,
            'menu_position'      => 56, // Después de WooCommerce (55)
            'menu_icon'          => 'dashicons-filter',
            'supports'           => ['title'],
            'capability_type'    => 'post',
            'hierarchical'       => false,
            'has_archive'        => false,
            'rewrite'            => false,
        ]);

        // CPT para Opciones de Producto (Submenú)
        register_post_type('gdm_opcion', [
            'labels' => [
                'name'               => __('Opciones de Producto', 'product-conditional-content'),
                'singular_name'      => __('Opción de Producto', 'product-conditional-content'),
                'menu_name'          => __('Opciones de Producto', 'product-conditional-content'),
                'all_items'          => __('Opciones de Producto', 'product-conditional-content'),
                'add_new'            => __('Agregar Nueva', 'product-conditional-content'),
                'add_new_item'       => __('Agregar Nueva Opción', 'product-conditional-content'),
                'edit_item'          => __('Editar Opción', 'product-conditional-content'),
                'new_item'           => __('Nueva Opción', 'product-conditional-content'),
                'view_item'          => __('Ver Opción', 'product-conditional-content'),
                'search_items'       => __('Buscar Opciones', 'product-conditional-content'),
                'not_found'          => __('No se encontraron opciones', 'product-conditional-content'),
                'not_found_in_trash' => __('No hay opciones en la papelera', 'product-conditional-content'),
            ],
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => 'edit.php?post_type=gdm_regla',
            'supports'           => ['title'],
            'capability_type'    => 'post',
            'hierarchical'       => false,
            'has_archive'        => false,
            'rewrite'            => false,
        ]);
    }

    /**
     * Arreglar el resaltado del menú cuando se está en gdm_opcion
     * 
     * @param string $parent_file Archivo padre actual
     * @return string Archivo padre corregido
     */
    public static function fix_menu_highlight($parent_file) {
        global $current_screen;
        
        if ($current_screen && $current_screen->post_type === 'gdm_opcion') {
            $parent_file = 'edit.php?post_type=gdm_regla';
        }
        
        return $parent_file;
    }
}

GDM_CPT::initialize_custom_post_types();