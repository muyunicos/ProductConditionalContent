<?php
/**
 * Gestor Centralizado de Módulos v6.1 CORREGIDO
 * Compatible con WordPress 6.8.3, PHP 8.2, WooCommerce 10.2.2
 * 
 * @package ProductConditionalContent
 * @since 6.1.0
 * @date 2025-10-15
 */

if (!defined('ABSPATH')) exit;

final class GDM_Module_Manager {
    
    private static $instance = null;
    private $modules = [];
    private $module_instances = [];
    
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor privado
     * ✅ CORRECCIÓN: Registrar módulos INMEDIATAMENTE, no en hooks
     */
    private function __construct() {
        // ✅ INMEDIATO: Registrar módulos del core
        $this->register_core_modules();
        
        // Permitir registro externo DESPUÉS
        add_action('init', [$this, 'allow_external_registration'], 6);
        
        // Inicializar módulos en init
        add_action('init', [$this, 'init_registered_modules'], 7);
    }
    
    /**
     * Registrar módulos del core
     * ✅ PÚBLICO: Se ejecuta desde el constructor
     */
    public function register_core_modules() {
        $modules_dir = GDM_PLUGIN_DIR . 'includes/admin/modules/';
        
        // Módulo de Descripción
        $this->register_module('descripcion', [
            'class' => 'GDM_Module_Descripcion',
            'label' => __('Descripción', 'product-conditional-content'),
            'icon' => '📄',
            'file' => $modules_dir . 'class-module-description.php',
            'enabled' => true,
            'priority' => 10,
        ]);
        
        // Módulo de Galería
        $this->register_module('galeria', [
            'class' => 'GDM_Module_Gallery',
            'label' => __('Galería', 'product-conditional-content'),
            'icon' => '🖼️',
            'file' => $modules_dir . 'class-module-gallery.php',
            'enabled' => true,
            'priority' => 15,
        ]);
        
        // Módulo de Título
        $this->register_module('titulo', [
            'class' => 'GDM_Module_Title',
            'label' => __('Título', 'product-conditional-content'),
            'icon' => '📝',
            'file' => $modules_dir . 'class-module-title.php',
            'enabled' => true,
            'priority' => 20,
        ]);
        
        // Módulo de Precio
        $this->register_module('precio', [
            'class' => 'GDM_Module_Price',
            'label' => __('Precio', 'product-conditional-content'),
            'icon' => '💰',
            'file' => $modules_dir . 'class-module-price.php',
            'enabled' => true,
            'priority' => 25,
        ]);
        
        // Módulo de Destacado
        $this->register_module('destacado', [
            'class' => 'GDM_Module_Featured',
            'label' => __('Destacado', 'product-conditional-content'),
            'icon' => '⭐',
            'file' => $modules_dir . 'class-module-featured.php',
            'enabled' => true,
            'priority' => 30,
        ]);
        
        // Hook para extensiones
        do_action('gdm_register_modules', $this);
    }
    
    /**
     * Permitir registro externo
     */
    public function allow_external_registration() {
        do_action('gdm_modules_init', $this);
    }
    
    /**
     * Inicializar módulos registrados
     * ✅ Solo se ejecuta en init, pero el REGISTRO ya está hecho
     */
    public function init_registered_modules() {
        foreach ($this->modules as $id => $config) {
            if (!$config['enabled']) {
                continue;
            }
            
            // Cargar archivo si existe
            if (!empty($config['file']) && file_exists($config['file'])) {
                require_once $config['file'];
            }
            
            // Instanciar clase
            if (class_exists($config['class'])) {
                try {
                    $this->module_instances[$id] = new $config['class']();
                } catch (Exception $e) {
                    error_log(sprintf(
                        'GDM Module Manager: Error al inicializar módulo "%s": %s',
                        $id,
                        $e->getMessage()
                    ));
                }
            } else {
                error_log(sprintf(
                    'GDM Module Manager: Clase "%s" no encontrada para módulo "%s"',
                    $config['class'],
                    $id
                ));
            }
        }
        
        do_action('gdm_modules_loaded', $this->module_instances);
    }
    
