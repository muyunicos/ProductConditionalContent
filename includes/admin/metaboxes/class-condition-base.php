<?php
/**
 * Clase Base Abstracta para Ámbitos de Aplicación
 * Cada ámbito es un módulo independiente con su UI y lógica
 * Compatible con WordPress 6.8.3, PHP 8.2, WooCommerce 10.2.2
 * 
 * @package ProductConditionalContent
 * @since 6.1.0
 * @date 2025-10-15
 */

if (!defined('ABSPATH')) exit;

abstract class GDM_Condition_Base {
    
    /**
     * ID único del ámbito
     * @var string
     */
    protected $condition_id = '';
    
    /**
     * Nombre del ámbito
     * @var string
     */
    protected $condition_name = '';
    
    /**
     * Icono del ámbito
     * @var string
     */
    protected $condition_icon = '🎯';
    
    /**
     * Prioridad de orden
     * @var int
     */
    protected $priority = 10;
    
    /**
     * Caché estático
     * @var array
     */
    protected static $cache = [];
    
    /**
     * Constructor
     */
    public function __construct() {
        if (empty($this->condition_id) || empty($this->condition_name)) {
            wp_die(__('El ámbito debe definir condition_id y condition_name', 'product-conditional-content'));
        }
        
        // Hooks de inicialización
        $this->condition_init();
    }

    /**
     * Encolar CSS y JS globales de conditions
     * 
     * @since 6.2.0
     */
    public static function enqueue_condition_assets() {
        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'gdm_regla') {
            return;
        }

        // CSS Base Global (Capa 1)
        wp_enqueue_style(
            'gdm-rules-admin-general',
            GDM_PLUGIN_URL . 'assets/admin/css/rules-admin-general.css',
            [],
            GDM_VERSION
        );

