<?php
/**
 * Rules Config Metabox v7.0 - CORREGIDO CON FUNCIONALIDAD DINÁMICA
 * Interfaz reorganizada + carga dinámica de conditions y actions
 * 
 * INTEGRA:
 * ✅ Orden de campos según especificación del usuario
 * ✅ Selector dinámico de categorías de contenido
 * ✅ Carga automática de CONDITIONS desde includes/admin/conditions/
 * ✅ Carga automática de ACTIONS desde includes/admin/actions/
 * ✅ Filtrado por categorías (productos, entradas, páginas)
 * ✅ Tooltips informativos y validación
 * 
 * @package ProductConditionalContent
 * @since 7.0.0
 */

if (!defined('ABSPATH')) exit;

class GDM_Rules_Config_Metabox {
    
    private static $instance = null;
    
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('add_meta_boxes', [$this, 'add_metaboxes']);
        add_action('save_post', [$this, 'save_rule_config'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        
        // Hook para guardar datos dinámicos
        add_action('save_post', [$this, 'save_dynamic_modules'], 15, 2);
    }
    
    /**
     * Agregar metaboxes
     */
    public function add_metaboxes() {
        add_meta_box(
            'gdm-rule-config',
            __('Configuración de la Regla', 'product-conditional-content'),
            [$this, 'render_config_metabox'],
            'gdm_regla',
            'side',
            'high'
        );
        
        add_meta_box(
            'gdm-conditions-config',
            __('Ámbitos de Aplicación (Condiciones)', 'product-conditional-content'),
            [$this, 'render_conditions_metabox'],
            'gdm_regla',
            'normal',
            'high'
        );
        
        add_meta_box(
            'gdm-actions-config',
            __('Aplica a (Acciones)', 'product-conditional-content'),
            [$this, 'render_actions_metabox'],
            'gdm_regla',
            'normal',
            'high'
        );
    }
    
    /**
     * Renderizar metabox de configuración (reorganizado)
     */
    public function render_config_metabox($post) {
        wp_nonce_field('gdm_rule_config_nonce', 'gdm_rule_config_nonce');
        
        // Obtener valores actuales
        $priority = get_post_meta($post->ID, '_gdm_regla_prioridad', true) ?: '10';
        $is_last = (bool) get_post_meta($post->ID, '_gdm_regla_ultima', true);
        $is_forced = (bool) get_post_meta($post->ID, '_gdm_regla_forzada', true);
        $is_reusable = (bool) get_post_meta($post->ID, '_gdm_regla_reutilizable', true);
        $content_categories = get_post_meta($post->ID, '_gdm_regla_content_categories', true) ?: ['productos'];
        
        ?>
        <div class="gdm-rule-config">
            
            <!-- CONFIGURACIÓN BÁSICA -->
            <div class="gdm-config-section">
                <h4><?php _e('Configuración', 'product-conditional-content'); ?></h4>
                
                <div class="gdm-field-group">
                    <label for="gdm_regla_prioridad" class="gdm-field-label">
                        <strong><?php _e('Prioridad', 'product-conditional-content'); ?></strong>
                        <span class="gdm-tooltip" data-tooltip="<?php esc_attr_e('A menor valor, mayor prioridad. Las reglas se ejecutan de menor a mayor número.', 'product-conditional-content'); ?>">ℹ️</span>
                    </label>
                    <input type="number" 
                           id="gdm_regla_prioridad" 
                           name="gdm_regla_prioridad" 
                           value="<?php echo esc_attr($priority); ?>" 
                           min="1" 
                           max="999" 
                           step="1" 
                           class="widefat">
                    <p class="description"><?php _e('A menor valor, mayor prioridad', 'product-conditional-content'); ?></p>
                </div>
                
                <div class="gdm-field-group">
                    <label class="gdm-checkbox-label">
                        <input type="checkbox" 
                               name="gdm_regla_ultima" 
                               value="1" 
                               <?php checked($is_last, true); ?>
                               class="gdm-rule-option">
                        <strong><?php _e('Última Regla', 'product-conditional-content'); ?></strong>
                        <span class="gdm-tooltip" data-tooltip="<?php esc_attr_e('Anula reglas de menor prioridad. Si esta regla se ejecuta, las siguientes se ignoran.', 'product-conditional-content'); ?>">ℹ️</span>
                    </label>
                    <p class="description"><?php _e('Anula reglas de menor prioridad', 'product-conditional-content'); ?></p>
                </div>
                
                <div class="gdm-field-group">
                    <label class="gdm-checkbox-label">
                        <input type="checkbox" 
                               name="gdm_regla_forzada" 
                               value="1" 
                               <?php checked($is_forced, true); ?>
                               class="gdm-rule-option">
                        <strong><?php _e('Forzada', 'product-conditional-content'); ?></strong>
                        <span class="gdm-tooltip" data-tooltip="<?php esc_attr_e('Se activa siempre que se cumplan las condiciones, sin importar otras reglas.', 'product-conditional-content'); ?>">ℹ️</span>
                    </label>
                    <p class="description"><?php _e('Se activa siempre que se cumplan las condiciones', 'product-conditional-content'); ?></p>
                </div>
                
                <div class="gdm-field-group">
                    <label class="gdm-checkbox-label">
                        <input type="checkbox" 
                               name="gdm_regla_reutilizable" 
                               value="1" 
                               <?php checked($is_reusable, true); ?>
                               class="gdm-rule-option">
                        <strong><?php _e('Regla reutilizable', 'product-conditional-content'); ?></strong>
                        <span class="gdm-tooltip" data-tooltip="<?php esc_attr_e('Las reglas reutilizables se pueden aplicar dentro de otras reglas mediante shortcodes.', 'product-conditional-content'); ?>">ℹ️</span>
                    </label>
                    <p class="description"><?php _e('Las reglas reutilizables se pueden aplicar dentro de otras reglas', 'product-conditional-content'); ?></p>
                </div>
            </div>
            
            <hr class="gdm-separator">
            
            <!-- CATEGORÍAS DE CONTENIDO -->
            <div class="gdm-config-section">
                <h4><?php _e('Categorías de Contenido', 'product-conditional-content'); ?></h4>
                <p class="description"><?php _e('Selecciona los tipos de contenido que se verán afectados por esta regla', 'product-conditional-content'); ?></p>
                
                <div class="gdm-content-categories">
                    <?php $this->render_content_categories($content_categories); ?>
                </div>
            </div>
            
            <?php if ($is_reusable): ?>
            <div class="gdm-info-box gdm-shortcode-info">
                <h4><?php _e('📋 Shortcode', 'product-conditional-content'); ?></h4>
                <code>[gdm_content rule="<?php echo $post->ID; ?>"]</code>
                <p class="description"><?php _e('Usa este shortcode para aplicar esta regla en cualquier lugar.', 'product-conditional-content'); ?></p>
            </div>
            <?php endif; ?>
            
        </div>
        
        <style>
        .gdm-rule-config { font-size: 13px; }
        .gdm-config-section { margin-bottom: 20px; }
        .gdm-config-section h4 { margin: 0 0 15px 0; font-size: 14px; color: #1d2327; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
        .gdm-field-group { margin-bottom: 15px; }
        .gdm-field-label { display: block; margin-bottom: 5px; font-weight: 600; }
        .gdm-checkbox-label { display: flex; align-items: flex-start; gap: 8px; margin-bottom: 5px; }
        .gdm-checkbox-label input[type="checkbox"] { margin: 2px 0 0 0; }
        .gdm-tooltip { cursor: help; font-size: 12px; color: #2271b1; position: relative; }
        .gdm-tooltip:hover::after { content: attr(data-tooltip); position: absolute; bottom: 125%; left: 50%; transform: translateX(-50%); background: #333; color: white; padding: 5px 10px; border-radius: 3px; white-space: nowrap; font-size: 11px; z-index: 1000; max-width: 200px; white-space: normal; }
        .gdm-separator { border: 0; border-top: 1px solid #ddd; margin: 20px 0; }
        .gdm-content-categories { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 10px; }
        .gdm-category-item { display: flex; align-items: center; gap: 5px; padding: 5px 10px; border: 1px solid #ddd; border-radius: 3px; background: #f9f9f9; transition: all 0.2s ease; }
        .gdm-category-item:hover { background: #e6f3ff; border-color: #2271b1; }
        .gdm-category-item input[type="checkbox"] { margin: 0; }
        .gdm-info-box { padding: 10px; background: #e6f3ff; border-left: 4px solid #2271b1; border-radius: 3px; margin-bottom: 15px; }
        .gdm-info-box h4 { margin: 0 0 8px 0; border: none; padding: 0; }
        .gdm-shortcode-info { background: #f0f6fc; }
        .gdm-shortcode-info code { display: block; padding: 5px; background: #fff; border: 1px solid #ddd; border-radius: 3px; margin: 5px 0; }
        .description { font-size: 11px; color: #646970; margin: 3px 0 0 0; }
        </style>
        <?php
    }
    
    /**
     * Renderizar categorías de contenido
     */
    private function render_content_categories($selected_categories) {
        $categories = [
            'productos' => [
                'label' => __('Productos', 'product-conditional-content'),
                'icon' => '🛍️',
                'description' => __('Productos de WooCommerce', 'product-conditional-content')
            ],
            'entradas' => [
                'label' => __('Entradas', 'product-conditional-content'),
                'icon' => '📝',
                'description' => __('Posts del blog', 'product-conditional-content')
            ],
            'páginas' => [
                'label' => __('Páginas', 'product-conditional-content'),
                'icon' => '📄',
                'description' => __('Páginas estáticas', 'product-conditional-content')
            ]
        ];
        
        foreach ($categories as $key => $category) {
            $checked = in_array($key, $selected_categories);
            ?>
            <label class="gdm-category-item" data-category="<?php echo esc_attr($key); ?>">
                <input type="checkbox" 
                       name="gdm_regla_content_categories[]" 
                       value="<?php echo esc_attr($key); ?>" 
                       <?php checked($checked, true); ?>
                       class="gdm-content-category-checkbox">
                <span><?php echo $category['icon']; ?> <?php echo esc_html($category['label']); ?></span>
            </label>
            <?php
        }
    }
    
    /**
     * Renderizar metabox de CONDITIONS (dinámico)
     */
    public function render_conditions_metabox($post) {
        $condition_manager = GDM_Condition_Manager::instance();
        $conditions = $condition_manager->get_conditions_ordered();
        $content_categories = get_post_meta($post->ID, '_gdm_regla_content_categories', true) ?: ['productos'];
        
        ?>
        <div class="gdm-conditions-wrapper">
            <div class="gdm-conditions-info">
                <p><?php _e('Las condiciones determinan <strong>cuándo</strong> se aplicará esta regla. Se filtran automáticamente según las categorías de contenido seleccionadas.', 'product-conditional-content'); ?></p>
            </div>
            
            <div id="gdm-conditions-container">
                <?php foreach ($conditions as $id => $config): ?>
                    <?php 
                    // Filtrar por categoría
                    if (!$this->condition_supports_categories($id, $content_categories)) {
                        continue;
                    }
                    
                    $instance = $condition_manager->get_condition_instance($id);
                    if (!$instance) {
                        continue;
                    }
                    ?>
                    
                    <div class="gdm-condition-module" data-condition="<?php echo esc_attr($id); ?>" data-categories="<?php echo esc_attr(implode(',', $this->get_condition_categories($id))); ?>">
                        <div class="gdm-module-header">
                            <h4>
                                <span class="gdm-module-icon"><?php echo $config['icon']; ?></span>
                                <?php echo esc_html($config['label']); ?>
                                <span class="gdm-module-toggle">
                                    <input type="checkbox" 
                                           name="gdm_conditions[<?php echo esc_attr($id); ?>][enabled]" 
                                           value="1"
                                           class="gdm-condition-toggle">
                                </span>
                            </h4>
                        </div>
                        
                        <div class="gdm-module-content" style="display: none;">
                            <?php 
                            // Renderizar el módulo condition usando su instancia
                            $instance->render($post->ID);
                            ?>
                        </div>
                    </div>
                    
                <?php endforeach; ?>
            </div>
        </div>
        
        <style>
        .gdm-conditions-wrapper { font-size: 13px; }
        .gdm-conditions-info { margin-bottom: 20px; padding: 15px; background: #f0f6fc; border-left: 4px solid #2271b1; }
        .gdm-condition-module { margin-bottom: 15px; border: 1px solid #ddd; border-radius: 4px; background: #fff; }
        .gdm-module-header { padding: 12px 15px; background: #f9f9f9; border-bottom: 1px solid #eee; cursor: pointer; }
        .gdm-module-header h4 { margin: 0; display: flex; align-items: center; justify-content: space-between; font-size: 13px; }
        .gdm-module-icon { margin-right: 8px; }
        .gdm-module-content { padding: 15px; }
        .gdm-module-toggle input[type="checkbox"] { margin: 0; }
        </style>
        <?php
    }
    
    /**
     * Renderizar metabox de ACTIONS (dinámico)
     */
    public function render_actions_metabox($post) {
        $action_manager = GDM_Action_Manager::instance();
        $actions = $action_manager->get_actions_ordered();
        $content_categories = get_post_meta($post->ID, '_gdm_regla_content_categories', true) ?: ['productos'];
        
        ?>
        <div class="gdm-actions-wrapper">
            <div class="gdm-actions-info">
                <p><?php _e('Las acciones determinan <strong>qué</strong> modificar cuando se cumplan las condiciones. Se filtran automáticamente según las categorías de contenido seleccionadas.', 'product-conditional-content'); ?></p>
            </div>
            
            <div id="gdm-actions-container">
                <?php foreach ($actions as $id => $config): ?>
                    <?php 
                    // Filtrar por categoría
                    if (!$this->action_supports_categories($id, $content_categories)) {
                        continue;
                    }
                    
                    $instance = $action_manager->get_action_instance($id);
                    if (!$instance) {
                        continue;
                    }
                    ?>
                    
                    <div class="gdm-action-module" data-action="<?php echo esc_attr($id); ?>" data-categories="<?php echo esc_attr(implode(',', $this->get_action_categories($id))); ?>">
                        <div class="gdm-module-header">
                            <h4>
                                <span class="gdm-module-icon"><?php echo $config['icon']; ?></span>
                                <?php echo esc_html($config['label']); ?>
                                <span class="gdm-module-toggle">
                                    <input type="checkbox" 
                                           name="gdm_actions[<?php echo esc_attr($id); ?>][enabled]" 
                                           value="1"
                                           class="gdm-action-toggle">
                                </span>
                            </h4>
                        </div>
                        
                        <div class="gdm-module-content" style="display: none;">
                            <?php 
                            // Renderizar el módulo action usando su instancia
                            $instance->render($post->ID);
                            ?>
                        </div>
                    </div>
                    
                <?php endforeach; ?>
            </div>
        </div>
        
        <style>
        .gdm-actions-wrapper { font-size: 13px; }
        .gdm-actions-info { margin-bottom: 20px; padding: 15px; background: #f0f6fc; border-left: 4px solid #2271b1; }
        .gdm-action-module { margin-bottom: 15px; border: 1px solid #ddd; border-radius: 4px; background: #fff; }
        </style>
        <?php
    }
    
    /**
     * Verificar si condition soporta las categorías seleccionadas
     */
    private function condition_supports_categories($condition_id, $content_categories) {
        // Mapeo de conditions a categorías
        $condition_categories = [
            'productos' => ['productos', 'categorias', 'etiquetas', 'atributos', 'tipos', 'precio', 'stock'],
            'entradas' => ['titulo', 'categorias', 'etiquetas'],
            'páginas' => ['titulo']
        ];
        
        foreach ($content_categories as $category) {
            if (isset($condition_categories[$category]) && in_array($condition_id, $condition_categories[$category])) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Verificar si action soporta las categorías seleccionadas
     */
    private function action_supports_categories($action_id, $content_categories) {
        // Mapeo de actions a categorías
        $action_categories = [
            'productos' => ['price', 'description', 'gallery', 'featured', 'title'],
            'entradas' => ['description', 'featured', 'title'],
            'páginas' => ['description', 'featured', 'title']
        ];
        
        foreach ($content_categories as $category) {
            if (isset($action_categories[$category]) && in_array($action_id, $action_categories[$category])) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Obtener categorías soportadas por condition
     */
    private function get_condition_categories($condition_id) {
        $mappings = [
            'productos' => ['productos'],
            'categorias' => ['productos', 'entradas'],
            'etiquetas' => ['productos', 'entradas'],
            'atributos' => ['productos'],
            'tipos' => ['productos'],
            'precio' => ['productos'],
            'stock' => ['productos'],
            'titulo' => ['productos', 'entradas', 'páginas']
        ];
        
        return $mappings[$condition_id] ?? [];
    }
    
    /**
     * Obtener categorías soportadas por action
     */
    private function get_action_categories($action_id) {
        $mappings = [
            'price' => ['productos'],
            'description' => ['productos', 'entradas', 'páginas'],
            'gallery' => ['productos'],
            'featured' => ['productos', 'entradas', 'páginas'],
            'title' => ['productos', 'entradas', 'páginas']
        ];
        
        return $mappings[$action_id] ?? [];
    }
    
    /**
     * Guardar configuración de la regla
     */
    public function save_rule_config($post_id, $post) {
        // Verificaciones de seguridad
        if (!isset($_POST['gdm_rule_config_nonce']) || 
            !wp_verify_nonce($_POST['gdm_rule_config_nonce'], 'gdm_rule_config_nonce')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if ($post->post_type !== 'gdm_regla') {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Guardar configuración básica
        $priority = isset($_POST['gdm_regla_prioridad']) ? (int) $_POST['gdm_regla_prioridad'] : 10;
        update_post_meta($post_id, '_gdm_regla_prioridad', $priority);
        
        update_post_meta($post_id, '_gdm_regla_ultima', !empty($_POST['gdm_regla_ultima']));
        update_post_meta($post_id, '_gdm_regla_forzada', !empty($_POST['gdm_regla_forzada']));
        update_post_meta($post_id, '_gdm_regla_reutilizable', !empty($_POST['gdm_regla_reutilizable']));
        
        // Guardar categorías de contenido
        $content_categories = isset($_POST['gdm_regla_content_categories']) ? 
                             (array) $_POST['gdm_regla_content_categories'] : ['productos'];
        update_post_meta($post_id, '_gdm_regla_content_categories', $content_categories);
    }
    
    /**
     * Guardar datos de módulos dinámicos (conditions y actions)
     */
    public function save_dynamic_modules($post_id, $post) {
        if ($post->post_type !== 'gdm_regla') {
            return;
        }
        
        // Usar los managers para guardar
        do_action('gdm_save_conditions_data', $post_id, $post);
        do_action('gdm_save_modules_data', $post_id, $post); // Para actions (retrocompatibilidad)
    }
    
    /**
     * Cargar assets del admin
     */
    public function enqueue_admin_assets($hook) {
        global $post_type;
        
        if ($post_type !== 'gdm_regla') {
            return;
        }
        
        wp_enqueue_script(
            'gdm-rules-config-dynamic',
            GDM_PLUGIN_URL . 'assets/admin/js/rules-config-dynamic.js',
            ['jquery'],
            GDM_VERSION,
            true
        );
        
        wp_localize_script('gdm-rules-config-dynamic', 'gdm_rules_config', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gdm_rules_config'),
        ]);
    }
}

// Inicializar metabox
GDM_Rules_Config_Metabox::instance();