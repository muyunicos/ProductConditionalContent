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

/** --- Cargar clase de compatibilidad --- */
require_once plugin_dir_path(__FILE__) . 'includes/compatibility/class-compat-check.php';

add_action('plugins_loaded', function() {
    /** --- Verificar compatibilidad antes de cargar --- */
    $compat_result = GDM_Compat_Check::check();
    
    if (!$compat_result['compatible']) {
        GDM_Compat_Check::show_admin_notice($compat_result['messages']);
        return;
    }

    /** --- Constantes globales --- */
    define('GDM_VERSION', '6.0.0'); // ✅ ACTUALIZADO
    define('GDM_PLUGIN_DIR', plugin_dir_path(__FILE__));
    define('GDM_PLUGIN_URL', plugin_dir_url(__FILE__));

    /** --- Declarar compatibilidad HPOS --- */
    GDM_Compat_Check::declare_hpos_compatibility(__FILE__);

    /** --- Inicialización Core --- */
    require_once GDM_PLUGIN_DIR . 'includes/core/class-plugin-bootstrap.php';
    require_once GDM_PLUGIN_DIR . 'includes/core/class-custom-post-types.php';

    /** --- Sistema Modular --- */
    require_once GDM_PLUGIN_DIR . 'includes/admin/modules/class-module-base.php';
    require_once GDM_PLUGIN_DIR . 'includes/admin/modules/class-module-manager.php';

    /** --- Carga según contexto --- */
    if (is_admin()) {
        require_once GDM_PLUGIN_DIR . 'includes/admin/managers/class-admin-helpers.php';
        require_once GDM_PLUGIN_DIR . 'includes/admin/product-panels/class-product-options-panel.php';
        require_once GDM_PLUGIN_DIR . 'includes/admin/product-panels/class-product-rules-panel.php';
        require_once GDM_PLUGIN_DIR . 'includes/admin/metaboxes/class-rules-config-metabox.php';
        require_once GDM_PLUGIN_DIR . 'includes/admin/metaboxes/class-options-config-metabox.php';
        require_once GDM_PLUGIN_DIR . 'includes/admin/managers/class-ajax-toggle-handler.php';
        require_once GDM_PLUGIN_DIR . 'includes/admin/managers/class-rules-status-manager.php';
    } else {
        require_once GDM_PLUGIN_DIR . 'includes/frontend/class-rules-engine.php';
        require_once GDM_PLUGIN_DIR . 'includes/frontend/class-options-renderer.php';
        require_once GDM_PLUGIN_DIR . 'includes/frontend/class-shortcodes-handler.php';
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