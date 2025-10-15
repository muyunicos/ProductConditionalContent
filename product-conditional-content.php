<?php
/**
 * Plugin Name: Reglas de Contenido para WooCommerce
 * Description: Motor profesional de reglas y campos personalizados con sistema modular para productos WooCommerce
 * Version: 6.2.1
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

/** --- Constantes globales (ANTES de verificar compatibilidad) --- */
define('GDM_VERSION', '6.2.1');
define('GDM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GDM_PLUGIN_URL', plugin_dir_url(__FILE__));

/** --- Cargar clase de compatibilidad --- */
require_once GDM_PLUGIN_DIR . 'includes/compatibility/class-compat-check.php';

/**
 * ✅ CORRECCIÓN: Inicializar en plugins_loaded con prioridad correcta
 */
add_action('plugins_loaded', function() {
    /** --- Verificar compatibilidad antes de cargar --- */
    $compat_result = GDM_Compat_Check::check();
    
    if (!$compat_result['compatible']) {
        GDM_Compat_Check::show_admin_notice($compat_result['messages']);
        return;
    }

    /** --- Declarar compatibilidad HPOS --- */
    GDM_Compat_Check::declare_hpos_compatibility(__FILE__);
    
    /** --- ✅ NUEVO: Cargar traducciones en el momento correcto --- */
    load_plugin_textdomain(
        'product-conditional-content',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );

    /** --- Inicialización Core --- */
    require_once GDM_PLUGIN_DIR . 'includes/core/class-plugin-bootstrap.php';
    require_once GDM_PLUGIN_DIR . 'includes/core/class-custom-post-types.php';

    /** --- Sistema Modular de Módulos --- */
    require_once GDM_PLUGIN_DIR . 'includes/admin/modules/class-module-base.php';
    require_once GDM_PLUGIN_DIR . 'includes/admin/modules/class-module-manager.php';
    GDM_Module_Manager::instance();
    
    /** --- Sistema Modular de Ámbitos --- */
    require_once GDM_PLUGIN_DIR . 'includes/admin/scopes/class-scope-base.php';
    require_once GDM_PLUGIN_DIR . 'includes/admin/scopes/class-scope-manager.php';
    GDM_Scope_Manager::instance();
    
    do_action('gdm_init_modules');
    do_action('gdm_init_scopes');

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
}, 10); // ✅ Prioridad 10 para asegurar que WooCommerce ya cargó

/**
 * ✅ CORRECCIÓN: Cron independiente con validación
 */
add_action('init', function() {
    if (!wp_next_scheduled('gdm_check_regla_schedules')) {
        wp_schedule_event(time(), 'hourly', 'gdm_check_regla_schedules');
    }
}, 15);

register_activation_hook(__FILE__, function() {
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function() {
    $timestamp = wp_next_scheduled('gdm_check_regla_schedules');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'gdm_check_regla_schedules');
    }
    flush_rewrite_rules();
});