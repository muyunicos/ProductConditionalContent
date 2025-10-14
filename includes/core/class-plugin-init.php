<?php
if (!defined('ABSPATH')) exit;

final class GDM_Plugin_Init {
    private static $instance = null;

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

        // Encolar shared-styles.css en admin y frontend
        add_action('admin_enqueue_scripts', [$this, 'enqueue_shared_styles_admin']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_shared_styles_frontend']);
    }

    public function load_textdomain() {
        load_plugin_textdomain('product-conditional-content', false, dirname(dirname(plugin_basename(__FILE__))) . '/languages/');
    }

    public function default_has_rules($has_rules, $product_id) {
        $rules = get_post_meta($product_id, '_gdm_reglas_contenido', true);
        return !empty($rules);
    }

    public function default_has_fields($has_fields, $product_id) {
        $fields = get_post_meta($product_id, '_gdm_campos_personalizados', true);
        return !empty($fields);
    }

    // Encolar shared-styles.css en admin
    public function enqueue_shared_styles_admin() {
        wp_enqueue_style('gdm-shared-styles', GDM_PLUGIN_URL . 'assets/shared/shared-styles.css', [], GDM_VERSION);
    }

    // Encolar shared-styles.css en frontend si es producto o página relevante
    public function enqueue_shared_styles_frontend() {
        if (is_product() || is_cart() || is_checkout()) {
            wp_enqueue_style('gdm-shared-styles', GDM_PLUGIN_URL . 'assets/shared/shared-styles.css', [], GDM_VERSION);
        }
    }
}

GDM_Plugin_Init::instance();