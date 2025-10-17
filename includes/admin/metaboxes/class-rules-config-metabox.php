<?php
/**
 * Rules Config Metabox v7.0 - REORGANIZADO
 * Interfaz mejorada para configuración de reglas
 * 
 * MEJORAS IMPLEMENTADAS:
 * ✅ Nuevo orden de campos según especificación
 * ✅ Selector de categorías de contenido
 * ✅ Tooltips informativos mejorados
 * ✅ Validación en tiempo real
 * ✅ UI más intuitiva y organizada
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
    }
    
    /**
     * Renderizar metabox de configuración
     */
    public function render_config_metabox($post) {
        wp_nonce_field('gdm_rule_config_nonce', 'gdm_rule_config_nonce');
        
        // Obtener valores actuales
        $priority = get_post_meta($post->ID, '_gdm_regla_prioridad', true) ?: '10';
        $is_last = (bool) get_post_meta($post->ID, '_gdm_regla_ultima', true);
        $is_forced = (bool) get_post_meta($post->ID, '_gdm_regla_forzada', true);
        $is_reusable = (bool) get_post_meta($post->ID, '_gdm_regla_reutilizable', true);
        $content_categories = get_post_meta($post->ID, '_gdm_regla_content_categories', true) ?: ['productos'];
        $contexts = get_post_meta($post->ID, '_gdm_regla_contextos', true) ?: ['products'];
        
        ?>
        <div class="gdm-rule-config">
            
            <!-- SECCIÓN 1: CONFIGURACIÓN BÁSICA -->
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
            
            <!-- SECCIÓN 2: ÁMBITOS DE APLICACIÓN -->
            <div class="gdm-config-section">
                <h4><?php _e('Ámbitos de Aplicación', 'product-conditional-content'); ?></h4>
                
                <div class="gdm-field-group">
                    <label class="gdm-field-label">
                        <strong><?php _e('Categorías de Contenido', 'product-conditional-content'); ?></strong>
                        <span class="gdm-tooltip" data-tooltip="<?php esc_attr_e('Selecciona qué tipos de contenido se verán afectados por esta regla.', 'product-conditional-content'); ?>">ℹ️</span>
                    </label>
                    
                    <div class="gdm-content-categories">
                        <?php $this->render_content_categories($content_categories); ?>
                    </div>
                </div>
                
                <div class="gdm-field-group" id="gdm-contexts-container">
                    <label class="gdm-field-label">
                        <strong><?php _e('Aplica a', 'product-conditional-content'); ?></strong>
                    </label>
                    
                    <div class="gdm-contexts" id="gdm-contexts-list">
                        <?php $this->render_contexts_by_categories($content_categories, $contexts); ?>
                    </div>
                </div>
            </div>
            
            <hr class="gdm-separator">
            
            <!-- SECCIÓN 3: INFORMACIÓN ADICIONAL -->
            <div class="gdm-config-section">
                <div class="gdm-info-box">
                    <h4><?php _e('🔍 Vista Previa de Configuración', 'product-conditional-content'); ?></h4>
                    <div id="gdm-config-preview">
                        <?php $this->render_config_preview($priority, $is_last, $is_forced, $is_reusable); ?>
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
            
        </div>
        
        <style>
        .gdm-rule-config {
            font-size: 13px;
        }
        
        .gdm-config-section {
            margin-bottom: 20px;
        }
        
        .gdm-config-section h4 {
            margin: 0 0 15px 0;
            font-size: 14px;
            color: #1d2327;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }
        
        .gdm-field-group {
            margin-bottom: 15px;
        }
        
        .gdm-field-label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        .gdm-checkbox-label {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            margin-bottom: 5px;
        }
        
        .gdm-checkbox-label input[type="checkbox"] {
            margin: 2px 0 0 0;
        }
        
        .gdm-tooltip {
            cursor: help;
            font-size: 12px;
            color: #2271b1;
            position: relative;
        }
        
        .gdm-tooltip:hover::after {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            background: #333;
            color: white;
            padding: 5px 10px;
            border-radius: 3px;
            white-space: nowrap;
            font-size: 11px;
            z-index: 1000;
            max-width: 200px;
            white-space: normal;
        }
        
        .gdm-separator {
            border: 0;
            border-top: 1px solid #ddd;
            margin: 20px 0;
        }
        
        .gdm-content-categories {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .gdm-category-item {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 5px 10px;
            border: 1px solid #ddd;
            border-radius: 3px;
            background: #f9f9f9;
            transition: all 0.2s ease;
        }
        
        .gdm-category-item:hover {
            background: #e6f3ff;
            border-color: #2271b1;
        }
        
        .gdm-category-item input[type="checkbox"] {
            margin: 0;
        }
        
        .gdm-contexts {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
            padding: 10px;
            background: #f6f7f7;
            border-radius: 3px;
        }
        
        .gdm-context-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
        }
        
        .gdm-context-item input[type="checkbox"] {
            margin: 0;
        }
        
        .gdm-info-box {
            padding: 10px;
            background: #e6f3ff;
            border-left: 4px solid #2271b1;
            border-radius: 3px;
            margin-bottom: 15px;
        }
        
        .gdm-info-box h4 {
            margin: 0 0 8px 0;
            border: none;
            padding: 0;
        }
        
        .gdm-shortcode-info {
            background: #f0f6fc;
        }
        
        .gdm-shortcode-info code {
            display: block;
            padding: 5px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 3px;
            margin: 5px 0;
        }
        
        #gdm-config-preview {
            font-size: 11px;
            color: #666;
        }
        
        .description {
            font-size: 11px;
            color: #646970;
            margin: 3px 0 0 0;
        }
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
     * Renderizar contextos según categorías seleccionadas
     */
    private function render_contexts_by_categories($selected_categories, $selected_contexts) {
        $category_contexts = [
            'productos' => [
                'products' => __('Páginas de productos individuales', 'product-conditional-content'),
                'wc_shop' => __('Página de tienda y categorías', 'product-conditional-content'),
                'wc_cart' => __('Página de carrito', 'product-conditional-content'),
                'wc_checkout' => __('Página de checkout', 'product-conditional-content'),
            ],
            'entradas' => [
                'posts' => __('Páginas de entradas individuales', 'product-conditional-content'),
                'archive' => __('Páginas de archivo del blog', 'product-conditional-content'),
                'category' => __('Páginas de categorías', 'product-conditional-content'),
                'tag' => __('Páginas de etiquetas', 'product-conditional-content'),
            ],
            'páginas' => [
                'pages' => __('Páginas individuales', 'product-conditional-content'),
                'front_page' => __('Página de inicio', 'product-conditional-content'),
                'blog_page' => __('Página del blog', 'product-conditional-content'),
            ]
        ];
        
        foreach ($selected_categories as $category) {
            if (isset($category_contexts[$category])) {
                ?>
                <div class="gdm-category-contexts" data-category="<?php echo esc_attr($category); ?>">
                    <h5><?php 
                    switch($category) {
                        case 'productos': echo '🛍️ ' . __('Productos', 'product-conditional-content'); break;
                        case 'entradas': echo '📝 ' . __('Entradas', 'product-conditional-content'); break;
                        case 'páginas': echo '📄 ' . __('Páginas', 'product-conditional-content'); break;
                    }
                    ?></h5>
                    <?php foreach ($category_contexts[$category] as $context_key => $context_label): ?>
                        <label class="gdm-context-item">
                            <input type="checkbox" 
                                   name="gdm_regla_contextos[]" 
                                   value="<?php echo esc_attr($context_key); ?>"
                                   <?php checked(in_array($context_key, $selected_contexts), true); ?>>
                            <?php echo esc_html($context_label); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?php
            }
        }
    }
    
    /**
     * Renderizar vista previa de configuración
     */
    private function render_config_preview($priority, $is_last, $is_forced, $is_reusable) {
        ?>
        <div id="preview-content">
            <p><strong><?php _e('Prioridad:', 'product-conditional-content'); ?></strong> 
               <span id="preview-priority"><?php echo esc_html($priority); ?></span>
               <small>(<?php echo $priority <= 5 ? __('Alta prioridad', 'product-conditional-content') : 
                                ($priority <= 15 ? __('Prioridad media', 'product-conditional-content') : 
                                __('Baja prioridad', 'product-conditional-content')); ?>)</small>
            </p>
            
            <p><strong><?php _e('Comportamiento:', 'product-conditional-content'); ?></strong></p>
            <ul id="preview-behavior">
                <?php if ($is_forced): ?>
                    <li>⚡ <?php _e('Regla forzada - siempre se ejecuta', 'product-conditional-content'); ?></li>
                <?php endif; ?>
                <?php if ($is_last): ?>
                    <li>🛑 <?php _e('Última regla - bloquea reglas posteriores', 'product-conditional-content'); ?></li>
                <?php endif; ?>
                <?php if ($is_reusable): ?>
                    <li>🔄 <?php _e('Reutilizable - disponible como shortcode', 'product-conditional-content'); ?></li>
                <?php endif; ?>
                <?php if (!$is_forced && !$is_last && !$is_reusable): ?>
                    <li>📋 <?php _e('Regla estándar', 'product-conditional-content'); ?></li>
                <?php endif; ?>
            </ul>
        </div>
        <?php
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
        
        // Guardar prioridad
        $priority = isset($_POST['gdm_regla_prioridad']) ? 
                   (int) $_POST['gdm_regla_prioridad'] : 10;
        update_post_meta($post_id, '_gdm_regla_prioridad', $priority);
        
        // Guardar checkboxes
        update_post_meta($post_id, '_gdm_regla_ultima', !empty($_POST['gdm_regla_ultima']));
        update_post_meta($post_id, '_gdm_regla_forzada', !empty($_POST['gdm_regla_forzada']));
        update_post_meta($post_id, '_gdm_regla_reutilizable', !empty($_POST['gdm_regla_reutilizable']));
        
        // Guardar categorías de contenido
        $content_categories = isset($_POST['gdm_regla_content_categories']) ? 
                             (array) $_POST['gdm_regla_content_categories'] : ['productos'];
        update_post_meta($post_id, '_gdm_regla_content_categories', $content_categories);
        
        // Guardar contextos
        $contexts = isset($_POST['gdm_regla_contextos']) ? 
                   (array) $_POST['gdm_regla_contextos'] : ['products'];
        update_post_meta($post_id, '_gdm_regla_contextos', $contexts);
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
            'gdm-rule-config',
            GDM_PLUGIN_URL . 'assets/admin/js/rule-config.js',
            ['jquery'],
            GDM_VERSION,
            true
        );
        
        wp_localize_script('gdm-rule-config', 'gdm_rule_config', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gdm_rule_config'),
        ]);
    }
}

// Inicializar metabox
GDM_Rules_Config_Metabox::instance();