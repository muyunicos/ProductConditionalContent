<?php
/**
 * Plugin Name:       Motor de Reglas de Contenido MuyUnicos
 * Description:       Aplica contenido dinámico a productos basado en un sistema de reglas avanzado.
 * Version:           4.0.0
 * Author:            Muy Únicos
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * WC requires at least: 9.0
 * WC tested up to:   10.2.2
 */

if (!defined('ABSPATH')) exit;

// Constantes globales
define('GDM_VERSION', '4.0.0');
define('GDM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GDM_PLUGIN_URL', plugin_dir_url(__FILE__));

// Hook para declarar compatibilidad HPOS
add_action('before_woocommerce_init', function() {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

// Cargar solo lo necesario según el contexto
if (is_admin()) {
    // SOLO en admin - cargar clase de administración
    require_once GDM_PLUGIN_DIR . 'includes/class-gdm-admin.php';
    GDM_Admin::instance();
} else {
    // SOLO en frontend - cargar clase de frontend
    require_once GDM_PLUGIN_DIR . 'includes/class-gdm-frontend.php';
    GDM_Frontend::instance();
}