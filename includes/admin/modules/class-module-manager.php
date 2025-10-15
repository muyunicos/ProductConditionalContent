<?php
/**
 * Gestor Centralizado de Módulos v6.1
 * Registra y administra todos los módulos del sistema
 * Compatible con WordPress 6.8.3, PHP 8.2, WooCommerce 10.2.2
 * 
 * @package ProductConditionalContent
 * @since 6.1.0
 * @date 2025-10-15
 */

if (!defined('ABSPATH')) exit;

final class GDM_Module_Manager {
    
    /**
     * Instancia única (Singleton)
     * @var GDM_Module_Manager|null
     */
    private static $instance = null;
    
    /**
     * Módulos registrados
     * @var array
     */
    private $modules = [];
    
    /**
     * Instancias de módulos inicializados
     * @var array
     */
    private $module_instances = [];
    
    /**
     * Obtener instancia única
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor privado (Singleton)
     */
    private function __construct() {
        // Registrar módulos en init con prioridad 5 (antes de metaboxes)
        add_action('init', [$this, 'register_core_modules'], 5);
        
        // Permitir que otros plugins/temas registren módulos
        add_action('init', [$this, 'allow_external_registration'], 6);
        
        // Inicializar módulos registrados
        add_action('init', [$this, 'init_registered_modules'], 7);
    }
    
    /**
     * Registrar módulos del core
     */
    public function register_core_modules() {
        $modules_dir = GDM_PLUGIN_DIR . 'includes/admin/modules/';
        
        // Módulo de Descripción (existente)
        $this->register_module('descripcion', [
            'class' => 'GDM_Module_Descripcion',
            'label' => __('Descripción', 'product-conditional-content'),
            'icon' => '📄',
            'file' => $modules_dir . 'class-module-description.php',
            'enabled' => true,
            'priority' => 10,
        ]);
        
        // Módulo de Galería (NUEVO)
        $this->register_module('galeria', [
            'class' => 'GDM_Module_Gallery',
            'label' => __('Galería', 'product-conditional-content'),
            'icon' => '🖼️',
            'file' => $modules_dir . 'class-module-gallery.php',
            'enabled' => true,
            'priority' => 15,
        ]);
        
        // Módulo de Título (NUEVO)
        $this->register_module('titulo', [
            'class' => 'GDM_Module_Title',
            'label' => __('Título', 'product-conditional-content'),
            'icon' => '📝',
            'file' => $modules_dir . 'class-module-title.php',
            'enabled' => true,
            'priority' => 20,
        ]);
        
        // Módulo de Precio (NUEVO)
        $this->register_module('precio', [
            'class' => 'GDM_Module_Price',
            'label' => __('Precio', 'product-conditional-content'),
            'icon' => '💰',
            'file' => $modules_dir . 'class-module-price.php',
            'enabled' => true,
            'priority' => 25,
        ]);
        
        // Módulo de Destacado (NUEVO)
        $this->register_module('destacado', [
            'class' => 'GDM_Module_Featured',
            'label' => __('Destacado', 'product-conditional-content'),
            'icon' => '⭐',
            'file' => $modules_dir . 'class-module-featured.php',
            'enabled' => true,
            'priority' => 30,
        ]);
        
        /**
         * Hook para permitir registro de módulos personalizados
         * 
         * @param GDM_Module_Manager $this Instancia del manager
         */
        do_action('gdm_register_modules', $this);
    }
    
    /**
     * Permitir registro externo de módulos
     */
    public function allow_external_registration() {
        /**
         * Permite a otros plugins/temas registrar módulos
         * 
         * Ejemplo de uso:
         * add_action('gdm_modules_init', function($manager) {
         *     $manager->register_module('mi_modulo', [
         *         'class' => 'My_Custom_Module',
         *         'label' => 'Mi Módulo',
         *         'icon' => '🎯',
         *         'file' => '/path/to/my-module.php',
         *         'enabled' => true,
         *         'priority' => 50,
         *     ]);
         * });
         */
        do_action('gdm_modules_init', $this);
    }
    
    /**
     * Inicializar módulos registrados
     */
    public function init_registered_modules() {
        foreach ($this->modules as $id => $config) {
            // Solo inicializar módulos habilitados
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
                    'GDM Module Manager: Clase "%s" no encontrada para el módulo "%s"',
                    $config['class'],
                    $id
                ));
            }
        }
        
        /**
         * Hook ejecutado después de inicializar todos los módulos
         * 
         * @param array $module_instances Instancias de módulos inicializados
         */
        do_action('gdm_modules_loaded', $this->module_instances);
    }
    
    /**
     * Registrar un módulo
     * 
     * @param string $id ID único del módulo
     * @param array $config Configuración del módulo
     * @return bool True si se registró correctamente
     */
    public function register_module($id, $config = []) {
        // Validar ID
        if (empty($id)) {
            return false;
        }
        
        // Si ya existe, permitir sobrescribir solo si se fuerza
        if (isset($this->modules[$id]) && empty($config['force'])) {
            return false;
        }
        
        // Configuración por defecto
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
        
        // Validar clase
        if (empty($config['class'])) {
            return false;
        }
        
        // Registrar módulo
        $this->modules[$id] = $config;
        
        return true;
    }
    
    /**
     * Desregistrar un módulo
     * 
     * @param string $id ID del módulo
     * @return bool
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
     * 
     * @param string $id ID del módulo
     * @return array|null
     */
    public function get_module($id) {
        return $this->modules[$id] ?? null;
    }
    
    /**
     * Obtener todos los módulos registrados
     * 
     * @return array
     */
    public function get_modules() {
        return $this->modules;
    }
    
    /**
     * Obtener módulos habilitados
     * 
     * @return array
     */
    public function get_enabled_modules() {
        return array_filter($this->modules, function($module) {
            return $module['enabled'] === true;
        });
    }
    
    /**
     * Obtener módulos con íconos para el selector
     * 
     * @return array
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
     * Obtener instancia de un módulo
     * 
     * @param string $id ID del módulo
     * @return object|null
     */
    public function get_module_instance($id) {
        return $this->module_instances[$id] ?? null;
    }
    
    /**
     * Verificar si un módulo está registrado
     * 
     * @param string $id ID del módulo
     * @return bool
     */
    public function is_module_registered($id) {
        return isset($this->modules[$id]);
    }
    
    /**
     * Verificar si un módulo está habilitado
     * 
     * @param string $id ID del módulo
     * @return bool
     */
    public function is_module_enabled($id) {
        return isset($this->modules[$id]) && $this->modules[$id]['enabled'] === true;
    }
    
    /**
     * Habilitar/deshabilitar módulo
     * 
     * @param string $id ID del módulo
     * @param bool $enabled Estado
     * @return bool
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
     * 
     * @return array
     */
    public function get_modules_count() {
        return [
            'total' => count($this->modules),
            'enabled' => count($this->get_enabled_modules()),
            'disabled' => count($this->modules) - count($this->get_enabled_modules()),
        ];
    }
    
    /**
     * Debug: Listar módulos registrados
     * 
     * @return void
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
 * Función helper para obtener el manager
 * 
 * @return GDM_Module_Manager
 */
function gdm_modules() {
    return GDM_Module_Manager::instance();
}

/**
 * Debug en el admin footer (solo para desarrollo)
 */
if (defined('WP_DEBUG') && WP_DEBUG && is_admin()) {
    add_action('admin_footer', function() {
        $screen = get_current_screen();
        if ($screen && $screen->id === 'gdm_regla' && isset($_GET['debug_modules'])) {
            GDM_Module_Manager::instance()->debug_modules();
        }
    });
}