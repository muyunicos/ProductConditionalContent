<?php
/**
 * Plugin Name:       Reglas de Contenido para WooCommerce
 * Description:       Aplica contenido dinámico a productos basado en reglas y opciones personalizadas condicionales.
 * Version:           5.0.1
 * Author:            Muy Únicos
 * Requires at least: 6
 * Requires PHP:      8.2
 * WC requires at least: 10
 * WC tested up to:   10.2.2
 * License:           GPLv3
 * Text Domain:       product-conditional-content
 */

if (!defined('ABSPATH')) exit;

/** --- Compatibilidad y declaración de requisitos --- */
function gdm_check_plugin_compat() {
    global $wp_version;
    $min_wp     = '6';
    $min_php    = '8.2';
    $min_wc     = '10.0.0';
    $error_msgs = [];

    if (version_compare($wp_version, $min_wp, '<')) {
        $error_msgs[] = "WordPress $min_wp+";
    }
    if (version_compare(PHP_VERSION, $min_php, '<')) {
        $error_msgs[] = "PHP $min_php+";
    }
    if (!defined('WC_VERSION')) {
        $error_msgs[] = "WooCommerce $min_wc+ (WooCommerce no está activo)";
    } elseif (version_compare(WC_VERSION, $min_wc, '<')) {
        $error_msgs[] = "WooCommerce $min_wc+";
    }

    if ($error_msgs) {
        add_action('admin_notices', function() use ($error_msgs) {
            echo '<div class="notice notice-error"><p><b>Reglas de Contenido para WooCommerce:</b> Requiere: '
                . implode(', ', $error_msgs) . '.</p></div>';
        });
        return false;
    }
    return true;
}

add_action('plugins_loaded', function() {
    if (!gdm_check_plugin_compat()) return;

    /** --- Constantes globales --- */
    define('GDM_VERSION', '5.0.1');
    define('GDM_PLUGIN_DIR', plugin_dir_path(__FILE__));
    define('GDM_PLUGIN_URL', plugin_dir_url(__FILE__));

    /** --- Compatibilidad HPOS --- */
    add_action('before_woocommerce_init', function() {
        if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        }
    });

    /** --- Inicialización global --- */
    require_once GDM_PLUGIN_DIR . 'includes/core/class-plugin-init.php';
    require_once GDM_PLUGIN_DIR . 'includes/core/class-cpt.php';

    /** --- Carga según contexto --- */
    if (is_admin()) {
        require_once GDM_PLUGIN_DIR . 'includes/admin/class-admin-helpers.php';
        require_once GDM_PLUGIN_DIR . 'includes/admin/class-fields-admin.php';
        require_once GDM_PLUGIN_DIR . 'includes/admin/class-rules-admin.php';
        require_once GDM_PLUGIN_DIR . 'includes/admin/class-meta-boxes.php';
        require_once GDM_PLUGIN_DIR . 'includes/admin/class-opciones-metabox.php';
    } else {
        require_once GDM_PLUGIN_DIR . 'includes/frontend/class-rules-frontend.php';
        require_once GDM_PLUGIN_DIR . 'includes/frontend/class-fields-frontend.php';
        require_once GDM_PLUGIN_DIR . 'includes/frontend/class-shortcodes.php';
    }
}, 20);