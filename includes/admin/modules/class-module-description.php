<?php
/**
 * Módulo: Descripción del Producto
 * Gestión modular de descripciones largas y cortas con variantes condicionales
 * Compatible con WordPress 6.8.3, PHP 8.2, WooCommerce 10.2.2
 * 
 * @package ProductConditionalContent
 * @since 6.0.0
 * @author MuyUnicos
 * @date 2025-10-15
 */

if (!defined('ABSPATH')) exit;

final class GDM_Module_Descripcion extends GDM_Module_Base {
    
    protected $module_id = 'descripcion';
    protected $module_name = 'Descripción';
    protected $module_icon = '📝';
    protected $priority = 'high';
    
    /**
     * Inicialización específica del módulo
     */
    protected function module_init() {
        // Registrar AJAX para reglas reutilizables
        add_action('wp_ajax_gdm_get_reusable_rules', [$this, 'handle_ajax_get_reusable_rules']);
    }
    
    /**
     * Renderizar metabox
     */
    public function render_metabox($post) {
        $data = $this->get_module_data($post->ID);
        ?>
        <div class="gdm-module-descripcion">
            
            <!-- Header con iconos de ayuda -->
            <div class="gdm-module-header">
                <h4>
                    <span class="dashicons dashicons-edit"></span>
                    <?php _e('Configuración de Descripción', 'product-conditional-content'); ?>
                </h4>
                <span class="description">
                    <?php _e('Define cómo y dónde se aplicará el contenido de esta regla', 'product-conditional-content'); ?>
                </span>
            </div>
            
            <!-- Tipo de Descripción -->
            <div class="gdm-field-group">
                <label>
                    <strong><?php _e('Aplicar a:', 'product-conditional-content'); ?></strong>
                </label>
                <p class="gdm-field-description">
                    <?php _e('Selecciona si esta regla afectará la descripción larga, corta o ambas.', 'product-conditional-content'); ?>
                </p>
                <label>
                    <input type="checkbox" 
                           name="gdm_descripcion_tipos[]" 
                           value="larga"
                           <?php checked(in_array('larga', $data['tipos'])); ?>>
                    <?php _e('Descripción Larga (pestaña "Descripción")', 'product-conditional-content'); ?>
                </label>
                <br>
                <label>
                    <input type="checkbox" 
                           name="gdm_descripcion_tipos[]" 
                           value="corta"
                           <?php checked(in_array('corta', $data['tipos'])); ?>>
                    <?php _e('Descripción Corta (resumen del producto)', 'product-conditional-content'); ?>
                </label>
            </div>
            
            <!-- Ubicación de la Regla -->
            <?php
            $this->render_select_field('gdm_descripcion_ubicacion', [
                'label' => __('Ubicación de la Regla:', 'product-conditional-content'),
                'value' => $data['ubicacion'],
                'options' => [
                    'reemplaza' => __('Reemplaza la descripción original', 'product-conditional-content'),
                    'antes' => __('Añadir antes de la descripción original', 'product-conditional-content'),
                    'despues' => __('Añadir después de la descripción original', 'product-conditional-content'),
                    'solo_vacia' => __('Solo si la descripción está vacía', 'product-conditional-content'),
                ],
                'description' => __('Define cómo se fusionará el contenido de la regla con la descripción del producto.', 'product-conditional-content'),
            ]);
            ?>
            
            <hr>
            
            <!-- Editor de Contenido Principal -->
            <div class="gdm-field-group">
                <label>
                    <strong><?php _e('📄 Contenido de la Regla:', 'product-conditional-content'); ?></strong>
                </label>
                <p class="gdm-field-description">
                    <?php _e('Escribe el contenido que se aplicará. Puedes usar comodines y shortcodes.', 'product-conditional-content'); ?>
                </p>
                
                <!-- Botones de Comodines -->
                <div class="gdm-comodines-toolbar">
                    <button type="button" class="button button-small gdm-insert-shortcode" data-shortcode="[nombre-prod]" title="Nombre del producto">
                        <span class="dashicons dashicons-tag"></span> [nombre-prod]
                    </button>
                    <button type="button" class="button button-small gdm-insert-shortcode" data-shortcode="[precio-prod]" title="Precio del producto">
                        <span class="dashicons dashicons-money-alt"></span> [precio-prod]
                    </button>
                    <button type="button" class="button button-small gdm-insert-shortcode" data-shortcode="[sku-prod]" title="SKU del producto">
                        <span class="dashicons dashicons-admin-network"></span> [sku-prod]
                    </button>
                    <button type="button" class="button button-small gdm-insert-shortcode" data-shortcode="[slug-prod]" title="Slug del producto">
                        <span class="dashicons dashicons-admin-links"></span> [slug-prod]
                    </button>
                    <button type="button" class="button button-small gdm-insert-shortcode" data-shortcode="[var-cond]" title="Placeholder para variantes">
                        <span class="dashicons dashicons-admin-settings"></span> [var-cond]
                    </button>
                    <button type="button" class="button button-small gdm-insert-rule-id" title="Insertar regla reutilizable">
                        <span class="dashicons dashicons-admin-page"></span> [rule-id]
                    </button>
                </div>
                
                <?php
                wp_editor($data['contenido'], 'gdm_descripcion_contenido', [
                    'textarea_name' => 'gdm_descripcion_contenido',
                    'media_buttons' => false,
                    'textarea_rows' => 12,
                    'teeny' => false,
                    'tinymce' => [
                        'toolbar1' => 'bold,italic,underline,bullist,numlist,link,unlink,undo,redo',
                    ],
                    'quicktags' => true
                ]);
                ?>
            </div>
            
            <hr>
            
            <!-- Variantes Condicionales -->
            <div class="gdm-field-group">
                <label>
                    <strong><?php _e('🔀 Variantes Condicionales:', 'product-conditional-content'); ?></strong>
                </label>
                <p class="gdm-field-description">
                    <?php _e('Define contenido alternativo basado en tags o metadatos del producto. El placeholder [var-cond] será reemplazado por el texto de la variante que cumpla la condición.', 'product-conditional-content'); ?>
                </p>
                
                <!-- Tabla de Variantes -->
                <table class="gdm-variantes-table widefat">
                    <thead>
                        <tr>
                            <th width="30"><?php _e('#', 'product-conditional-content'); ?></th>
                            <th width="40" class="sort-handle-header">
                                <span class="dashicons dashicons-menu" title="<?php _e('Arrastrar para ordenar', 'product-conditional-content'); ?>"></span>
                            </th>
                            <th width="120"><?php _e('Tipo', 'product-conditional-content'); ?></th>
                            <th><?php _e('Clave', 'product-conditional-content'); ?></th>
                            <th><?php _e('Valor', 'product-conditional-content'); ?></th>
                            <th width="150"><?php _e('Acción', 'product-conditional-content'); ?></th>
                            <th><?php _e('Texto de Variante', 'product-conditional-content'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="gdm-variantes-tbody" class="gdm-sortable">
                        <?php
                        if (!empty($data['variantes']) && is_array($data['variantes'])) {
                            foreach ($data['variantes'] as $index => $variante) {
                                $this->render_variante_row($index, $variante);
                            }
                        } else {
                            // Fila por defecto
                            $this->render_variante_row(0, []);
                        }
                        ?>
                    </tbody>
                </table>
                
                <!-- Acciones de Variantes -->
                <div class="gdm-variantes-actions">
                    <button type="button" id="gdm-add-variante" class="button button-primary">
                        <span class="dashicons dashicons-plus-alt2"></span>
                        <?php _e('Agregar Variante', 'product-conditional-content'); ?>
                    </button>
                    <button type="button" id="gdm-delete-selected-variantes" class="button" disabled>
                        <span class="dashicons dashicons-trash"></span>
                        <?php _e('Eliminar Seleccionadas', 'product-conditional-content'); ?>
                    </button>
                    <span id="gdm-variantes-counter" class="gdm-counter">
                        <?php printf(__('Total: %d variante(s)', 'product-conditional-content'), count($data['variantes'])); ?>
                    </span>
                </div>
                
                <!-- Template oculto para nuevas variantes -->
                <script type="text/html" id="gdm-variante-template">
                    <?php $this->render_variante_row('__INDEX__', []); ?>
                </script>
            </div>
            
            <hr>
            
            <!-- Opciones Avanzadas -->
            <div class="gdm-field-group">
                <label>
                    <strong><?php _e('⚙️ Opciones Avanzadas:', 'product-conditional-content'); ?></strong>
                </label>
                
                <?php
                $this->render_checkbox_field('gdm_descripcion_regla_final', [
                    'label' => __('Regla Final - No procesar más reglas después de esta', 'product-conditional-content'),
                    'checked' => $data['regla_final'] === '1',
                    'description' => __('Si está activado, esta regla detendrá el procesamiento de otras reglas de descripción.', 'product-conditional-content'),
                ]);
                
                $this->render_checkbox_field('gdm_descripcion_forzar', [
                    'label' => __('Forzar Aplicación - Ignorar otras reglas finales', 'product-conditional-content'),
                    'checked' => $data['forzar'] === '1',
                    'description' => __('Esta regla se aplicará aunque otra regla anterior sea marcada como "Regla Final".', 'product-conditional-content'),
                ]);
                ?>
            </div>
            
        </div>
        
        <!-- Estilos específicos del módulo -->
        <style>
            .gdm-module-descripcion .gdm-comodines-toolbar {
                margin-bottom: 10px;
                padding: 10px;
                background: #f0f0f1;
                border-radius: 4px;
                display: flex;
                flex-wrap: wrap;
                gap: 5px;
            }
            .gdm-module-descripcion .gdm-comodines-toolbar .button {
                flex: 0 0 auto;
            }
            .gdm-module-descripcion .gdm-variantes-table {
                margin-top: 10px;
                border: 1px solid #c3c4c7;
            }
            .gdm-module-descripcion .gdm-variantes-table th {
                background: #f6f7f7;
                font-weight: 600;
                padding: 8px;
            }
            .gdm-module-descripcion .gdm-variantes-table td {
                padding: 8px;
                vertical-align: middle;
            }
            .gdm-module-descripcion .sort-handle {
                cursor: move;
                color: #8c8f94;
                font-size: 18px;
            }
            .gdm-module-descripcion .sort-handle:hover {
                color: #1d2327;
            }
            .gdm-module-descripcion .gdm-sortable-placeholder {
                background: #f0f0f1;
                border: 2px dashed #c3c4c7;
                visibility: visible !important;
                height: 60px;
            }
            .gdm-module-descripcion .gdm-variantes-actions {
                margin-top: 10px;
                padding: 10px;
                background: #f6f7f7;
                border-radius: 4px;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .gdm-module-descripcion .gdm-counter {
                margin-left: auto;
                font-weight: 600;
                color: #1d2327;
            }
            .gdm-module-descripcion .gdm-variantes-actions .button .dashicons {
                vertical-align: middle;
                margin-right: 4px;
            }
        </style>
        <?php
    }
    
    /**
     * Renderizar fila de variante
     */
    private function render_variante_row($index, $variante = []) {
        $cond_type = $variante['cond_type'] ?? 'tag';
        $cond_key = $variante['cond_key'] ?? '';
        $cond_value = $variante['cond_value'] ?? '';
        $action = $variante['action'] ?? 'placeholder';
        $text = $variante['text'] ?? '';
        
        // Deshabilitar campos según el tipo de condición
        $key_disabled = ($cond_type === 'default') ? 'disabled' : '';
        $value_disabled = ($cond_type !== 'meta') ? 'disabled' : '';
        ?>
        <tr class="gdm-variante-row" data-index="<?php echo esc_attr($index); ?>">
            <td class="check-cell">
                <input type="checkbox" class="gdm-variante-checkbox">
            </td>
            <td class="sort-handle">
                <span class="dashicons dashicons-menu"></span>
            </td>
            <td>
                <select name="gdm_descripcion_variantes[<?php echo esc_attr($index); ?>][cond_type]" 
                        class="gdm-variante-cond-type widefat">
                    <option value="tag" <?php selected($cond_type, 'tag'); ?>>
                        <?php _e('Tag', 'product-conditional-content'); ?>
                    </option>
                    <option value="meta" <?php selected($cond_type, 'meta'); ?>>
                        <?php _e('Meta', 'product-conditional-content'); ?>
                    </option>
                    <option value="default" <?php selected($cond_type, 'default'); ?>>
                        <?php _e('Por Defecto', 'product-conditional-content'); ?>
                    </option>
                </select>
            </td>
            <td>
                <input type="text" 
                       class="gdm-variante-cond-key widefat" 
                       name="gdm_descripcion_variantes[<?php echo esc_attr($index); ?>][cond_key]" 
                       value="<?php echo esc_attr($cond_key); ?>" 
                       placeholder="slug-del-tag o meta_key"
                       <?php echo $key_disabled; ?>>
            </td>
            <td>
                <input type="text" 
                       class="gdm-variante-cond-value widefat" 
                       name="gdm_descripcion_variantes[<?php echo esc_attr($index); ?>][cond_value]" 
                       value="<?php echo esc_attr($cond_value); ?>" 
                       placeholder="valor (opcional)"
                       <?php echo $value_disabled; ?>>
            </td>
            <td>
                <select name="gdm_descripcion_variantes[<?php echo esc_attr($index); ?>][action]" class="widefat">
                    <option value="placeholder" <?php selected($action, 'placeholder'); ?>>
                        <?php _e('Reemplaza [var-cond]', 'product-conditional-content'); ?>
                    </option>
                    <option value="reemplaza_todo" <?php selected($action, 'reemplaza_todo'); ?>>
                        <?php _e('Reemplaza Todo', 'product-conditional-content'); ?>
                    </option>
                </select>
            </td>
            <td>
                <textarea name="gdm_descripcion_variantes[<?php echo esc_attr($index); ?>][text]" 
                          rows="2" 
                          class="widefat"><?php echo esc_textarea($text); ?></textarea>
            </td>
        </tr>
        <?php
    }
    
    /**
     * Guardar datos del módulo
     */
    public function save_module_data($post_id, $post) {
        if (!$this->validate_save($post_id, $post)) {
            return;
        }
        
        // Solo guardar si el módulo está activo
        if (!$this->is_module_active($post_id)) {
            return;
        }
        
        // Guardar tipos (larga/corta)
        $tipos = isset($_POST['gdm_descripcion_tipos']) && is_array($_POST['gdm_descripcion_tipos'])
            ? array_map('sanitize_text_field', $_POST['gdm_descripcion_tipos'])
            : ['larga']; // Default: larga
        $this->save_module_field($post_id, 'tipos', $tipos);
        
        // Guardar ubicación
        $ubicacion = isset($_POST['gdm_descripcion_ubicacion']) 
            ? sanitize_text_field($_POST['gdm_descripcion_ubicacion']) 
            : 'reemplaza';
        $this->save_module_field($post_id, 'ubicacion', $ubicacion);
        
        // Guardar contenido
        $contenido = isset($_POST['gdm_descripcion_contenido']) 
            ? wp_kses_post($_POST['gdm_descripcion_contenido']) 
            : '';
        $this->save_module_field($post_id, 'contenido', $contenido);
        
        // Guardar variantes
        $variantes_sanitizadas = [];
        if (isset($_POST['gdm_descripcion_variantes']) && is_array($_POST['gdm_descripcion_variantes'])) {
            foreach ($_POST['gdm_descripcion_variantes'] as $variante) {
                // Validar que tenga tipo de condición
                if (!isset($variante['cond_type'])) {
                    continue;
                }
                
                // Validar que tenga clave si no es "default"
                if ($variante['cond_type'] !== 'default' && empty($variante['cond_key'])) {
                    continue;
                }
                
                $variantes_sanitizadas[] = [
                    'cond_type' => sanitize_text_field($variante['cond_type']),
                    'cond_key' => isset($variante['cond_key']) ? sanitize_text_field($variante['cond_key']) : '',
                    'cond_value' => isset($variante['cond_value']) ? sanitize_text_field($variante['cond_value']) : '',
                    'action' => isset($variante['action']) ? sanitize_text_field($variante['action']) : 'placeholder',
                    'text' => isset($variante['text']) ? wp_kses_post($variante['text']) : '',
                ];
            }
        }
        $this->save_module_field($post_id, 'variantes', $variantes_sanitizadas);
        
        // Guardar opciones avanzadas
        $regla_final = isset($_POST['gdm_descripcion_regla_final']) ? '1' : '0';
        $this->save_module_field($post_id, 'regla_final', $regla_final);
        
        $forzar = isset($_POST['gdm_descripcion_forzar']) ? '1' : '0';
        $this->save_module_field($post_id, 'forzar', $forzar);
    }
    
    /**
     * Datos por defecto
     */
    protected function get_default_data() {
        return [
            'tipos' => ['larga'],
            'ubicacion' => 'reemplaza',
            'contenido' => '',
            'variantes' => [],
            'regla_final' => '0',
            'forzar' => '0',
        ];
    }
    
    /**
     * Encolar assets específicos
     */
    public function enqueue_assets($hook) {
        if (!in_array($hook, ['post.php', 'post-new.php'])) {
            return;
        }
        
        $screen = get_current_screen();
        if ($screen->id !== 'gdm_regla') {
            return;
        }
        
        // Encolar script específico del módulo
        wp_enqueue_script(
            'gdm-module-descripcion',
            GDM_PLUGIN_URL . 'assets/admin/js/modules/module-description.js',
            ['jquery', 'jquery-ui-sortable', 'wp-editor'],
            GDM_VERSION,
            true
        );
        
        wp_localize_script('gdm-module-descripcion', 'gdmModuloDescripcion', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gdm_admin_nonce'),
            'i18n' => [
                'deleteConfirm' => __('¿Eliminar la variante seleccionada?', 'product-conditional-content'),
                'deleteMultipleConfirm' => __('¿Eliminar %d variantes seleccionadas?', 'product-conditional-content'),
                'selectRule' => __('Seleccionar Regla Reutilizable', 'product-conditional-content'),
                'cancel' => __('Cancelar', 'product-conditional-content'),
                'insert' => __('Insertar', 'product-conditional-content'),
            ]
        ]);
    }
    
    /**
     * AJAX: Obtener reglas reutilizables
     */
    public function handle_ajax_get_reusable_rules() {
        check_ajax_referer('gdm_admin_nonce', 'nonce');
        
        $current_post_id = isset($_POST['current_post_id']) ? intval($_POST['current_post_id']) : 0;
        
        $rules = GDM_Admin_Helpers::get_available_reglas($current_post_id);
        
        $reusable_rules = [];
        foreach ($rules as $rule) {
            $aplicar_a = get_post_meta($rule->ID, '_gdm_aplicar_a', true) ?: [];
            
            // Verificar si es reutilizable (nuevo campo)
            $is_reutilizable = get_post_meta($rule->ID, '_gdm_reutilizable', true) === '1';
            
            if ($is_reutilizable) {
                $reusable_rules[] = [
                    'id' => $rule->ID,
                    'title' => $rule->post_title,
                ];
            }
        }
        
        wp_send_json_success($reusable_rules);
    }
}