<?php
/**
 * Gestor Principal de Estados para Reglas (REFACTORIZADO)
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
    }
    
    /**
     * Cargar archivos de componentes
     */
    private static function load_components() {
        $components_dir = GDM_PLUGIN_DIR . 'includes/admin/managers/status/';
        
        require_once $components_dir . 'class-status-core.php';
        require_once $components_dir . 'class-status-columns.php';
        require_once $components_dir . 'class-status-metabox.php';
        require_once $components_dir . 'class-status-ajax.php';
        require_once $components_dir . 'class-status-filters.php';
        require_once $components_dir . 'class-status-cron.php';
    }
    
    /**
     * Inicializar componentes
     */
    private static function init_components() {
        self::$core = new GDM_Rules_Status_Core();
        self::$columns = new GDM_Rules_Status_Columns();
        self::$metabox = new GDM_Rules_Status_Metabox();
        self::$ajax = new GDM_Rules_Status_Ajax();
        self::$filters = new GDM_Rules_Status_Filters();
        self::$cron = new GDM_Rules_Status_Cron();
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
}

// Inicializar el sistema
GDM_Regla_Status_Manager::init();