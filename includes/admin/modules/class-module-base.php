<?php
/**
 * Clase Base Abstracta para Módulos de Reglas
 * Sistema modular para características de productos WooCommerce
 * Compatible con WordPress 6.8.3, PHP 8.2, WooCommerce 10.2.2
 * 
 * @package ProductConditionalContent
 * @since 6.0.0
 * @author MuyUnicos
 * @date 2025-10-15
 */

if (!defined('ABSPATH')) exit;

abstract class GDM_Module_Base {
    
    /**
     * ID único del módulo (debe definirse en clase hija)
     * @var string
     */
    protected $module_id = '';
    
    /**
     * Nombre del módulo para mostrar
     * @var string
     */
    protected $module_name = '';
    
    /**
     * Ícono del módulo (dashicon o emoji)
     * @var string
     */
    protected $module_icon = '⚙️';
    
    /**
     * Prioridad del metabox
     * @var string
     */
    protected $priority = 'default';
    
    /**
     * Caché estático para datos del módulo
     * @var array
     */
    protected static $cache = [];
    
    /**
     * Constructor
     */
    public function __construct() {
        if (empty($this->module_id) || empty($this->module_name)) {
            wp_die(__('El módulo debe definir module_id y module_name', 'product-conditional-content'));
        }
        
        // Hooks principales
        add_action('add_meta_boxes', [$this, 'register_metabox']);
        add_action('save_post_gdm_regla', [$this, 'save_module_data'], 20, 2);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        
        // Hook de inicialización específico del módulo
        $this->module_init();
    }
    
    /**
     * Hook de inicialización específica del módulo (opcional)
     */
    protected function module_init() {
        // Implementar en clase hija si se necesita
    }
    
    /**
     * Registrar metabox
     */
    public function register_metabox() {
        add_meta_box(
            "gdm_module_{$this->module_id}",
            sprintf('%s %s', $this->module_icon, $this->module_name),
            [$this, 'render_metabox_wrapper'],
            'gdm_regla',
            'normal',
            $this->priority
        );
    }
    
    /**
     * Wrapper del metabox con validación de activación
     */
    public function render_metabox_wrapper($post) {
        $is_active = $this->is_module_active($post->ID);
        
        echo '<div class="gdm-module-wrapper" data-module="' . esc_attr($this->module_id) . '">';
        
        if (!$is_active) {
            $this->render_inactive_message();
        } else {
            $this->render_metabox($post);
        }
        
        echo '</div>';
        
        // Estilos inline base
        $this->render_base_styles();
    }
    
    /**
     * Mensaje cuando el módulo está inactivo
     */
    protected function render_inactive_message() {
        ?>
        <div class="gdm-module-inactive">
            <p>
                <span class="dashicons dashicons-info"></span>
                <?php 
                printf(
                    __('Para usar este módulo, activa "%s" en la sección "Aplicar a" de la configuración general.', 'product-conditional-content'),
                    '<strong>' . esc_html($this->module_name) . '</strong>'
                );
                ?>
            </p>
        </div>
        <?php
    }
    
