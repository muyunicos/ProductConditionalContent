<?php
/**
 * Uninstall Script
 * Limpia todos los datos del plugin cuando se desinstala
 * Compatible con WordPress 6.8.3, PHP 8.2, WooCommerce 10.2.2
 * 
 * @package ProductConditionalContent
 * @since 6.0.0
 * @author MuyUnicos
 * @date 2025-10-15
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {exit;}

/**
 * Eliminar Custom Post Types
 */
function gdm_delete_custom_posts() {
    global $wpdb;
    
    // Eliminar posts de tipo gdm_regla
    $reglas = get_posts([
        'post_type' => 'gdm_regla',
        'posts_per_page' => -1,
        'post_status' => 'any'
    ]);
    
    foreach ($reglas as $regla) {
        wp_delete_post($regla->ID, true);
    }
    
    // Eliminar posts de tipo gdm_opcion
    $opciones = get_posts([
        'post_type' => 'gdm_opcion',
        'posts_per_page' => -1,
        'post_status' => 'any'
    ]);
    
    foreach ($opciones as $opcion) {
        wp_delete_post($opcion->ID, true);
    }
}

/**
 * Eliminar opciones del plugin
 */
function gdm_delete_options() {
    global $wpdb;
    
    // Eliminar opciones específicas del plugin
    delete_option('gdm_version');
    delete_option('gdm_settings');
    delete_option('gdm_modules_enabled');
    
    // Eliminar opciones con prefijo gdm_
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'gdm_%'");
}

/**
 * Eliminar post meta
 */
function gdm_delete_post_meta() {
    global $wpdb;
    
    // Eliminar meta de productos
    $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_gdm_%'");
    $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE 'gdm_%'");
}

/**
 * Eliminar términos de taxonomía personalizados (si existen)
 */
function gdm_delete_taxonomies() {
    global $wpdb;
    
    // Si en el futuro se agregan taxonomías personalizadas, eliminarlas aquí
    // Por ahora, solo limpiamos posibles términos huérfanos
    $wpdb->query("DELETE FROM {$wpdb->term_taxonomy} WHERE taxonomy LIKE 'gdm_%'");
}

/**
 * Limpiar cron jobs
 */
function gdm_clear_cron_jobs() {
    wp_clear_scheduled_hook('gdm_check_regla_schedules');
}

/**
 * Limpiar caché
 */
function gdm_clear_cache() {
    // Limpiar caché de objetos
    wp_cache_flush();
    
    // Limpiar caché de transients
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_gdm_%' OR option_name LIKE '_transient_timeout_gdm_%'");
}

/**
 * Eliminar tablas personalizadas (si existen)
 */
function gdm_drop_custom_tables() {
    global $wpdb;
    
    // Por ahora no hay tablas personalizadas, pero se puede agregar en el futuro
    // Ejemplo: $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}gdm_custom_table");
}

/**
 * Ejecutar desinstalación
 */
function gdm_uninstall() {
    // Verificar permisos
    if (!current_user_can('activate_plugins')) {
        return;
    }
    
    // Limpiar cron jobs primero
    gdm_clear_cron_jobs();
    
    // Eliminar posts personalizados
    gdm_delete_custom_posts();
    
    // Eliminar opciones
    gdm_delete_options();
    
    // Eliminar post meta
    gdm_delete_post_meta();
    
    // Eliminar taxonomías
    gdm_delete_taxonomies();
    
    // Eliminar tablas personalizadas
    gdm_drop_custom_tables();
    
    // Limpiar caché
    gdm_clear_cache();
    
    // Limpiar rewrite rules
    flush_rewrite_rules();
}

// Ejecutar desinstalación
gdm_uninstall();