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
        // Hooks y filtros globales
        add_filter('gdm_product_has_rules', [$this, 'default_has_rules'], 10, 2);
        add_filter('gdm_product_has_custom_fields', [$this, 'default_has_fields'], 10, 2);

        // Encolar estilos compartidos
        add_action('admin_enqueue_scripts', [$this, 'enqueue_shared_styles_admin']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_shared_styles_frontend']);
    }

    public function default_has_rules($has, $product_id) {
        $rules = get_post_meta($product_id, '_gdm_product_rules', true);
        return !empty($rules);
    }

    public function default_has_fields($has, $product_id) {
        $fields = get_post_meta($product_id, '_gdm_product_fields', true);
        return !empty($fields);
    }

    public function enqueue_shared_styles_admin($hook) {
        if (in_array($hook, ['post.php', 'post-new.php'])) {
            wp_enqueue_style(
                'gdm-shared-admin',
                GDM_PLUGIN_URL . 'assets/admin/css/shared-admin.css',
                [],
                GDM_VERSION
            );
        }
    }

    public function enqueue_shared_styles_frontend() {
        if (is_product()) {
            wp_enqueue_style(
                'gdm-shared-frontend',
                GDM_PLUGIN_URL . 'assets/frontend/css/shared-frontend.css',
                [],
                GDM_VERSION
            );
        }
    }
}