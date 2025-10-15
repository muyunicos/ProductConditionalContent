<?php
/**
 * Plugin Name: Reglas de Contenido para WooCommerce
 * Description: Motor profesional de reglas y campos personalizados con sistema modular para productos WooCommerce
 * Version: 6.0.0
 * Author: MuyUnicos
 * Author URI: https://muyunicos.com
 * Text Domain: product-conditional-content
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * WC requires at least: 8.0
 * WC tested up to: 10.2.2
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

if (!defined('ABSPATH')) exit;

/**
 * Verificar compatibilidad antes de cargar
 */
function gdm_check_plugin_compat() {
    $min_wp = '6.0';
    $min_php = '8.0';
    $min_wc = '8.0';
    $error_msgs = [];

    if (version_compare(get_bloginfo('version'), $min_wp, '<')) {
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
    define('GDM_VERSION', '6.0.0'); // ✅ ACTUALIZADO
    define('GDM_PLUGIN_DIR', plugin_dir_path(__FILE__));
    define('GDM_PLUGIN_URL', plugin_dir_url(__FILE__));

    /** --- Compatibilidad HPOS --- */
    add_action('before_woocommerce_init', function() {
        if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        }
    });

    /** --- Inicialización Core --- */
    require_once GDM_PLUGIN_DIR . 'includes/core/class-plugin-init.php';
    require_once GDM_PLUGIN_DIR . 'includes/core/class-custom-post-types.php';

    /** --- Sistema Modular --- */
    require_once GDM_PLUGIN_DIR . 'includes/admin/modules/class-module-base.php';
    require_once GDM_PLUGIN_DIR . 'includes/admin/modules/class-module-manager.php';

    /** --- Carga según contexto --- */
    if (is_admin()) {
        require_once GDM_PLUGIN_DIR . 'includes/admin/class-admin-helpers.php';
        require_once GDM_PLUGIN_DIR . 'includes/admin/class-fields-admin.php';
        require_once GDM_PLUGIN_DIR . 'includes/admin/class-rules-admin.php';
        require_once GDM_PLUGIN_DIR . 'includes/admin/class-meta-boxes.php';
        require_once GDM_PLUGIN_DIR . 'includes/admin/class-opciones-metabox.php';
        require_once GDM_PLUGIN_DIR . 'includes/admin/class-regla-toggle-ajax.php';
        require_once GDM_PLUGIN_DIR . 'includes/admin/class-regla-status-manager.php';
    } else {
        require_once GDM_PLUGIN_DIR . 'includes/frontend/class-rules-frontend.php';
        require_once GDM_PLUGIN_DIR . 'includes/frontend/class-fields-frontend.php';
        require_once GDM_PLUGIN_DIR . 'includes/frontend/class-shortcodes.php';
    }
    
    /**
     * Activar cron para verificar programaciones
     */
    if (!wp_next_scheduled('gdm_check_regla_schedules')) {
        wp_schedule_event(time(), 'hourly', 'gdm_check_regla_schedules');
    }

    /**
     * Desactivar cron al desactivar el plugin
     */
    register_deactivation_hook(__FILE__, function() {
        wp_clear_scheduled_hook('gdm_check_regla_schedules');
    });
}, 20);