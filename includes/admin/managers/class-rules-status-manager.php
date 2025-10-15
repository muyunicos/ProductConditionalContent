<?php
/**
 * Gestor Principal de Estados para Reglas (REFACTORIZADO COMPLETO)
 * Coordina los diferentes componentes del sistema de estados
 * Compatible con WordPress 6.8.3, PHP 8.2
 * 
 * @package ProductConditionalContent
 * @since 6.3.0
 */

if (!defined('ABSPATH')) exit;

final class GDM_Regla_Status_Manager {
    
    /**
     * Instancias de componentes
     */
    private static $core = null;
    private static $columns = null;
    private static $metabox = null;
    private static $ajax = null;
    private static $filters = null;
    private static $cron = null;
    
    /**
     * Inicializar el sistema completo
     */
    public static function init() {
        // Cargar componentes
        self::load_components();
        
        // Inicializar cada componente
        self::init_components();
        
        // Registrar hooks de compatibilidad
        self::register_compatibility_hooks();
    }
    
    /**
     * Cargar archivos de componentes
     */
    private static function load_components() {
        $components_dir = GDM_PLUGIN_DIR . 'includes/admin/managers/status/';
        
        // Verificar que el directorio existe
        if (!is_dir($components_dir)) {
            wp_mkdir_p($components_dir);
        }
        
        $components = [
            'class-status-core.php',
            'class-status-columns.php', 
            'class-status-metabox.php',
            'class-status-ajax.php',
            'class-status-filters.php',
            'class-status-cron.php',
        ];
        
        foreach ($components as $component_file) {
            $file_path = $components_dir . $component_file;
            if (file_exists($file_path)) {
                require_once $file_path;
            } else {
                error_log("GDM: Componente no encontrado: {$file_path}");
            }
        }
    }
    
    /**
     * Inicializar componentes
     */
    private static function init_components() {
        if (class_exists('GDM_Rules_Status_Core')) {
            self::$core = new GDM_Rules_Status_Core();
        }
        
        if (class_exists('GDM_Rules_Status_Columns')) {
            self::$columns = new GDM_Rules_Status_Columns();
        }
        
        if (class_exists('GDM_Rules_Status_Metabox')) {
            self::$metabox = new GDM_Rules_Status_Metabox();
        }
        
        if (class_exists('GDM_Rules_Status_Ajax')) {
            self::$ajax = new GDM_Rules_Status_Ajax();
        }
        
        if (class_exists('GDM_Rules_Status_Filters')) {
            self::$filters = new GDM_Rules_Status_Filters();
        }
        
        if (class_exists('GDM_Rules_Status_Cron')) {
            self::$cron = new GDM_Rules_Status_Cron();
        }
    }
    
    /**
     * Registrar hooks de compatibilidad para mantener API existente
     */
    private static function register_compatibility_hooks() {
        // Hook para calcular sub-estados (usado por otros componentes)
        add_filter('gdm_calculate_substatus_gdm_regla', [__CLASS__, 'calculate_substatus'], 10, 3);
        
        // Hook para verificar programaciones (usado por cron)
        add_action('gdm_check_regla_schedules', [__CLASS__, 'check_schedules']);
    }
    
    /**
     * Obtener instancia de componente
     */
    public static function get_component($component) {
        switch ($component) {
            case 'core': return self::$core;
            case 'columns': return self::$columns;
            case 'metabox': return self::$metabox;
            case 'ajax': return self::$ajax;
            case 'filters': return self::$filters;
            case 'cron': return self::$cron;
            default: return null;
        }
    }
    
    /**
     * Métodos de compatibilidad (mantener API existente)
     */
    public static function calculate_substatus($default, $post_id, $status) {
        return self::$core ? self::$core->calculate_substatus($default, $post_id, $status) : [];
    }
    
    public static function check_schedules() {
        if (self::$cron) {
            self::$cron->check_schedules();
        }
    }
    
    /**
     * Helper: Verificar si el sistema está completamente cargado
     */
    public static function is_loaded() {
        return self::$core !== null;
    }
    
    /**
     * Helper: Obtener estadísticas del sistema
     */
    public static function get_system_stats() {
        if (!self::is_loaded()) {
            return [];
        }
        
        $stats = [
            'components_loaded' => 0,
            'total_components' => 6,
        ];
        
        $components = ['core', 'columns', 'metabox', 'ajax', 'filters', 'cron'];
        foreach ($components as $component) {
            if (self::get_component($component) !== null) {
                $stats['components_loaded']++;
            }
        }
        
        return $stats;
    }
}

// Inicializar el sistema refactorizado
GDM_Regla_Status_Manager::init();