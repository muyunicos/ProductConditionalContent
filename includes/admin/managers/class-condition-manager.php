<?php
/**
 * Gestor de Ámbitos (conditions) - Sistema Dinámico y Extensible
 * Compatible con WordPress 6.8.3, PHP 8.2
 * 
 * ✅ FIX v6.2.5: Corrección de enqueue_condition_assets
 * 
 * @package ProductConditionalContent
 * @since 5.0.0
 */

if (!defined('ABSPATH')) exit;

final class GDM_Condition_Manager {
    private static $instance = null;
    private $conditions = [];
    private $condition_instances = [];

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // ✅ FIX: Registrar conditions en init con prioridad 10 (DESPUÉS de traducciones)
        add_action('init', [$this, 'register_core_conditions'], 10);
        
        // ✅ FIX: Permitir registro externo prioridad 11
        add_action('init', [$this, 'allow_external_registration'], 11);
        
        // ✅ FIX: Inicializar conditions prioridad 12
        add_action('init', [$this, 'init_registered_conditions'], 12);
        
        // ✅ FIX v6.2.5: Llamar al método desde GDM_Condition_Base en vez de __CLASS__
        add_action('admin_enqueue_scripts', ['GDM_Condition_Base', 'enqueue_condition_assets']);
    }
    
    /**
     * Registrar ámbitos del core
     * ✅ FIX: Ahora se ejecuta en hook init con prioridad 10
     */
    public function register_core_conditions() {
        $conditions_dir = GDM_PLUGIN_DIR . 'includes/admin/conditions/';
        
        $this->register_condition('productos', [
            'class' => 'GDM_Condition_Products',
            'label' => __('Productos Específicos', 'product-conditional-content'),
            'icon' => '🛍️',
            'file' => $conditions_dir . 'class-condition-products.php',
            'enabled' => true,
            'priority' => 10,
        ]);
        
        $this->register_condition('categorias', [
            'class' => 'GDM_Condition_Categories',
            'label' => __('Categorías', 'product-conditional-content'),
            'icon' => '📁',
            'file' => $conditions_dir . 'class-condition-categories.php',
            'enabled' => true,
            'priority' => 20,
        ]);
        
        $this->register_condition('etiquetas', [
            'class' => 'GDM_Condition_Tags',
            'label' => __('Etiquetas', 'product-conditional-content'),
            'icon' => '🏷️',
            'file' => $conditions_dir . 'class-condition-tags.php',
            'enabled' => true,
            'priority' => 30,
        ]);
        
        $this->register_condition('atributos', [
            'class' => 'GDM_Condition_Attributes',
            'label' => __('Atributos', 'product-conditional-content'),
            'icon' => '⚙️',
            'file' => $conditions_dir . 'class-condition-attributes.php',
            'enabled' => true,
            'priority' => 40,
        ]);
        
        $this->register_condition('tipos', [
            'class' => 'GDM_Condition_Product_Types',
            'label' => __('Tipos de Producto', 'product-conditional-content'),
            'icon' => '📦',
            'file' => $conditions_dir . 'class-condition-product-types.php',
            'enabled' => true,
            'priority' => 50,
        ]);
        
        $this->register_condition('precio', [
            'class' => 'GDM_Condition_Price',
            'label' => __('Filtro por Precio', 'product-conditional-content'),
            'icon' => '💰',
            'file' => $conditions_dir . 'class-condition-price.php',
            'enabled' => true,
            'priority' => 60,
        ]);
        
        $this->register_condition('titulo', [
            'class' => 'GDM_Condition_Title',
            'label' => __('Filtro por Título', 'product-conditional-content'),
            'icon' => '📝',
            'file' => $conditions_dir . 'class-condition-title.php',
            'enabled' => true,
            'priority' => 70,
        ]);
        
        do_action('gdm_register_conditions', $this);
    }
    
    /**
     * Permitir registro externo
     */
    public function allow_external_registration() {
        do_action('gdm_conditions_init', $this);
    }
    
    /**
     * Inicializar ámbitos registrados
     */
    public function init_registered_conditions() {
        foreach ($this->conditions as $id => $config) {
            if (!$config['enabled']) {
                continue;
            }
            
            if (!empty($config['file']) && file_exists($config['file'])) {
                require_once $config['file'];
            }
            
            if (class_exists($config['class'])) {
                try {
                    $this->condition_instances[$id] = new $config['class']();
                } catch (Exception $e) {
                    error_log(sprintf(
                        'GDM condition Manager: Error al inicializar ámbito "%s": %s',
                        $id,
                        $e->getMessage()
                    ));
                }
            }
        }
        
        do_action('gdm_conditions_loaded', $this->condition_instances);
    }
    
    /**
     * Registrar un ámbito
     */
    public function register_condition($id, $config = []) {
        if (empty($id)) {
            return false;
        }
        
        if (isset($this->conditions[$id]) && empty($config['force'])) {
            return false;
        }
        
        $defaults = [
            'class' => '',
            'label' => ucfirst($id),
            'icon' => '🎯',
            'file' => '',
            'enabled' => true,
            'priority' => 10,
        ];
        
        $config = wp_parse_args($config, $defaults);
        
        if (empty($config['class'])) {
            return false;
        }
        
        $this->conditions[$id] = $config;
        
        return true;
    }
    
    /**
     * Obtener ámbito por ID
     */
    public function get_condition($id) {
        return $this->conditions[$id] ?? null;
    }
    
    /**
     * Obtener instancia de ámbito
     */
    public function get_condition_instance($id) {
        return $this->condition_instances[$id] ?? null;
    }
    
    /**
     * Obtener todos los ámbitos
     */
    public function get_conditions() {
        return $this->conditions;
    }
    
    /**
     * Obtener ámbitos ordenados por prioridad
     */
    public function get_conditions_ordered() {
        $conditions = $this->conditions;
        
        uasort($conditions, function($a, $b) {
            return $a['priority'] - $b['priority'];
        });
        
        return $conditions;
    }
    
    /**
     * Renderizar todos los ámbitos
     */
    public function render_all($post_id) {
        $conditions = $this->get_conditions_ordered();
        
        foreach ($conditions as $id => $config) {
            if (!$config['enabled']) {
                continue;
            }
            
            $instance = $this->get_condition_instance($id);
            if ($instance) {
                $instance->render($post_id);
            }
        }
    }
    
    /**
     * Guardar todos los ámbitos
     */
    public function save_all($post_id) {
        foreach ($this->condition_instances as $instance) {
            $instance->save($post_id);
        }
    }
    
    /**
     * Verificar si un producto cumple con los ámbitos de una regla
     */
    public function product_matches_conditions($product_id, $rule_id) {
        foreach ($this->condition_instances as $instance) {
            if (!$instance->matches_product($product_id, $rule_id)) {
                return false;
            }
        }
        
        return true;
    }
}

/**
 * Helper function
 */
function gdm_conditions() {
    return GDM_Condition_Manager::instance();
}