    /**
     * Registrar un módulo
     */
    public function register_module($id, $config = []) {
        if (empty($id)) {
            return false;
        }
        
        if (isset($this->modules[$id]) && empty($config['force'])) {
            return false;
        }
        
        $defaults = [
            'class' => '',
            'label' => ucfirst($id),
            'icon' => '⚙️',
            'file' => '',
            'enabled' => true,
            'priority' => 10,
            'description' => '',
        ];
        
        $config = wp_parse_args($config, $defaults);
        
        if (empty($config['class'])) {
            return false;
        }
        
        $this->modules[$id] = $config;
        
        return true;
    }
    
    /**
     * Desregistrar módulo
     */
    public function unregister_module($id) {
        if (isset($this->modules[$id])) {
            unset($this->modules[$id]);
            
            if (isset($this->module_instances[$id])) {
                unset($this->module_instances[$id]);
            }
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Obtener módulo por ID
     */
    public function get_module($id) {
        return $this->modules[$id] ?? null;
    }
    
    /**
     * Obtener todos los módulos
     */
    public function get_modules() {
        return $this->modules;
    }
    
    /**
     * Obtener módulos habilitados
     */
    public function get_enabled_modules() {
        return array_filter($this->modules, function($module) {
            return $module['enabled'] === true;
        });
    }
    
    /**
     * Obtener módulos con íconos para selector
     * ✅ Este método es llamado por el metabox
     */
    public function get_modules_with_icons() {
        $modules = [];
        
        foreach ($this->modules as $id => $config) {
            if ($config['enabled']) {
                $modules[$id] = [
                    'id' => $id,
                    'label' => $config['label'],
                    'icon' => $config['icon'],
                    'priority' => $config['priority'],
                    'description' => $config['description'] ?? '',
                ];
            }
        }
        
        // Ordenar por prioridad
        uasort($modules, function($a, $b) {
            return $a['priority'] - $b['priority'];
        });
        
        return $modules;
    }
    
    /**
     * Obtener instancia de módulo
     */
    public function get_module_instance($id) {
        return $this->module_instances[$id] ?? null;
    }
    
    /**
     * Verificar si módulo está registrado
     */
    public function is_module_registered($id) {
        return isset($this->modules[$id]);
    }
    
    /**
     * Verificar si módulo está habilitado
     */
    public function is_module_enabled($id) {
        return isset($this->modules[$id]) && $this->modules[$id]['enabled'] === true;
    }
    
    /**
     * Habilitar/deshabilitar módulo
     */
    public function set_module_status($id, $enabled = true) {
        if (isset($this->modules[$id])) {
            $this->modules[$id]['enabled'] = (bool) $enabled;
            return true;
        }
        
        return false;
    }
    
    /**
     * Obtener conteo de módulos
     */
    public function get_modules_count() {
        return [
            'total' => count($this->modules),
            'enabled' => count($this->get_enabled_modules()),
            'disabled' => count($this->modules) - count($this->get_enabled_modules()),
        ];
    }
    
    /**
     * Debug
     */
    public function debug_modules() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        echo '<pre>';
        echo "=== GDM Module Manager Debug ===\n\n";
        echo "Módulos registrados: " . count($this->modules) . "\n";
        echo "Módulos inicializados: " . count($this->module_instances) . "\n\n";
        
        foreach ($this->modules as $id => $config) {
            $status = $config['enabled'] ? '✅' : '❌';
            $loaded = isset($this->module_instances[$id]) ? '🟢' : '🔴';
            
            echo "{$status} {$loaded} {$config['icon']} {$id}\n";
            echo "   Clase: {$config['class']}\n";
            echo "   Label: {$config['label']}\n";
            echo "   Prioridad: {$config['priority']}\n";
            
            if (!empty($config['file'])) {
                echo "   Archivo: " . (file_exists($config['file']) ? '✓' : '✗') . " {$config['file']}\n";
            }
            
            echo "\n";
        }
        
        echo '</pre>';
    }
}

/**
 * Helper function
 */
function gdm_modules() {
    return GDM_Module_Manager::instance();
}

/**
 * Debug (solo desarrollo)
 */
if (defined('WP_DEBUG') && WP_DEBUG && is_admin()) {
    add_action('admin_footer', function() {
        $screen = get_current_screen();
        if ($screen && $screen->id === 'gdm_regla' && isset($_GET['debug_modules'])) {
            GDM_Module_Manager::instance()->debug_modules();
        }
    });
}