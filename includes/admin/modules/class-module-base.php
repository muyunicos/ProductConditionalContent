<?php
/**
 * Clase Base Abstracta para Módulos de Reglas
 * Compatible con WordPress 6.8.3, PHP 8.2, WooCommerce 10.2.2
 * 
 * @package ProductConditionalContent
 * @since 6.0.0
 * @date 2025-10-15
 */

if (!defined('ABSPATH')) exit;

abstract class GDM_Module_Base {
    
    protected $module_id = '';
    protected $module_name = '';
    protected $module_icon = '⚙️';
    protected $priority = 'default';
    protected static $cache = [];
    
    public function __construct() {
        if (empty($this->module_id) || empty($this->module_name)) {
            wp_die(__('El módulo debe definir module_id y module_name', 'product-conditional-content'));
        }
        
        add_action('add_meta_boxes', [$this, 'register_metabox']);
        add_action('save_post_gdm_regla', [$this, 'save_module_data'], 20, 2);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        
        $this->module_init();
    }
    
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
     * ✅ CORREGIDO: Siempre muestra el metabox, controla visibilidad con clases
     */
    public function render_metabox_wrapper($post) {
        $is_active = $this->is_module_active($post->ID);
        
        echo '<div class="gdm-module-wrapper" data-module="' . esc_attr($this->module_id) . '">';
        
        // ✅ Mensaje inactivo (se oculta con JS si está activo)
        echo '<div class="gdm-module-inactive" style="' . ($is_active ? 'display:none;' : '') . '">';
        $this->render_inactive_message();
        echo '</div>';
        
        // ✅ Contenido del módulo (se oculta con JS si está inactivo)
        echo '<div class="gdm-module-content" style="' . ($is_active ? '' : 'display:none;') . '">';
        $this->render_metabox($post);
        echo '</div>';
        
        echo '</div>';
        
        $this->render_base_styles();
    }
    
    /**
     * Mensaje cuando el módulo está inactivo
     */
    protected function render_inactive_message() {
        ?>
        <p>
            <span class="dashicons dashicons-info"></span>
            <?php 
            printf(
                __('Para usar este módulo, activa "%s" en la sección "Aplicar a" de la configuración general.', 'product-conditional-content'),
                '<strong>' . esc_html($this->module_name) . '</strong>'
            );
            ?>
        </p>
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
            .gdm-module-inactive p {
                margin: 0;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .gdm-module-inactive .dashicons {
                font-size: 20px;
                width: 20px;
                height: 20px;
            }
        </style>
        <?php
    }
    
    /**
     * Renderizar contenido del metabox (DEBE implementarse en clase hija)
     */
    abstract public function render_metabox($post);
    
    /**
     * Guardar datos del módulo (DEBE implementarse en clase hija)
     */
    abstract public function save_module_data($post_id, $post);
    
    /**
     * Obtener datos por defecto del módulo (DEBE implementarse en clase hija)
     */
    abstract protected function get_default_data();
    
    /**
     * Encolar assets específicos del módulo (opcional)
     */
    public function enqueue_assets($hook) {
        // Implementar en clase hija si se necesitan assets específicos
    }
    
    /**
     * Validar si el módulo está activo para esta regla
     */
    protected function is_module_active($post_id) {
        $aplicar_a = get_post_meta($post_id, '_gdm_aplicar_a', true) ?: [];
        return in_array($this->module_id, $aplicar_a);
    }
    
    /**
     * Helper: Obtener datos del módulo con caché
     */
    protected function get_module_data($post_id) {
        $cache_key = "{$this->module_id}_{$post_id}";
        
        if (isset(self::$cache[$cache_key])) {
            return self::$cache[$cache_key];
        }
        
        $default_data = $this->get_default_data();
        $data = [];
        
        foreach ($default_data as $key => $default_value) {
            $meta_key = "_{$this->module_id}_{$key}";
            $value = get_post_meta($post_id, $meta_key, true);
            $data[$key] = ($value !== '' && $value !== false) ? $value : $default_value;
        }
        
        self::$cache[$cache_key] = $data;
        return $data;
    }
    
    /**
     * Helper: Guardar un campo del módulo
     */
    protected function save_module_field($post_id, $field_name, $value) {
        $meta_key = "_{$this->module_id}_{$field_name}";
        update_post_meta($post_id, $meta_key, $value);
    }
    
    /**
     * Helper: Validar guardado
     */
    protected function validate_save($post_id, $post) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return false;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return false;
        }
        
        if ($post->post_type !== 'gdm_regla') {
            return false;
        }
        
        return true;
    }
}