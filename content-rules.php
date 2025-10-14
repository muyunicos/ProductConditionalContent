<?php
/**
 * Plugin Name:       Reglas de Contenido para WooCommerce
 * Description:       Aplica contenido dinámico a productos basado en reglas y campos personalizados condicionales.
 * Version:           5.0.0
 * Author:            Muy Únicos
 * Requires at least: 6
 * Requires PHP:      8.2
 * WC requires at least: 10
 * WC tested up to:   10.2.2
 * License:           GPLv3
 * Text Domain:       wc-content-rules
 */

if (!defined('ABSPATH')) exit;

/**
 * ==========================
 * Árbol de directorios (referencia)
 * 
 * product-conditional-content/
 * ├── content-rules.php                     # Archivo principal del plugin. Encargado de declaraciones, compatibilidad y carga modular.
 * ├── includes/
 * │   ├── core/
 * │   │   ├── class-plugin-init.php         # Inicialización global, hooks generales, helpers comunes.
 * │   │   └── class-admin-menu.php          # Definición del árbol de menús y permisos de acceso en el admin de WP.
 * │   ├── admin/
 * │   │   ├── class-fields-admin.php        # Lógica de gestión de campos personalizados (alta, edición, borrado, condiciones).
 * │   │   ├── class-rules-admin.php         # Lógica de gestión de reglas y variantes para productos (alta, edición, borrado).
 * │   │   └── class-meta-boxes.php          # Meta boxes extra para edición de productos, reglas, y campos.
 * │   ├── frontend/
 * │   │   ├── class-fields-frontend.php     # Renderizado de campos personalizados y lógica de interacción en la página de producto.
 * │   │   ├── class-rules-frontend.php      # Aplicación de reglas y variantes en el frontend; filtrado de contenido dinámico.
 * │   │   └── class-shortcodes.php          # Implementación de shortcodes (ej: [campo-cond id="..."]) para uso en contenido.
 * │   └── compatibility/
 * │       └── class-compat-check.php        # Comprobaciones de versión de WP, PHP, WooCommerce y dependencias, muestra avisos si no cumplen.
 * ├── assets/
 * │   ├── admin/
 * │   │   ├── fields-admin.js               # JS para el admin de campos personalizados (tablas, modals, validación).
 * │   │   ├── fields-admin.css              # CSS específico para el admin de campos personalizados.
 * │   │   ├── rules-admin.js                # JS para el admin de reglas y variantes.
 * │   │   └── rules-admin.css               # CSS específico para la gestión de reglas y variantes.
 * │   ├── frontend/
 * │   │   ├── fields-frontend.js            # JS para campos personalizados en el frontend (condiciones, validaciones, UX).
 * │   │   ├── fields-frontend.css           # CSS para el diseño de campos personalizados en el producto.
 * │   │   └── rules-frontend.js             # JS para lógica avanzada de reglas en el frontend.
 * │   └── shared/
 * │       └── shared-styles.css             # Estilos reutilizables por admin y frontend (helpers, utilidades, variables).
 * ├── languages/
 * │   └── product-conditional-content-es_ES.mo  # Traducciones.
 * └── readme.txt                            # Documentación para WordPress.org y desarrolladores.
 * ==========================
 */

/** --- Compatibilidad y declaración de requisitos --- */
function gdm_check_plugin_compat() {
    global $wp_version;
    $min_wp     = '6';
    $min_php    = '8.2'; // Mejor usar la misma versión que el plugin requiere
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
define('GDM_VERSION', '5.0.0');
define('GDM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GDM_PLUGIN_URL', plugin_dir_url(__FILE__));

/** --- Compatibilidad HPOS (WooCommerce High-Performance Order Storage) --- */
add_action('before_woocommerce_init', function() {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

/** --- Inicialización global y carga modular --- */
// Carga núcleo (hooks, helpers, utilidades)
require_once GDM_PLUGIN_DIR . 'includes/core/class-plugin-init.php';
require_once GDM_PLUGIN_DIR . 'includes/core/class-admin-menu.php';
require_once GDM_PLUGIN_DIR . '/includes/core/class-cpt.php';

// Carga lógica según contexto
if (is_admin()) {
    require_once GDM_PLUGIN_DIR . 'includes/admin/class-fields-admin.php';
    require_once GDM_PLUGIN_DIR . 'includes/admin/class-rules-admin.php';
    require_once GDM_PLUGIN_DIR . 'includes/admin/class-meta-boxes.php';
    require_once GDM_PLUGIN_DIR . 'includes/admin/class-opciones-metabox.php';
} else {
    // Frontend: carga selectiva solo si el producto necesita reglas o campos personalizados
    add_action('wp', function() {
        if (is_product()) {
            $product_id = get_the_ID();
            $has_rules  = apply_filters('gdm_product_has_rules', false, $product_id);
            $has_fields = apply_filters('gdm_product_has_custom_fields', false, $product_id);
            if ($has_rules) {
                require_once GDM_PLUGIN_DIR . 'includes/frontend/class-rules-frontend.php';
            }
            if ($has_fields) {
                require_once GDM_PLUGIN_DIR . 'includes/frontend/class-fields-frontend.php';
            }
            if ($has_rules || $has_fields) {
                require_once GDM_PLUGIN_DIR . 'includes/frontend/class-shortcodes.php';
            }
        }
    });
}

// Siempre carga comprobaciones de compatibilidad si existen
if (file_exists(GDM_PLUGIN_DIR . 'includes/compatibility/class-compat-check.php')) {
    require_once GDM_PLUGIN_DIR . 'includes/compatibility/class-compat-check.php';
}
});