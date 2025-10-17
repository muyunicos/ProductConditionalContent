<?php
/**
 * Rules Engine v7.0 - CORREGIDO
 * Ejecuta las reglas en el frontend y aplica las acciones
 * 
 * FIXES APLICADOS:
 * ✅ Corregido bootstrap de reglas
 * ✅ Mejorada ejecución de actions
 * ✅ Agregado debugging para troubleshooting
 * ✅ Hooks de WooCommerce corregidos
 * 
 * @package ProductConditionalContent
 * @since 7.0.0
 */

if (!defined('ABSPATH')) exit;

class GDM_Rules_Engine {
    
    private static $instance = null;
    private $active_rules = [];
    private $debug_mode = false;
    
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->debug_mode = defined('WP_DEBUG') && WP_DEBUG;
        $this->init_hooks();
        $this->load_active_rules();
    }
    
    /**
     * Inicializar hooks de WordPress/WooCommerce
     */
    private function init_hooks() {
        // Hooks principales para productos
        add_action('wp', [$this, 'execute_rules_for_context'], 5);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        
        // Hooks específicos de WooCommerce
        add_filter('woocommerce_product_get_price', [$this, 'modify_product_price'], 10, 2);
        add_filter('woocommerce_product_get_sale_price', [$this, 'modify_product_sale_price'], 10, 2);
        add_filter('woocommerce_get_price_html', [$this, 'modify_price_html'], 10, 2);
        
        // Hooks para contenido
        add_filter('the_content', [$this, 'modify_content'], 10);
        add_filter('the_title', [$this, 'modify_title'], 10, 2);
        add_filter('post_thumbnail_html', [$this, 'modify_featured_image'], 10, 5);
        
        // Hook para shortcodes
        add_shortcode('gdm_content', [$this, 'shortcode_handler']);
        
        if ($this->debug_mode) {
            add_action('wp_footer', [$this, 'debug_output']);
        }
    }
    
    /**
     * Cargar reglas activas de la base de datos
     */
    private function load_active_rules() {
        $rules = get_posts([
            'post_type' => 'gdm_regla',
            'post_status' => 'publish',
            'numberposts' => -1,
            'meta_key' => '_gdm_regla_activa',
            'meta_value' => '1',
            'orderby' => 'meta_value_num',
            'meta_key' => '_gdm_regla_prioridad',
            'order' => 'ASC' // Menor prioridad = mayor importancia
        ]);
        
        foreach ($rules as $rule) {
            $rule_data = $this->get_rule_data($rule->ID);
            if ($rule_data && $this->is_rule_valid($rule_data)) {
                $this->active_rules[] = $rule_data;
            }
        }
        
        if ($this->debug_mode) {
            error_log('GDM Rules Engine: Loaded ' . count($this->active_rules) . ' active rules');
        }
    }
    
    /**
     * Obtener datos completos de una regla
     */
    private function get_rule_data($rule_id) {
        $rule = get_post($rule_id);
        if (!$rule || $rule->post_type !== 'gdm_regla') {
            return false;
        }
        
        return [
            'id' => $rule_id,
            'title' => $rule->post_title,
            'priority' => (int) get_post_meta($rule_id, '_gdm_regla_prioridad', true) ?: 10,
            'contexts' => get_post_meta($rule_id, '_gdm_regla_contextos', true) ?: ['products'],
            'conditions' => get_post_meta($rule_id, '_gdm_conditions_config', true) ?: [],
            'actions' => get_post_meta($rule_id, '_gdm_actions_config', true) ?: [],
            'is_forced' => (bool) get_post_meta($rule_id, '_gdm_regla_forzada', true),
            'is_last' => (bool) get_post_meta($rule_id, '_gdm_regla_ultima', true),
            'is_reusable' => (bool) get_post_meta($rule_id, '_gdm_regla_reutilizable', true),
            'content_categories' => get_post_meta($rule_id, '_gdm_regla_content_categories', true) ?: ['productos']
        ];
    }
    
    /**
     * Verificar si una regla es válida
     */
    private function is_rule_valid($rule_data) {
        return !empty($rule_data['contexts']) && 
               (!empty($rule_data['conditions']) || $rule_data['is_forced']);
    }
    
    /**
     * Ejecutar reglas según el contexto actual
     */
    public function execute_rules_for_context() {
        if (!$this->active_rules) {
            return;
        }
        
        $context = $this->get_current_context();
        if (!$context) {
            return;
        }
        
        foreach ($this->active_rules as $rule) {
            if ($this->rule_applies_to_context($rule, $context)) {
                if ($this->evaluate_rule_conditions($rule, $context)) {
                    $this->execute_rule_actions($rule, $context);
                    
                    // Si es última regla, no continuar
                    if ($rule['is_last']) {
                        break;
                    }
                }
            }
        }
    }
    
    /**
     * Determinar el contexto actual
     */
    private function get_current_context() {
        if (is_singular('product')) {
            return ['type' => 'products', 'object_id' => get_the_ID()];
        } elseif (is_singular('post')) {
            return ['type' => 'posts', 'object_id' => get_the_ID()];
        } elseif (is_singular('page')) {
            return ['type' => 'pages', 'object_id' => get_the_ID()];
        } elseif (is_shop() || is_product_category() || is_product_tag()) {
            return ['type' => 'wc_shop', 'object_id' => null];
        } elseif (is_cart()) {
            return ['type' => 'wc_cart', 'object_id' => null];
        } elseif (is_checkout()) {
            return ['type' => 'wc_checkout', 'object_id' => null];
        }
        
        return null;
    }
    
    /**
     * Verificar si la regla aplica al contexto actual
     */
    private function rule_applies_to_context($rule, $context) {
        // Verificar contextos básicos
        if (!in_array($context['type'], $rule['contexts'])) {
            return false;
        }
        
        // Verificar categorías de contenido
        $content_category_map = [
            'products' => 'productos',
            'posts' => 'entradas', 
            'pages' => 'páginas'
        ];
        
        $required_category = $content_category_map[$context['type']] ?? null;
        if ($required_category && !in_array($required_category, $rule['content_categories'])) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Evaluar condiciones de la regla
     */
    private function evaluate_rule_conditions($rule, $context) {
        // Si es regla forzada, siempre se ejecuta
        if ($rule['is_forced']) {
            return true;
        }
        
        // Si no hay condiciones, no ejecutar
        if (empty($rule['conditions'])) {
            return false;
        }
        
        $condition_manager = GDM_Condition_Manager::instance();
        
        foreach ($rule['conditions'] as $condition_type => $condition_data) {
            if (!isset($condition_data['enabled']) || !$condition_data['enabled']) {
                continue;
            }
            
            $condition = $condition_manager->get_condition($condition_type);
            if (!$condition) {
                continue;
            }
            
            if (!$condition->evaluate($context['object_id'], $condition_data['options'], $context)) {
                return false; // AND logic: todas las condiciones deben cumplirse
            }
        }
        
        return true;
    }
    
    /**
     * Ejecutar acciones de la regla
     */
    private function execute_rule_actions($rule, $context) {
        if (empty($rule['actions'])) {
            return;
        }
        
        $action_manager = GDM_Action_Manager::instance();
        
        foreach ($rule['actions'] as $action_type => $action_data) {
            if (!isset($action_data['enabled']) || !$action_data['enabled']) {
                continue;
            }
            
            $action = $action_manager->get_action($action_type);
            if (!$action) {
                continue;
            }
            
            // Configurar opciones de la acción
            $action->set_options($action_data['options']);
            
            // Ejecutar la acción
            try {
                $result = $action->execute_action($context['object_id'], $context['type']);
                
                if ($this->debug_mode) {
                    error_log("GDM: Executed action {$action_type} on {$context['object_id']}: " . 
                             ($result !== false ? 'SUCCESS' : 'FAILED'));
                }
            } catch (Exception $e) {
                if ($this->debug_mode) {
                    error_log("GDM: Action {$action_type} failed: " . $e->getMessage());
                }
            }
        }
    }
    
    /**
     * Modificar precio de producto
     */
    public function modify_product_price($price, $product) {
        $modified_price = $this->apply_price_modifications($product->get_id(), (float) $price);
        return $modified_price !== null ? $modified_price : $price;
    }
    
    /**
     * Modificar precio de oferta
     */
    public function modify_product_sale_price($sale_price, $product) {
        if (empty($sale_price)) {
            return $sale_price;
        }
        
        $modified_price = $this->apply_price_modifications($product->get_id(), (float) $sale_price);
        return $modified_price !== null ? $modified_price : $sale_price;
    }
    
    /**
     * Aplicar modificaciones de precio
     */
    private function apply_price_modifications($product_id, $original_price) {
        $context = ['type' => 'products', 'object_id' => $product_id];
        
        foreach ($this->active_rules as $rule) {
            if (!$this->rule_applies_to_context($rule, $context)) {
                continue;
            }
            
            if (!$this->evaluate_rule_conditions($rule, $context)) {
                continue;
            }
            
            // Buscar acción de precio
            if (isset($rule['actions']['price']) && $rule['actions']['price']['enabled']) {
                $action_manager = GDM_Action_Manager::instance();
                $price_action = $action_manager->get_action('price');
                
                if ($price_action) {
                    $price_action->set_options($rule['actions']['price']['options']);
                    $new_price = $price_action->execute_action($product_id, 'products');
                    
                    if ($new_price !== false) {
                        return $new_price;
                    }
                }
            }
        }
        
        return null; // No hay modificación
    }
    
    /**
     * Modificar HTML del precio
     */
    public function modify_price_html($price_html, $product) {
        // Aquí se pueden aplicar modificaciones adicionales como badges, precios tachados, etc.
        return $price_html;
    }
    
    /**
     * Modificar contenido del post
     */
    public function modify_content($content) {
        if (!is_singular()) {
            return $content;
        }
        
        global $post;
        $context = $this->get_current_context();
        
        if (!$context) {
            return $content;
        }
        
        foreach ($this->active_rules as $rule) {
            if (!$this->rule_applies_to_context($rule, $context)) {
                continue;
            }
            
            if (!$this->evaluate_rule_conditions($rule, $context)) {
                continue;
            }
            
            // Buscar acción de descripción
            if (isset($rule['actions']['description']) && $rule['actions']['description']['enabled']) {
                $action_manager = GDM_Action_Manager::instance();
                $desc_action = $action_manager->get_action('description');
                
                if ($desc_action) {
                    $desc_action->set_options($rule['actions']['description']['options']);
                    $new_content = $desc_action->execute_action($post->ID, $context['type']);
                    
                    if ($new_content !== false) {
                        return $new_content;
                    }
                }
            }
        }
        
        return $content;
    }
    
    /**
     * Handler de shortcode [gdm_content]
     */
    public function shortcode_handler($atts, $content = '') {
        $atts = shortcode_atts([
            'rule' => '',
            'context' => 'shortcode',
            'object_id' => null
        ], $atts);
        
        if (empty($atts['rule'])) {
            return $content;
        }
        
        $rule_id = (int) $atts['rule'];
        $rule_data = $this->get_rule_data($rule_id);
        
        if (!$rule_data) {
            return $content;
        }
        
        $context = [
            'type' => $atts['context'],
            'object_id' => $atts['object_id'] ?: get_the_ID()
        ];
        
        if ($this->evaluate_rule_conditions($rule_data, $context)) {
            $this->execute_rule_actions($rule_data, $context);
        }
        
        return $content;
    }
    
    /**
     * Cargar assets del frontend
     */
    public function enqueue_frontend_assets() {
        wp_enqueue_style(
            'gdm-frontend',
            GDM_PLUGIN_URL . 'assets/frontend/css/options-renderer.css',
            [],
            GDM_VERSION
        );
        
        wp_enqueue_script(
            'gdm-frontend',
            GDM_PLUGIN_URL . 'assets/frontend/js/options-renderer.js',
            ['jquery'],
            GDM_VERSION,
            true
        );
    }
    
    /**
     * Output de debug en el footer
     */
    public function debug_output() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        ?>
        <div id="gdm-debug" style="position: fixed; bottom: 10px; left: 10px; background: #fff; border: 1px solid #ccc; padding: 10px; font-size: 11px; z-index: 9999;">
            <strong>GDM Debug</strong><br>
            Active Rules: <?php echo count($this->active_rules); ?><br>
            Context: <?php echo json_encode($this->get_current_context()); ?><br>
            <a href="#" onclick="this.parentElement.remove()">Close</a>
        </div>
        <?php
    }
    
    /**
     * API pública para obtener reglas activas
     */
    public function get_active_rules() {
        return $this->active_rules;
    }
    
    /**
     * API pública para limpiar cache de reglas
     */
    public function refresh_rules() {
        $this->active_rules = [];
        $this->load_active_rules();
    }
}

// Inicializar el motor de reglas
add_action('init', function() {
    GDM_Rules_Engine::instance();
}, 20);