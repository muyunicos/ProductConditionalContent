<?php
/**
 * Gestor de Módulos para Reglas de Contenido
 * Sistema de registro y gestión centralizada de módulos
 * Compatible con WordPress 6.8.3, PHP 8.2, WooCommerce 10.2.2
 * 
 * @package ProductConditionalContent
 * @since 6.0.0
 * @author MuyUnicos
 * @date 2025-10-15
 */

if (!defined('ABSPATH')) exit;

final class GDM_Module_Manager {
    
    /**
     * Instancia única del gestor
     * @var GDM_Module_Manager|null
     */
    private static $instance = null;
    
    /**
     * Módulos registrados
     * @var array
     */
    private $modules = [];
    
    /**
     * Módulos inicializados
     * @var array
     */
    private $initialized_modules = [];
    
    /**
     * Obtener instancia única
     * 
     * @return GDM_Module_Manager
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
        // Módulo de Descripción (único implementado en Fase 1)
        $this->register_module('descripcion', [
            'class' => 'GDM_Module_Descripcion',
            'label' => __('Descripción', 'product-conditional-content'),
            'icon' => '📝',
            'file' => GDM_PLUGIN_DIR . 'includes/admin/modules/class-module-descripcion.php',
            'enabled' => true,
        ]);
        
        // Módulos futuros (preparados para Fase 2+)
        $future_modules = [
            'precio' => [
                'class' => 'GDM_Module_Precio',
                'label' => __('Precio', 'product-conditional-content'),
                'icon' => '💰',
                'file' => GDM_PLUGIN_DIR . 'includes/admin/modules/class-module-precio.php',
                'enabled' => false, // Deshabilitado hasta implementación
            ],
            'titulo' => [
                'class' => 'GDM_Module_Titulo',
                'label' => __('Título', 'product-conditional-content'),
                'icon' => '📌',
                'file' => GDM_PLUGIN_DIR . 'includes/admin/modules/class-module-titulo.php',
                'enabled' => false,
            ],
            'imagen' => [
                'class' => 'GDM_Module_Imagen',
                'label' => __('Imagen Destacada', 'product-conditional-content'),
                'icon' => '🖼️',
                'file' => GDM_PLUGIN_DIR . 'includes/admin/modules/class-module-imagen.php',
                'enabled' => false,
            ],
            'sku' => [
                'class' => 'GDM_Module_SKU',
                'label' => __('SKU', 'product-conditional-content'),
                'icon' => '🔖',
                'file' => GDM_PLUGIN_DIR . 'includes/admin/modules/class-module-sku.php',
                'enabled' => false,
            ],
            'stock' => [
                'class' => 'GDM_Module_Stock',
                'label' => __('Estado de Stock', 'product-conditional-content'),
                'icon' => '📦',
                'file' => GDM_PLUGIN_DIR . 'includes/admin/modules/class-module-stock.php',
                'enabled' => false,
            ],
        ];
        
        // Aplicar filtro para habilitar módulos futuros
        $future_modules = apply_filters('gdm_future_modules', $future_modules);
        
        foreach ($future_modules as $id => $config) {
            $this->register_module($id, $config);
        }
    }
    
    /**
     * Permitir registro externo de módulos
     */
    public function allow_external_registration() {
        /**
         * Hook para que otros plugins/temas registren módulos personalizados
         * 
         * @param GDM_Module_Manager $manager Instancia del gestor
         */
        do_action('gdm_register_modules', $this);
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
        if (empty($id) || isset($this->modules[$id])) {
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
                $this->initialized_modules[$id] = new $config['class']();
            } else {
                // Log de error si la clase no existe
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log(sprintf(
                        '[GDM Module Manager] Clase %s no encontrada para módulo %s',
                        $config['class'],
                        $id
                    ));
                }
            }
        }
    }
    
    /**
     * Obtener módulos disponibles para selección
     * 
     * @return array Array asociativo [id => label]
     */
    public function get_available_modules() {
        $available = [];
        
        foreach ($this->modules as $id => $config) {
            if ($config['enabled']) {
                $available[$id] = $config['label'];
            }
        }
        
        /**
         * Filtro para modificar módulos disponibles
         * 
         * @param array $available Módulos disponibles
         */
        return apply_filters('gdm_available_modules', $available);
    }
    
    /**
     * Obtener módulos disponibles con iconos
     * 
     * @return array Array con estructura completa [id => ['label', 'icon']]
     */
    public function get_modules_with_icons() {
        $modules = [];
        
        foreach ($this->modules as $id => $config) {
            if ($config['enabled']) {
                $modules[$id] = [
                    'label' => $config['label'],
                    'icon' => $config['icon'],
                ];
            }
        }
        
        return $modules;
    }
    
    /**
     * Verificar si un módulo está registrado
     * 
     * @param string $module_id ID del módulo
     * @return bool
     */
    public function is_module_registered($module_id) {
        return isset($this->modules[$module_id]);
    }
    
    /**
     * Verificar si un módulo está habilitado
     * 
     * @param string $module_id ID del módulo
     * @return bool
     */
    public function is_module_enabled($module_id) {
        return isset($this->modules[$module_id]) && $this->modules[$module_id]['enabled'];
    }
    
    /**
     * Obtener instancia de un módulo inicializado
     * 
     * @param string $module_id ID del módulo
     * @return object|null Instancia del módulo o null si no existe
     */
    public function get_module_instance($module_id) {
        return isset($this->initialized_modules[$module_id]) 
            ? $this->initialized_modules[$module_id] 
            : null;
    }
    
    /**
     * Obtener configuración de un módulo
     * 
     * @param string $module_id ID del módulo
     * @return array|null Configuración del módulo o null si no existe
     */
    public function get_module_config($module_id) {
        return isset($this->modules[$module_id]) 
            ? $this->modules[$module_id] 
            : null;
    }
    
    /**
     * Habilitar/deshabilitar un módulo
     * 
     * @param string $module_id ID del módulo
     * @param bool $enabled Estado deseado
     * @return bool True si se cambió el estado
     */
    public function set_module_enabled($module_id, $enabled) {
        if (!isset($this->modules[$module_id])) {
            return false;
        }
        
        $this->modules[$module_id]['enabled'] = (bool) $enabled;
        return true;
    }
}

// Inicializar gestor de módulos
GDM_Module_Manager::instance();