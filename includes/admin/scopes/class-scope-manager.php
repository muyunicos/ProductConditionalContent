<?php
/**
 * Gestor de Ámbitos (Scopes) - Sistema Dinámico y Extensible
 * Compatible con WordPress 6.8.3, PHP 8.2
 * 
 * ✅ FIX v6.2.5: Corrección de enqueue_scope_assets
 * 
 * @package ProductConditionalContent
 * @since 5.0.0
 */

if (!defined('ABSPATH')) exit;

final class GDM_Scope_Manager {
    private static $instance = null;
    private $scopes = [];
    private $scope_instances = [];

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // ✅ FIX: Registrar scopes en init con prioridad 10 (DESPUÉS de traducciones)
        add_action('init', [$this, 'register_core_scopes'], 10);
        
        // ✅ FIX: Permitir registro externo prioridad 11
        add_action('init', [$this, 'allow_external_registration'], 11);
        
        // ✅ FIX: Inicializar scopes prioridad 12
        add_action('init', [$this, 'init_registered_scopes'], 12);
        
        // ✅ FIX v6.2.5: Llamar al método desde GDM_Scope_Base en vez de __CLASS__
        add_action('admin_enqueue_scripts', ['GDM_Scope_Base', 'enqueue_scope_assets']);
    }
    
    /**
     * Registrar ámbitos del core
     * ✅ FIX: Ahora se ejecuta en hook init con prioridad 10
     */
    public function register_core_scopes() {
        $scopes_dir = GDM_PLUGIN_DIR . 'includes/admin/scopes/';
        
        $this->register_scope('productos', [
            'class' => 'GDM_Scope_Products',
            'label' => __('Productos Específicos', 'product-conditional-content'),
            'icon' => '🛍️',
            'file' => $scopes_dir . 'class-scope-products.php',
            'enabled' => true,
            'priority' => 10,
        ]);
        
        $this->register_scope('categorias', [
            'class' => 'GDM_Scope_Categories',
            'label' => __('Categorías', 'product-conditional-content'),
            'icon' => '📁',
            'file' => $scopes_dir . 'class-scope-categories.php',
            'enabled' => true,
            'priority' => 20,
        ]);
        
        $this->register_scope('etiquetas', [
            'class' => 'GDM_Scope_Tags',
            'label' => __('Etiquetas', 'product-conditional-content'),
            'icon' => '🏷️',
            'file' => $scopes_dir . 'class-scope-tags.php',
            'enabled' => true,
            'priority' => 30,
        ]);
        
        $this->register_scope('atributos', [
            'class' => 'GDM_Scope_Attributes',
            'label' => __('Atributos', 'product-conditional-content'),
            'icon' => '⚙️',
            'file' => $scopes_dir . 'class-scope-attributes.php',
            'enabled' => true,
            'priority' => 40,
        ]);
        
        $this->register_scope('tipos', [
            'class' => 'GDM_Scope_Product_Types',
            'label' => __('Tipos de Producto', 'product-conditional-content'),
            'icon' => '📦',
            'file' => $scopes_dir . 'class-scope-product-types.php',
            'enabled' => true,
            'priority' => 50,
        ]);
        
        $this->register_scope('precio', [
            'class' => 'GDM_Scope_Price',
            'label' => __('Filtro por Precio', 'product-conditional-content'),
            'icon' => '💰',
            'file' => $scopes_dir . 'class-scope-price.php',
            'enabled' => true,
            'priority' => 60,
        ]);
        
        $this->register_scope('titulo', [
            'class' => 'GDM_Scope_Title',
            'label' => __('Filtro por Título', 'product-conditional-content'),
            'icon' => '📝',
            'file' => $scopes_dir . 'class-scope-title.php',
            'enabled' => true,
            'priority' => 70,
        ]);
        
        do_action('gdm_register_scopes', $this);
    }
    
    /**
     * Permitir registro externo
     */
    public function allow_external_registration() {
        do_action('gdm_scopes_init', $this);
    }
    
    /**
     * Inicializar ámbitos registrados
     */
    public function init_registered_scopes() {
        foreach ($this->scopes as $id => $config) {
            if (!$config['enabled']) {
                continue;
            }
            
            if (!empty($config['file']) && file_exists($config['file'])) {
                require_once $config['file'];
            }
            
            if (class_exists($config['class'])) {
                try {
                    $this->scope_instances[$id] = new $config['class']();
                } catch (Exception $e) {
                    error_log(sprintf(
                        'GDM Scope Manager: Error al inicializar ámbito "%s": %s',
                        $id,
                        $e->getMessage()
                    ));
                }
            }
        }
        
        do_action('gdm_scopes_loaded', $this->scope_instances);
    }
    
    /**
     * Registrar un ámbito
     */
    public function register_scope($id, $config = []) {
        if (empty($id)) {
            return false;
        }
        
        if (isset($this->scopes[$id]) && empty($config['force'])) {
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
        
        $this->scopes[$id] = $config;
        
        return true;
    }
    
    /**
     * Obtener ámbito por ID
     */
    public function get_scope($id) {
        return $this->scopes[$id] ?? null;
    }
    
    /**
     * Obtener instancia de ámbito
     */
    public function get_scope_instance($id) {
        return $this->scope_instances[$id] ?? null;
    }
    
    /**
     * Obtener todos los ámbitos
     */
    public function get_scopes() {
        return $this->scopes;
    }
    
    /**
     * Obtener ámbitos ordenados por prioridad
     */
    public function get_scopes_ordered() {
        $scopes = $this->scopes;
        
        uasort($scopes, function($a, $b) {
            return $a['priority'] - $b['priority'];
        });
        
        return $scopes;
    }
    
    /**
     * Renderizar todos los ámbitos
     */
    public function render_all($post_id) {
        $scopes = $this->get_scopes_ordered();
        
        foreach ($scopes as $id => $config) {
            if (!$config['enabled']) {
                continue;
            }
            
            $instance = $this->get_scope_instance($id);
            if ($instance) {
                $instance->render($post_id);
            }
        }
    }
    
    /**
     * Guardar todos los ámbitos
     */
    public function save_all($post_id) {
        foreach ($this->scope_instances as $instance) {
            $instance->save($post_id);
        }
    }
    
    /**
     * Verificar si un producto cumple con los ámbitos de una regla
     */
    public function product_matches_scopes($product_id, $rule_id) {
        foreach ($this->scope_instances as $instance) {
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
function gdm_scopes() {
    return GDM_Scope_Manager::instance();
}