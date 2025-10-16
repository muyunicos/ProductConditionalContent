<?php
/**
 * Módulo: Gestión de Variantes Condicionales
 * Sistema independiente de variantes con condiciones dinámicas
 * Compatible con WordPress 6.8.3, PHP 8.2, WooCommerce 10.2.2
 * 
 * @package ProductConditionalContent
 * @since 6.2.1
 * @author MuyUnicos
 * @date 2025-10-15
 */

if (!defined('ABSPATH')) exit;

final class GDM_Module_Variants extends GDM_Module_Base {
    
    protected $module_id = 'variantes';
    protected $module_name = 'Variantes Condicionales';
    protected $module_icon = '🔀';
    protected $priority = 'high';
    
    /**
     * Renderizar metabox
     */
    public function render_metabox($post) {
        $data = $this->get_module_data($post->ID);
        ?>
        <div class="gdm-module-variantes">
            
            <!-- Header explicativo -->
            <div class="gdm-module-header">
                <h4>
                    <span class="dashicons dashicons-randomize"></span>
                    <?php _e('Sistema de Variantes Condicionales', 'product-conditional-content'); ?>
                </h4>
                <p class="description">
                    <?php _e('Define contenido alternativo basado en condiciones específicas del producto (tags, metadatos, atributos). Usa el placeholder [var-cond] en otros módulos para insertar el contenido de la variante que coincida.', 'product-conditional-content'); ?>
                </p>
            </div>
            
            <hr class="gdm-separator">
            
            <!-- Tabla de Variantes -->
            <div class="gdm-field-group">
                <label>
                    <strong><?php _e('🔀 Configuración de Variantes:', 'product-conditional-content'); ?></strong>
                </label>
                
                <table class="gdm-variantes-table widefat">
                    <thead>
                        <tr>
                            <th width="30">
                                <input type="checkbox" id="gdm-select-all-variantes" title="<?php esc_attr_e('Seleccionar todo', 'product-conditional-content'); ?>">
                            </th>
                            <th width="40" class="sort-handle-header">
                                <span class="dashicons dashicons-menu" title="<?php esc_attr_e('Arrastrar para ordenar', 'product-conditional-content'); ?>"></span>
                            </th>
                            <th width="30"><?php _e('#', 'product-conditional-content'); ?></th>
                            <th width="120"><?php _e('Tipo de Condición', 'product-conditional-content'); ?></th>
                            <th><?php _e('Clave/Slug', 'product-conditional-content'); ?></th>
                            <th><?php _e('Valor Esperado', 'product-conditional-content'); ?></th>
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
                            // Fila de ejemplo
                            $this->render_variante_row(0, [
                                'cond_type' => 'tag',
                                'cond_key' => '',
                                'cond_value' => '',
                                'action' => 'placeholder',
                                'text' => '',
                            ]);
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
                    
                    <button type="button" id="gdm-duplicate-variante" class="button" disabled>
                        <span class="dashicons dashicons-admin-page"></span>
                        <?php _e('Duplicar Seleccionada', 'product-conditional-content'); ?>
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
            
            <hr class="gdm-separator">
            
            <!-- Opciones Avanzadas -->
            <div class="gdm-field-group">
                <label>
                    <strong><?php _e('⚙️ Configuración de Evaluación:', 'product-conditional-content'); ?></strong>
                </label>
                
                <div style="margin-top: 10px;">
                    <label>
                        <input type="radio" 
                               name="gdm_variantes_modo" 
                               value="primera" 
                               <?php checked($data['modo'], 'primera'); ?>>
                        <strong><?php _e('Detener en primera coincidencia', 'product-conditional-content'); ?></strong>
                    </label>
                    <p class="description" style="margin-left: 24px;">
                        <?php _e('Se aplicará solo la primera variante que cumpla la condición (más rápido)', 'product-conditional-content'); ?>
                    </p>
                </div>
                
                <div style="margin-top: 15px;">
                    <label>
                        <input type="radio" 
                               name="gdm_variantes_modo" 
                               value="todas" 
                               <?php checked($data['modo'], 'todas'); ?>>
                        <strong><?php _e('Evaluar todas las variantes', 'product-conditional-content'); ?></strong>
                    </label>
                    <p class="description" style="margin-left: 24px;">
                        <?php _e('Se evaluarán todas las variantes y se concatenarán las que coincidan', 'product-conditional-content'); ?>
                    </p>
                </div>
                
                <div style="margin-top: 15px;">
                    <label>
                        <input type="radio" 
                               name="gdm_variantes_modo" 
                               value="prioridad" 
                               <?php checked($data['modo'], 'prioridad'); ?>>
                        <strong><?php _e('Por orden de prioridad (número menor gana)', 'product-conditional-content'); ?></strong>
                    </label>
                    <p class="description" style="margin-left: 24px;">
                        <?php _e('Se aplicará la variante con menor número de orden que coincida', 'product-conditional-content'); ?>
                    </p>
                </div>
            </div>
            
            <hr class="gdm-separator">
            
            <!-- Fallback -->
            <div class="gdm-field-group">
                <label>
                    <input type="checkbox" 
                           name="gdm_variantes_fallback_enabled" 
                           id="gdm_variantes_fallback_enabled" 
                           value="1" 
                           <?php checked($data['fallback_enabled'], '1'); ?>>
                    <strong><?php _e('🛡️ Texto por defecto (fallback)', 'product-conditional-content'); ?></strong>
                </label>
                <p class="description">
                    <?php _e('Si ninguna variante coincide, se usará este texto como respaldo', 'product-conditional-content'); ?>
                </p>
                
                <div id="gdm-fallback-wrapper" style="<?php echo $data['fallback_enabled'] !== '1' ? 'display:none;' : ''; ?> margin-top: 10px;">
                    <textarea name="gdm_variantes_fallback_text" 
                              rows="3" 
                              class="large-text" 
                              placeholder="<?php esc_attr_e('Texto por defecto...', 'product-conditional-content'); ?>"><?php echo esc_textarea($data['fallback_text']); ?></textarea>
                </div>
            </div>
            
            <hr class="gdm-separator">
            
            <!-- Ejemplos y ayuda -->
            <div class="gdm-field-group">
                <details>
                    <summary style="cursor: pointer; color: #2271b1; font-weight: 600;">
                        <span class="dashicons dashicons-editor-help"></span>
                        <?php _e('Ver ejemplos de uso', 'product-conditional-content'); ?>
                    </summary>
                    
                    <div style="margin-top: 15px; padding: 15px; background: #f0f6fc; border-left: 4px solid #2271b1; border-radius: 3px;">
                        <h4><?php _e('Ejemplo 1: Variantes por Tag', 'product-conditional-content'); ?></h4>
                        <ul>
                            <li><strong>Tipo:</strong> Tag</li>
                            <li><strong>Clave:</strong> <code>nuevo</code></li>
                            <li><strong>Acción:</strong> Reemplaza [var-cond]</li>
                            <li><strong>Texto:</strong> "🆕 ¡NUEVO PRODUCTO!"</li>
                        </ul>
                        
                        <h4 style="margin-top: 20px;"><?php _e('Ejemplo 2: Variantes por Meta', 'product-conditional-content'); ?></h4>
                        <ul>
                            <li><strong>Tipo:</strong> Meta</li>
                            <li><strong>Clave:</strong> <code>_stock_status</code></li>
                            <li><strong>Valor:</strong> <code>instock</code></li>
                            <li><strong>Texto:</strong> "✅ Disponible ahora"</li>
                        </ul>
                        
                        <h4 style="margin-top: 20px;"><?php _e('Ejemplo 3: Variante por Defecto', 'product-conditional-content'); ?></h4>
                        <ul>
                            <li><strong>Tipo:</strong> Por Defecto</li>
                            <li><strong>Texto:</strong> "Producto estándar"</li>
                        </ul>
                    </div>
                </details>
            </div>
            
        </div>
        
        <!-- Estilos integrados -->
        <style>
            .gdm-module-variantes .gdm-module-header {
                margin-bottom: 20px;
            }
            
            .gdm-module-variantes .gdm-module-header h4 {
                margin: 0 0 8px 0;
                display: flex;
                align-items: center;
                gap: 8px;
                color: #1d2327;
            }
            
            .gdm-module-variantes .gdm-variantes-table {
                margin-top: 10px;
                border: 1px solid #c3c4c7;
            }
            
            .gdm-module-variantes .gdm-variantes-table th {
                background: #f6f7f7;
                font-weight: 600;
                padding: 10px 8px;
                border: 1px solid #c3c4c7;
            }
            
            .gdm-module-variantes .gdm-variantes-table td {
                padding: 10px 8px;
                border: 1px solid #c3c4c7;
                vertical-align: middle;
            }
            
            .gdm-module-variantes .sort-handle {
                cursor: move;
                color: #8c8f94;
                font-size: 18px;
                text-align: center;
                user-select: none;
            }
            
            .gdm-module-variantes .sort-handle:hover {
                color: #1d2327;
            }
            
            .gdm-module-variantes .gdm-sortable-placeholder {
                background: #f0f0f1;
                border: 2px dashed #c3c4c7;
                visibility: visible !important;
                height: 60px;
            }
            
            .gdm-module-variantes .ui-sortable-helper {
                background: #fff;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            }
            
            .gdm-module-variantes .gdm-variantes-actions {
                margin-top: 15px;
                padding: 12px;
                background: #f6f7f7;
                border-radius: 4px;
                display: flex;
                align-items: center;
                gap: 10px;
                flex-wrap: wrap;
            }
            
            .gdm-module-variantes .gdm-counter {
                margin-left: auto;
                font-weight: 600;
                color: #1d2327;
                padding: 5px 12px;
                background: #fff;
                border: 1px solid #c3c4c7;
                border-radius: 3px;
            }
            
            .gdm-module-variantes .gdm-variantes-actions .button .dashicons {
                vertical-align: middle;
                margin-right: 4px;
            }
            
            .gdm-module-variantes .gdm-variante-row-number {
                font-weight: 600;
                color: #666;
                text-align: center;
            }
            
            .gdm-module-variantes .gdm-variante-checkbox {
                cursor: pointer;
            }
            
            .gdm-module-variantes input[type="text"]:disabled,
            .gdm-module-variantes select:disabled,
            .gdm-module-variantes textarea:disabled {
                background: #f0f0f1;
                color: #999;
            }
            
            .gdm-module-variantes details summary {
                padding: 10px;
                background: #fff;
                border: 1px solid #c3c4c7;
                border-radius: 4px;
                display: flex;
                align-items: center;
                gap: 5px;
            }
            
            .gdm-module-variantes details[open] summary {
                border-bottom-left-radius: 0;
                border-bottom-right-radius: 0;
                border-bottom: none;
            }
            
            .gdm-module-variantes details summary:hover {
                background: #f6f7f7;
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
            <td class="gdm-variante-row-number">
                <?php echo is_numeric($index) ? $index + 1 : '—'; ?>
            </td>
            <td>
                <select name="gdm_variantes_variantes[<?php echo esc_attr($index); ?>][cond_type]" 
                        class="gdm-variante-cond-type widefat">
                    <option value="tag" <?php selected($cond_type, 'tag'); ?>>
                        <?php _e('Tag', 'product-conditional-content'); ?>
                    </option>
                    <option value="meta" <?php selected($cond_type, 'meta'); ?>>
                        <?php _e('Meta', 'product-conditional-content'); ?>
                    </option>
                    <option value="attribute" <?php selected($cond_type, 'attribute'); ?>>
                        <?php _e('Atributo', 'product-conditional-content'); ?>
                    </option>
                    <option value="default" <?php selected($cond_type, 'default'); ?>>
                        <?php _e('Por Defecto', 'product-conditional-content'); ?>
                    </option>
                </select>
            </td>
            <td>
                <input type="text" 
                       class="gdm-variante-cond-key widefat" 
                       name="gdm_variantes_variantes[<?php echo esc_attr($index); ?>][cond_key]" 
                       value="<?php echo esc_attr($cond_key); ?>" 
                       placeholder="<?php esc_attr_e('slug, meta_key o pa_atributo', 'product-conditional-content'); ?>"
                       <?php echo $key_disabled; ?>>
            </td>
            <td>
                <input type="text" 
                       class="gdm-variante-cond-value widefat" 
                       name="gdm_variantes_variantes[<?php echo esc_attr($index); ?>][cond_value]" 
                       value="<?php echo esc_attr($cond_value); ?>" 
                       placeholder="<?php esc_attr_e('valor (opcional)', 'product-conditional-content'); ?>"
                       <?php echo $value_disabled; ?>>
            </td>
            <td>
                <select name="gdm_variantes_variantes[<?php echo esc_attr($index); ?>][action]" class="widefat">
                    <option value="placeholder" <?php selected($action, 'placeholder'); ?>>
                        <?php _e('Reemplaza [var-cond]', 'product-conditional-content'); ?>
                    </option>
                    <option value="append" <?php selected($action, 'append'); ?>>
                        <?php _e('Agregar al final', 'product-conditional-content'); ?>
                    </option>
                    <option value="prepend" <?php selected($action, 'prepend'); ?>>
                        <?php _e('Agregar al inicio', 'product-conditional-content'); ?>
                    </option>
                </select>
            </td>
            <td>
                <textarea name="gdm_variantes_variantes[<?php echo esc_attr($index); ?>][text]" 
                          rows="2" 
                          class="widefat"
                          placeholder="<?php esc_attr_e('Texto que se mostrará...', 'product-conditional-content'); ?>"><?php echo esc_textarea($text); ?></textarea>
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
        
        // Guardar variantes
        $variantes_sanitizadas = [];
        if (isset($_POST['gdm_variantes_variantes']) && is_array($_POST['gdm_variantes_variantes'])) {
            foreach ($_POST['gdm_variantes_variantes'] as $variante) {
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
        
        // Guardar modo de evaluación
        $modo = isset($_POST['gdm_variantes_modo']) ? sanitize_text_field($_POST['gdm_variantes_modo']) : 'primera';
        $this->save_module_field($post_id, 'modo', $modo);
        
        // Guardar fallback
        $fallback_enabled = isset($_POST['gdm_variantes_fallback_enabled']) ? '1' : '0';
        $this->save_module_field($post_id, 'fallback_enabled', $fallback_enabled);
        
        $fallback_text = isset($_POST['gdm_variantes_fallback_text']) ? wp_kses_post($_POST['gdm_variantes_fallback_text']) : '';
        $this->save_module_field($post_id, 'fallback_text', $fallback_text);
    }
    
    /**
     * Datos por defecto
     */
    protected function get_default_data() {
        return [
            'variantes' => [],
            'modo' => 'primera',
            'fallback_enabled' => '0',
            'fallback_text' => '',
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
            'gdm-module-variants',
            GDM_PLUGIN_URL . 'assets/admin/js/modules/module-variants.js',
            ['jquery', 'jquery-ui-sortable'],
            GDM_VERSION,
            true
        );
        
        wp_localize_script('gdm-module-variants', 'gdmModuloVariantes', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gdm_admin_nonce'),
            'i18n' => [
                'deleteConfirm' => __('¿Eliminar la variante seleccionada?', 'product-conditional-content'),
                'deleteMultipleConfirm' => __('¿Eliminar %d variantes seleccionadas?', 'product-conditional-content'),
                'selectOne' => __('Selecciona solo una variante para duplicar', 'product-conditional-content'),
            ]
        ]);
    }
}