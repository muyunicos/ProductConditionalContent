<?php
/**
 * Inicialización global del plugin Motor de Reglas de Contenido MuyUnicos
 * - Hooks, filtros y utilidades compartidas por admin y frontend.
 * - Registro de shortcodes globales (si aplica).
 * - Helpers y funciones utilitarias.
 */

if (!defined('ABSPATH')) exit;

final class GDM_Plugin_Init {
    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Acceso global al singleton
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Registro de hooks y filtros globales
        add_action('init', [$this, 'load_textdomain']);
        add_filter('gdm_product_has_rules', [$this, 'default_has_rules'], 10, 2);
        add_filter('gdm_product_has_custom_fields', [$this, 'default_has_fields'], 10, 2);

        // (Opcional) Registro de shortcodes globales
        // add_shortcode('campo-cond', [$this, 'shortcode_campo_cond']);
    }

    /**
     * Cargar traducciones del plugin
     */
    public function load_textdomain() {
        load_plugin_textdomain('product-conditional-content', false, dirname(dirname(plugin_basename(__FILE__))) . '/languages/');
    }

    /**
     * Filtro por defecto para saber si un producto tiene reglas.
     * Puede ser sobrescrito por otros plugins o el theme.
     */
    public function default_has_rules($has_rules, $product_id) {
        // Por defecto busca si el producto tiene alguna meta asociada a reglas
        // Puedes personalizar esta lógica según cómo guardes las reglas
        $rules = get_post_meta($product_id, '_gdm_reglas_contenido', true);
        return !empty($rules);
    }

    /**
     * Filtro por defecto para saber si un producto tiene campos personalizados.
     */
    public function default_has_fields($has_fields, $product_id) {
        // Por defecto busca si el producto tiene alguna meta asociada a campos personalizados
        // Puedes personalizar esta lógica según cómo guardes los campos
        $fields = get_post_meta($product_id, '_gdm_campos_personalizados', true);
        return !empty($fields);
    }

    /**
     * (Opcional) Ejemplo de implementación de shortcode global
     */
    /*
    public function shortcode_campo_cond($atts) {
        $atts = shortcode_atts(['id' => ''], $atts, 'campo-cond');
        if (empty($atts['id'])) return '';
        // Aquí podrías retornar el HTML del campo personalizado
        return '<span class="gdm-campo-cond" data-id="' . esc_attr($atts['id']) . '"></span>';
    }
    */

}

// Inicializar el singleton globalmente
GDM_Plugin_Init::instance();