    /**
     * Estilos base para todos los módulos
     */
    protected function render_base_styles() {
        ?>
        <style>
            .gdm-module-wrapper {
                min-height: 50px;
            }
            .gdm-module-inactive {
                padding: 20px;
                background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
                border-left: 4px solid #ffc107;
                color: #856404;
                border-radius: 4px;
                margin: 10px 0;
            }
            .gdm-module-inactive .dashicons {
                vertical-align: middle;
                margin-right: 8px;
                color: #ffc107;
            }
            .gdm-field-group {
                margin-bottom: 20px;
                padding-bottom: 15px;
            }
            .gdm-field-group:not(:last-child) {
                border-bottom: 1px solid #ddd;
            }
            .gdm-field-group label {
                display: block;
                margin-bottom: 8px;
                font-weight: 600;
            }
            .gdm-field-description {
                font-size: 13px;
                color: #646970;
                font-style: italic;
                margin-top: 5px;
            }
            .gdm-module-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 15px;
                padding-bottom: 10px;
                border-bottom: 2px solid #e0e0e0;
            }
            .gdm-module-header h4 {
                margin: 0;
                display: flex;
                align-items: center;
                gap: 8px;
            }
        </style>
        <?php
    }
    
    /**
     * Renderizar contenido del metabox (DEBE implementarse en clase hija)
     * 
     * @param WP_Post $post Post actual
     */
    abstract public function render_metabox($post);
    
    /**
     * Guardar datos del módulo (DEBE implementarse en clase hija)
     * 
     * @param int $post_id ID del post
     * @param WP_Post $post Post actual
     */
    abstract public function save_module_data($post_id, $post);
    
    /**
     * Obtener datos por defecto del módulo (DEBE implementarse en clase hija)
     * 
     * @return array Array asociativo con valores por defecto
     */
    abstract protected function get_default_data();
    
    /**
     * Encolar assets específicos del módulo (opcional)
     * 
     * @param string $hook Hook actual
     */
    public function enqueue_assets($hook) {
        // Implementar en clase hija si se necesitan assets específicos
    }
    
    /**
     * Validar si el módulo está activo para esta regla
     * 
     * @param int $post_id ID del post
     * @return bool
     */
    protected function is_module_active($post_id) {
        $aplicar_a = get_post_meta($post_id, '_gdm_aplicar_a', true) ?: [];
        return in_array($this->module_id, $aplicar_a);
    }
    
    /**
     * Helper: Obtener datos del módulo con caché
     * 
     * @param int $post_id ID del post
     * @return array Datos del módulo
     */
    protected function get_module_data($post_id) {
        $cache_key = "{$this->module_id}_{$post_id}";
        
        if (isset(self::$cache[$cache_key])) {
            return self::$cache[$cache_key];
        }
        
        $default_data = $this->get_default_data();
        $data = [];
        
        foreach ($default_data as $key => $default_value) {
            $meta_key = "_gdm_{$this->module_id}_{$key}";
            $value = get_post_meta($post_id, $meta_key, true);
            $data[$key] = ($value !== '' && $value !== false) ? $value : $default_value;
        }
        
        self::$cache[$cache_key] = $data;
        return $data;
    }
    
    /**
     * Helper: Guardar un campo del módulo
     * 
     * @param int $post_id ID del post
     * @param string $field_key Clave del campo
     * @param mixed $value Valor a guardar
     */
    protected function save_module_field($post_id, $field_key, $value) {
        $meta_key = "_gdm_{$this->module_id}_{$field_key}";
        update_post_meta($post_id, $meta_key, $value);
    }
    
    /**
     * Helper: Validación de guardado usando GDM_Admin_Helpers
     * 
     * @param int $post_id ID del post
     * @param WP_Post $post Post actual
     * @return bool True si la validación pasa, false si debe abortar
     */
    protected function validate_save($post_id, $post) {
        return GDM_Admin_Helpers::validate_metabox_save(
            $post_id, 
            $post, 
            'gdm_nonce', 
            'gdm_save_rule_data', 
            'gdm_regla'
        );
    }
    
    /**
     * Helper: Renderizar campo de texto
     * 
     * @param string $field_id ID del campo
     * @param array $args Argumentos del campo
     */
    protected function render_text_field($field_id, $args = []) {
        $defaults = [
            'label' => '',
            'value' => '',
            'placeholder' => '',
            'description' => '',
            'class' => 'regular-text',
            'required' => false,
        ];
        $args = wp_parse_args($args, $defaults);
        ?>
        <div class="gdm-field-group">
            <?php if ($args['label']): ?>
                <label for="<?php echo esc_attr($field_id); ?>">
                    <?php echo esc_html($args['label']); ?>
                    <?php if ($args['required']): ?>
                        <span class="required">*</span>
                    <?php endif; ?>
                </label>
            <?php endif; ?>
            <input type="text" 
                   id="<?php echo esc_attr($field_id); ?>" 
                   name="<?php echo esc_attr($field_id); ?>" 
                   value="<?php echo esc_attr($args['value']); ?>" 
                   placeholder="<?php echo esc_attr($args['placeholder']); ?>" 
                   class="<?php echo esc_attr($args['class']); ?>"
                   <?php echo $args['required'] ? 'required' : ''; ?>>
            <?php if ($args['description']): ?>
                <p class="gdm-field-description"><?php echo esc_html($args['description']); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Helper: Renderizar campo checkbox
     * 
     * @param string $field_id ID del campo
     * @param array $args Argumentos del campo
     */
    protected function render_checkbox_field($field_id, $args = []) {
        $defaults = [
            'label' => '',
            'checked' => false,
            'description' => '',
        ];
        $args = wp_parse_args($args, $defaults);
        ?>
        <div class="gdm-field-group">
            <label>
                <input type="checkbox" 
                       id="<?php echo esc_attr($field_id); ?>" 
                       name="<?php echo esc_attr($field_id); ?>" 
                       value="1"
                       <?php checked($args['checked'], true); ?>>
                <?php echo esc_html($args['label']); ?>
            </label>
            <?php if ($args['description']): ?>
                <p class="gdm-field-description"><?php echo esc_html($args['description']); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Helper: Renderizar select
     * 
     * @param string $field_id ID del campo
     * @param array $args Argumentos del campo
     */
    protected function render_select_field($field_id, $args = []) {
        $defaults = [
            'label' => '',
            'value' => '',
            'options' => [],
            'description' => '',
            'class' => 'widefat',
        ];
        $args = wp_parse_args($args, $defaults);
        ?>
        <div class="gdm-field-group">
            <?php if ($args['label']): ?>
                <label for="<?php echo esc_attr($field_id); ?>">
                    <?php echo esc_html($args['label']); ?>
                </label>
            <?php endif; ?>
            <select id="<?php echo esc_attr($field_id); ?>" 
                    name="<?php echo esc_attr($field_id); ?>" 
                    class="<?php echo esc_attr($args['class']); ?>">
                <?php foreach ($args['options'] as $option_value => $option_label): ?>
                    <option value="<?php echo esc_attr($option_value); ?>" 
                            <?php selected($args['value'], $option_value); ?>>
                        <?php echo esc_html($option_label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($args['description']): ?>
                <p class="gdm-field-description"><?php echo esc_html($args['description']); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }
}