<?php
/**
 * Módulo: Descripción del Producto
 * Gestión de descripciones largas y cortas SIN variantes (módulo independiente)
 * Compatible con WordPress 6.8.3, PHP 8.2, WooCommerce 10.2.2
 * 
 * @package ProductConditionalContent
 * @since 6.2.1
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
            <div class="gdm-field-group">
                <label>
                    <strong><?php _e('Ubicación de la Regla:', 'product-conditional-content'); ?></strong>
                </label>
                <select name="gdm_descripcion_ubicacion" class="regular-text">
                    <option value="reemplaza" <?php selected($data['ubicacion'], 'reemplaza'); ?>>
                        <?php _e('Reemplaza la descripción original', 'product-conditional-content'); ?>
                    </option>
                    <option value="antes" <?php selected($data['ubicacion'], 'antes'); ?>>
                        <?php _e('Añadir antes de la descripción original', 'product-conditional-content'); ?>
                    </option>
                    <option value="despues" <?php selected($data['ubicacion'], 'despues'); ?>>
                        <?php _e('Añadir después de la descripción original', 'product-conditional-content'); ?>
                    </option>
                    <option value="solo_vacia" <?php selected($data['ubicacion'], 'solo_vacia'); ?>>
                        <?php _e('Solo si la descripción está vacía', 'product-conditional-content'); ?>
                    </option>
                </select>
                <p class="gdm-field-description">
                    <?php _e('Define cómo se fusionará el contenido de la regla con la descripción del producto.', 'product-conditional-content'); ?>
                </p>
            </div>
            
            <hr class="gdm-separator">
            
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
                    <button type="button" class="button button-small gdm-insert-shortcode" data-shortcode="[var-cond]" title="Placeholder para variantes condicionales">
                        <span class="dashicons dashicons-admin-settings"></span> [var-cond]
                    </button>
                    <button type="button" class="button button-small gdm-insert-rule-id" title="Insertar regla reutilizable">
                        <span class="dashicons dashicons-admin-page"></span> [rule-id]
                    </button>
                </div>
                
                <?php
                wp_editor($data['contenido'], 'gdm_descripcion_contenido', [
                    'textarea_name' => 'gdm_descripcion_contenido',
                    'media_buttons' => true,
                    'textarea_rows' => 12,
                    'teeny' => false,
                    'tinymce' => [
                        'toolbar1' => 'bold,italic,underline,bullist,numlist,link,unlink,undo,redo',
                    ],
                    'quicktags' => true
                ]);
                ?>
                
                <p class="gdm-field-description" style="margin-top: 10px;">
                    <span class="dashicons dashicons-info"></span>
                    <?php _e('Usa el módulo "Variantes Condicionales" para gestionar el contenido del placeholder [var-cond]', 'product-conditional-content'); ?>
                </p>
            </div>
            
            <hr class="gdm-separator">
            
            <!-- Opciones Avanzadas -->
            <div class="gdm-field-group">
                <label>
                    <strong><?php _e('⚙️ Opciones Avanzadas:', 'product-conditional-content'); ?></strong>
                </label>
                
                <div style="margin-top: 10px;">
                    <label>
                        <input type="checkbox" 
                               name="gdm_descripcion_regla_final" 
                               value="1" 
                               <?php checked($data['regla_final'], '1'); ?>>
                        <strong><?php _e('Regla Final', 'product-conditional-content'); ?></strong>
                    </label>
                    <p class="gdm-field-description" style="margin-left: 24px;">
                        <?php _e('No procesar más reglas de descripción después de esta', 'product-conditional-content'); ?>
                    </p>
                </div>
                
                <div style="margin-top: 15px;">
                    <label>
                        <input type="checkbox" 
                               name="gdm_descripcion_forzar" 
                               value="1" 
                               <?php checked($data['forzar'], '1'); ?>>
                        <strong><?php _e('Forzar Aplicación', 'product-conditional-content'); ?></strong>
                    </label>
                    <p class="gdm-field-description" style="margin-left: 24px;">
                        <?php _e('Ignorar otras reglas finales y aplicar esta de todos modos', 'product-conditional-content'); ?>
                    </p>
                </div>
            </div>
            
        </div>
        
        <!-- Estilos específicos del módulo -->
        <style>
            .gdm-module-descripcion .gdm-module-header {
                margin-bottom: 15px;
            }
            
            .gdm-module-descripcion .gdm-module-header h4 {
                margin: 0 0 5px 0;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            
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
            
            .gdm-module-descripcion .gdm-field-group {
                margin-bottom: 20px;
            }
        </style>
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
        
        // Script inline simple (sin archivo externo)
        wp_add_inline_script('jquery', "
        jQuery(document).ready(function($) {
            // Insertar shortcode en editor
            $(document).on('click', '.gdm-insert-shortcode', function(e) {
                e.preventDefault();
                const shortcode = $(this).data('shortcode');
                const editorId = 'gdm_descripcion_contenido';
                
                if (typeof tinymce !== 'undefined' && tinymce.get(editorId) && !tinymce.get(editorId).isHidden()) {
                    tinymce.get(editorId).execCommand('mceInsertContent', false, shortcode);
                } else {
                    const \$textarea = $('#' + editorId);
                    const cursorPos = \$textarea.prop('selectionStart');
                    const textBefore = \$textarea.val().substring(0, cursorPos);
                    const textAfter = \$textarea.val().substring(cursorPos);
                    \$textarea.val(textBefore + shortcode + textAfter);
                    \$textarea.focus();
                }
            });
            
            // Insertar regla reutilizable
            $(document).on('click', '.gdm-insert-rule-id', function(e) {
                e.preventDefault();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'gdm_get_reusable_rules',
                        nonce: $('#gdm_nonce').val(),
                        current_post_id: $('#post_ID').val()
                    },
                    success: function(response) {
                        if (response.success && response.data.length > 0) {
                            let options = '<option value=\"\">-- Seleccionar --</option>';
                            response.data.forEach(rule => {
                                options += '<option value=\"' + rule.id + '\">' + rule.title + ' (ID: ' + rule.id + ')</option>';
                            });
                            
                            const modal = $('<div class=\"gdm-modal\"><div class=\"gdm-modal-content\"><div class=\"gdm-modal-header\"><h3>Seleccionar Regla Reutilizable</h3><button type=\"button\" class=\"gdm-modal-close\">&times;</button></div><div class=\"gdm-modal-body\"><select id=\"gdm-selected-rule\" class=\"widefat\">' + options + '</select></div><div class=\"gdm-modal-footer\"><button type=\"button\" class=\"button button-primary gdm-modal-insert\">Insertar</button><button type=\"button\" class=\"button gdm-modal-cancel\">Cancelar</button></div></div></div>');
                            
                            $('body').append(modal);
                            
                            modal.find('.gdm-modal-close, .gdm-modal-cancel').on('click', () => modal.remove());
                            modal.find('.gdm-modal-insert').on('click', function() {
                                const ruleId = $('#gdm-selected-rule').val();
                                if (ruleId) {
                                    const shortcode = '[rule-' + ruleId + ']';
                                    const editorId = 'gdm_descripcion_contenido';
                                    if (typeof tinymce !== 'undefined' && tinymce.get(editorId) && !tinymce.get(editorId).isHidden()) {
                                        tinymce.get(editorId).execCommand('mceInsertContent', false, shortcode);
                                    } else {
                                        const \$textarea = $('#' + editorId);
                                        \$textarea.val(\$textarea.val() + shortcode);
                                    }
                                    modal.remove();
                                } else {
                                    alert('Selecciona una regla');
                                }
                            });
                        } else {
                            alert('No hay reglas reutilizables disponibles');
                        }
                    }
                });
            });
        });
        ");
    }
    
    /**
     * AJAX: Obtener reglas reutilizables
     */
    public function handle_ajax_get_reusable_rules() {
        check_ajax_referer('gdm_admin_nonce', 'nonce');
        
        $current_post_id = isset($_POST['current_post_id']) ? intval($_POST['current_post_id']) : 0;
        
        $rules = get_posts([
            'post_type' => 'gdm_regla',
            'post_status' => ['habilitada', 'deshabilitada', 'publish'],
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_gdm_reutilizable',
                    'value' => '1',
                ],
            ],
            'exclude' => [$current_post_id],
        ]);
        
        $reusable_rules = [];
        foreach ($rules as $rule) {
            $reusable_rules[] = [
                'id' => $rule->ID,
                'title' => $rule->post_title,
            ];
        }
        
        wp_send_json_success($reusable_rules);
    }
}