        // CSS Específico de conditions (Capa 2)
        wp_enqueue_style(
            'gdm-rules-config-metabox',
            GDM_PLUGIN_URL . 'assets/admin/css/rules-config-metabox.css',
            ['gdm-rules-admin-general'],
            GDM_VERSION
        );
    }

    /**
     * Hook de inicialización específica (opcional)
     */
    protected function condition_init() {
        // Implementar en clase hija si se necesita
    }
    
    /**
     * Renderizar el ámbito completo
     * 
     * @param int $post_id ID del post
     */
    public function render($post_id) {
        $data = $this->get_condition_data($post_id);
        $has_selection = $this->has_selection($data);
        $summary = $this->get_summary($data);
        $counter_text = $this->get_counter_text($data);
        
        ?>
        <div class="gdm-condition-group" 
             data-condition="<?php echo esc_attr($this->condition_id); ?>"
             role="region" 
             aria-labelledby="gdm-<?php echo esc_attr($this->condition_id); ?>-label">
            
            <div class="gdm-condition-header">
                <label class="gdm-condition-toggle">
                    <input type="checkbox" 
                           id="gdm_<?php echo esc_attr($this->condition_id); ?>_enabled" 
                           name="gdm_<?php echo esc_attr($this->condition_id); ?>_enabled" 
                           class="gdm-condition-checkbox"
                           value="1"
                           aria-describedby="gdm-<?php echo esc_attr($this->condition_id); ?>-summary"
                           aria-controls="gdm-<?php echo esc_attr($this->condition_id); ?>-content"
                           <?php checked($has_selection); ?>>
                    <strong id="gdm-<?php echo esc_attr($this->condition_id); ?>-label">
                        <?php echo esc_html($this->condition_icon . ' ' . $this->condition_name); ?>
                    </strong>
                </label>
                
                <div class="gdm-condition-summary" 
                     id="gdm-<?php echo esc_attr($this->condition_id); ?>-summary" 
                     style="<?php echo !$has_selection ? 'display:none;' : ''; ?>">
                    <span class="gdm-summary-text" id="gdm-<?php echo esc_attr($this->condition_id); ?>-summary-text">
                        <?php echo wp_kses_post($summary); ?>
                    </span>
                    <button type="button" 
                            class="button button-small gdm-condition-edit" 
                            data-target="<?php echo esc_attr($this->condition_id); ?>"
                            aria-label="<?php printf(__('Editar %s', 'product-conditional-content'), $this->condition_name); ?>"
                            aria-expanded="false">
                        <?php _e('Editar', 'product-conditional-content'); ?>
                    </button>
                </div>
            </div>
            
            <div class="gdm-condition-content" 
                 id="gdm-<?php echo esc_attr($this->condition_id); ?>-content" 
                 role="group"
                 aria-hidden="true"
                 style="display:none;">
                
                <?php $this->render_content($post_id, $data); ?>
                
                <div class="gdm-condition-actions">
                    <button type="button" 
                            class="button button-primary gdm-condition-save" 
                            data-target="<?php echo esc_attr($this->condition_id); ?>"
                            aria-label="<?php printf(__('Guardar cambios en %s', 'product-conditional-content'), $this->condition_name); ?>">
                        <span class="dashicons dashicons-yes"></span>
                        <?php _e('Guardar', 'product-conditional-content'); ?>
                    </button>
                    <button type="button" 
                            class="button gdm-condition-cancel" 
                            data-target="<?php echo esc_attr($this->condition_id); ?>"
                            aria-label="<?php _e('Cancelar cambios', 'product-conditional-content'); ?>">
                        <?php _e('Cancelar', 'product-conditional-content'); ?>
                    </button>
                    <span class="gdm-selection-counter" 
                          id="gdm-<?php echo esc_attr($this->condition_id); ?>-counter"
                          aria-live="polite">
                        <?php echo esc_html($counter_text); ?>
                    </span>
                </div>
            </div>
        </div>
        
        <?php $this->render_styles(); ?>
        <?php $this->render_scripts(); ?>
        <?php
    }
    
    /**
     * Renderizar contenido específico del ámbito (DEBE implementarse)
     * 
     * @param int $post_id ID del post
     * @param array $data Datos del ámbito
     */
    abstract protected function render_content($post_id, $data);
    
    /**
     * Guardar datos del ámbito (DEBE implementarse)
     * 
     * @param int $post_id ID del post
     */
    abstract public function save($post_id);
    
    /**
     * Obtener datos del ámbito
     * 
     * @param int $post_id ID del post
     * @return array
     */
    protected function get_condition_data($post_id) {
        $cache_key = "{$this->condition_id}_{$post_id}";
        
        if (isset(self::$cache[$cache_key])) {
            return self::$cache[$cache_key];
        }
        
        $data = $this->get_default_data();
        
        foreach ($data as $key => $default_value) {
            $meta_key = "_gdm_{$this->condition_id}_{$key}";
            $value = get_post_meta($post_id, $meta_key, true);
            $data[$key] = ($value !== '' && $value !== false) ? $value : $default_value;
        }
        
        self::$cache[$cache_key] = $data;
        return $data;
    }
    
    /**
     * Obtener datos por defecto (DEBE implementarse)
     * 
     * @return array
     */
    abstract protected function get_default_data();
    
    /**
     * Verificar si hay selección (DEBE implementarse)
     * 
     * @param array $data Datos del ámbito
     * @return bool
     */
    abstract protected function has_selection($data);
    
    /**
     * Obtener resumen de la selección (DEBE implementarse)
     * 
     * @param array $data Datos del ámbito
     * @return string HTML del resumen
     */
    abstract protected function get_summary($data);
    
    /**
     * Obtener texto del contador (DEBE implementarse)
     * 
     * @param array $data Datos del ámbito
     * @return string
     */
    abstract protected function get_counter_text($data);
    
    /**
     * Renderizar estilos específicos del ámbito (opcional)
     */
    protected function render_styles() {
        // Implementar en clase hija si se necesitan estilos específicos
    }
    
    /**
     * Renderizar scripts específicos del ámbito (opcional)
     */
    protected function render_scripts() {
        // Implementar en clase hija si se necesitan scripts específicos
    }
    
    /**
     * Helper: Guardar campo del ámbito
     * 
     * @param int $post_id ID del post
     * @param string $field_name Nombre del campo
     * @param mixed $value Valor a guardar
     */
    protected function save_field($post_id, $field_name, $value) {
        $meta_key = "_gdm_{$this->condition_id}_{$field_name}";
        update_post_meta($post_id, $meta_key, $value);
    }
    
    /**
     * Verificar si el ámbito cumple condiciones para un producto
     * 
     * @param int $product_id ID del producto
     * @param int $rule_id ID de la regla
     * @return bool
     */
    abstract public function matches_product($product_id, $rule_id);
}