<?php
/**
 * Módulo de Título
 * Permite modificar el título de productos agregando, reemplazando o usando regex
 * Compatible con WordPress 6.8.3, PHP 8.2, WooCommerce 10.2.2
 * 
 * @package ProductConditionalContent
 * @since 6.1.0
 * @date 2025-10-15
 */

if (!defined('ABSPATH')) exit;

class GDM_Module_Title extends GDM_Module_Base {
    
    protected $module_id = 'titulo';
    protected $module_name = 'Título';
    protected $module_icon = '📝';
    protected $priority = 'default';
    
    /**
     * Renderizar metabox
     */
    public function render_metabox($post) {
        $data = $this->get_module_data($post->ID);
        ?>
        <div class="gdm-module-titulo">
            
            <!-- Acción sobre el título -->
            <div class="gdm-field-group">
                <label>
                    <strong><?php _e('⚙️ Acción sobre el Título:', 'product-conditional-content'); ?></strong>
                </label>
                <select name="gdm_titulo_accion" id="gdm_titulo_accion" class="regular-text">
                    <option value="agregar_inicio" <?php selected($data['accion'], 'agregar_inicio'); ?>>
                        <?php _e('Agregar texto al inicio', 'product-conditional-content'); ?>
                    </option>
                    <option value="agregar_final" <?php selected($data['accion'], 'agregar_final'); ?>>
                        <?php _e('Agregar texto al final', 'product-conditional-content'); ?>
                    </option>
                    <option value="reemplazar" <?php selected($data['accion'], 'reemplazar'); ?>>
                        <?php _e('Reemplazar parte del título', 'product-conditional-content'); ?>
                    </option>
                    <option value="reemplazar_todo" <?php selected($data['accion'], 'reemplazar_todo'); ?>>
                        <?php _e('Reemplazar título completo', 'product-conditional-content'); ?>
                    </option>
                    <option value="regex" <?php selected($data['accion'], 'regex'); ?>>
                        <?php _e('Usar expresión regular (Regex)', 'product-conditional-content'); ?>
                    </option>
                </select>
            </div>
            
            <!-- Texto a agregar (inicio/final) -->
            <div class="gdm-field-group gdm-titulo-agregar" style="<?php echo !in_array($data['accion'], ['agregar_inicio', 'agregar_final']) ? 'display:none;' : ''; ?>">
                <label>
                    <strong><?php _e('📄 Texto a Agregar:', 'product-conditional-content'); ?></strong>
                </label>
                <input type="text" 
                       name="gdm_titulo_texto_agregar" 
                       value="<?php echo esc_attr($data['texto_agregar']); ?>" 
                       class="regular-text" 
                       placeholder="<?php esc_attr_e('Ej: OFERTA - ', 'product-conditional-content'); ?>">
                <p class="gdm-field-description">
                    <?php _e('Este texto se agregará al título del producto', 'product-conditional-content'); ?>
                </p>
            </div>
            
            <!-- Reemplazar parte -->
            <div class="gdm-field-group gdm-titulo-reemplazar" style="<?php echo $data['accion'] !== 'reemplazar' ? 'display:none;' : ''; ?>">
                <label>
                    <strong><?php _e('🔍 Texto a Buscar:', 'product-conditional-content'); ?></strong>
                </label>
                <input type="text" 
                       name="gdm_titulo_texto_buscar" 
                       value="<?php echo esc_attr($data['texto_buscar']); ?>" 
                       class="regular-text" 
                       placeholder="<?php esc_attr_e('Texto que será reemplazado', 'product-conditional-content'); ?>">
                
                <label style="margin-top: 15px; display: block;">
                    <strong><?php _e('✏️ Reemplazar con:', 'product-conditional-content'); ?></strong>
                </label>
                <input type="text" 
                       name="gdm_titulo_texto_reemplazar" 
                       value="<?php echo esc_attr($data['texto_reemplazar']); ?>" 
                       class="regular-text" 
                       placeholder="<?php esc_attr_e('Nuevo texto', 'product-conditional-content'); ?>">
                
                <label style="margin-top: 10px;">
                    <input type="checkbox" 
                           name="gdm_titulo_case_sensitive" 
                           value="1" 
                           <?php checked($data['case_sensitive'], '1'); ?>>
                    <?php _e('Distinguir mayúsculas/minúsculas', 'product-conditional-content'); ?>
                </label>
            </div>
            
            <!-- Reemplazar todo -->
            <div class="gdm-field-group gdm-titulo-reemplazar-todo" style="<?php echo $data['accion'] !== 'reemplazar_todo' ? 'display:none;' : ''; ?>">
                <label>
                    <strong><?php _e('📄 Nuevo Título:', 'product-conditional-content'); ?></strong>
                </label>
                <input type="text" 
                       name="gdm_titulo_nuevo_completo" 
                       value="<?php echo esc_attr($data['nuevo_completo']); ?>" 
                       class="regular-text" 
                       placeholder="<?php esc_attr_e('Título completamente nuevo', 'product-conditional-content'); ?>">
                <p class="gdm-field-description">
                    <?php _e('Este título reemplazará completamente el título original del producto', 'product-conditional-content'); ?>
                </p>
            </div>
            
            <!-- Regex -->
            <div class="gdm-field-group gdm-titulo-regex" style="<?php echo $data['accion'] !== 'regex' ? 'display:none;' : ''; ?>">
                <label>
                    <strong><?php _e('🔧 Patrón Regex:', 'product-conditional-content'); ?></strong>
                </label>
                <input type="text" 
                       name="gdm_titulo_regex_patron" 
                       value="<?php echo esc_attr($data['regex_patron']); ?>" 
                       class="regular-text" 
                       placeholder="<?php esc_attr_e('/patrón/i', 'product-conditional-content'); ?>">
                <p class="gdm-field-description">
                    <?php _e('Patrón de expresión regular compatible con PHP (preg_replace)', 'product-conditional-content'); ?>
                </p>
                
                <label style="margin-top: 15px; display: block;">
                    <strong><?php _e('✏️ Reemplazo:', 'product-conditional-content'); ?></strong>
                </label>
                <input type="text" 
                       name="gdm_titulo_regex_reemplazo" 
                       value="<?php echo esc_attr($data['regex_reemplazo']); ?>" 
                       class="regular-text" 
                       placeholder="<?php esc_attr_e('$1, $2, etc.', 'product-conditional-content'); ?>">
                <p class="gdm-field-description">
                    <?php _e('Usa $1, $2, etc. para hacer referencia a grupos capturados', 'product-conditional-content'); ?>
                </p>
                
                <div class="gdm-regex-examples" style="margin-top: 15px; padding: 10px; background: #f0f6fc; border-left: 3px solid #2271b1; border-radius: 3px;">
                    <strong><?php _e('Ejemplos:', 'product-conditional-content'); ?></strong>
                    <ul style="margin: 10px 0 0 20px; font-size: 12px;">
                        <li><code>/^(.+)$/i</code> → <code>NUEVO - $1</code> = <?php _e('Agregar prefijo', 'product-conditional-content'); ?></li>
                        <li><code>/\b(\d+)\s*ml\b/i</code> → <code>$1ml</code> = <?php _e('Eliminar espacios en medidas', 'product-conditional-content'); ?></li>
                        <li><code>/\s+/</code> → <code> </code> = <?php _e('Normalizar espacios', 'product-conditional-content'); ?></li>
                    </ul>
                </div>
            </div>
            
            <hr class="gdm-separator">
            
            <!-- Vista previa -->
            <div class="gdm-field-group">
                <label>
                    <strong><?php _e('👁️ Vista Previa:', 'product-conditional-content'); ?></strong>
                </label>
                <div id="gdm-titulo-preview" style="padding: 15px; background: #f9f9f9; border-left: 4px solid #2271b1; border-radius: 3px;">
                    <div style="color: #666; font-size: 12px; margin-bottom: 5px;">
                        <?php _e('Título original:', 'product-conditional-content'); ?>
                    </div>
                    <div style="margin-bottom: 10px;">
                        <input type="text" 
                               id="gdm-titulo-test-original" 
                               value="Camiseta de Algodón 100ml Talla M" 
                               class="regular-text" 
                               placeholder="<?php esc_attr_e('Ingresa un título de prueba', 'product-conditional-content'); ?>">
                    </div>
                    <div style="color: #666; font-size: 12px; margin-bottom: 5px;">
                        <?php _e('Título modificado:', 'product-conditional-content'); ?>
                    </div>
                    <div style="font-weight: 600; color: #2271b1; font-size: 16px;" id="gdm-titulo-test-resultado">
                        <?php _e('Ingresa configuración y título de prueba', 'product-conditional-content'); ?>
                    </div>
                </div>
                <button type="button" class="button" id="gdm-titulo-test-btn" style="margin-top: 10px;">
                    <span class="dashicons dashicons-visibility"></span>
                    <?php _e('Actualizar Vista Previa', 'product-conditional-content'); ?>
                </button>
            </div>
            
        </div>
        
        <style>
            .gdm-module-titulo .gdm-field-group {
                margin-bottom: 20px;
            }
            .gdm-regex-examples code {
                background: #fff;
                padding: 2px 6px;
                border-radius: 3px;
                font-family: monospace;
                font-size: 11px;
            }
        </style>
        <?php
    }
    
    /**
     * Guardar datos del módulo
     */
    public function save_module_data($post_id, $post) {
        if (!$this->is_module_active($post_id)) {
            return;
        }
        
        // Acción
        $accion = isset($_POST['gdm_titulo_accion']) ? sanitize_text_field($_POST['gdm_titulo_accion']) : 'agregar_final';
        update_post_meta($post_id, '_gdm_titulo_accion', $accion);
        
        // Textos
        $texto_agregar = isset($_POST['gdm_titulo_texto_agregar']) ? sanitize_text_field($_POST['gdm_titulo_texto_agregar']) : '';
        update_post_meta($post_id, '_gdm_titulo_texto_agregar', $texto_agregar);
        
        $texto_buscar = isset($_POST['gdm_titulo_texto_buscar']) ? sanitize_text_field($_POST['gdm_titulo_texto_buscar']) : '';
        update_post_meta($post_id, '_gdm_titulo_texto_buscar', $texto_buscar);
        
        $texto_reemplazar = isset($_POST['gdm_titulo_texto_reemplazar']) ? sanitize_text_field($_POST['gdm_titulo_texto_reemplazar']) : '';
        update_post_meta($post_id, '_gdm_titulo_texto_reemplazar', $texto_reemplazar);
        
        $case_sensitive = isset($_POST['gdm_titulo_case_sensitive']) ? '1' : '0';
        update_post_meta($post_id, '_gdm_titulo_case_sensitive', $case_sensitive);
        
        $nuevo_completo = isset($_POST['gdm_titulo_nuevo_completo']) ? sanitize_text_field($_POST['gdm_titulo_nuevo_completo']) : '';
        update_post_meta($post_id, '_gdm_titulo_nuevo_completo', $nuevo_completo);
        
        // Regex
        $regex_patron = isset($_POST['gdm_titulo_regex_patron']) ? sanitize_text_field($_POST['gdm_titulo_regex_patron']) : '';
        update_post_meta($post_id, '_gdm_titulo_regex_patron', $regex_patron);
        
        $regex_reemplazo = isset($_POST['gdm_titulo_regex_reemplazo']) ? sanitize_text_field($_POST['gdm_titulo_regex_reemplazo']) : '';
        update_post_meta($post_id, '_gdm_titulo_regex_reemplazo', $regex_reemplazo);
    }
    
    /**
     * Obtener datos por defecto
     */
    protected function get_default_data() {
        return [
            'accion' => 'agregar_final',
            'texto_agregar' => '',
            'texto_buscar' => '',
            'texto_reemplazar' => '',
            'case_sensitive' => '0',
            'nuevo_completo' => '',
            'regex_patron' => '',
            'regex_reemplazo' => '',
        ];
    }
    
    /**
     * Obtener datos del módulo con caché
     */
    private function get_module_data($post_id) {
        $cache_key = "title_{$post_id}";
        
        if (isset(self::$cache[$cache_key])) {
            return self::$cache[$cache_key];
        }
        
        $data = [
            'accion' => get_post_meta($post_id, '_gdm_titulo_accion', true) ?: 'agregar_final',
            'texto_agregar' => get_post_meta($post_id, '_gdm_titulo_texto_agregar', true),
            'texto_buscar' => get_post_meta($post_id, '_gdm_titulo_texto_buscar', true),
            'texto_reemplazar' => get_post_meta($post_id, '_gdm_titulo_texto_reemplazar', true),
            'case_sensitive' => get_post_meta($post_id, '_gdm_titulo_case_sensitive', true),
            'nuevo_completo' => get_post_meta($post_id, '_gdm_titulo_nuevo_completo', true),
            'regex_patron' => get_post_meta($post_id, '_gdm_titulo_regex_patron', true),
            'regex_reemplazo' => get_post_meta($post_id, '_gdm_titulo_regex_reemplazo', true),
        ];
        
        self::$cache[$cache_key] = $data;
        return $data;
    }
    
    /**
     * Encolar assets
     */
    public function enqueue_assets($hook) {
        $screen = get_current_screen();
        if ($screen->id !== 'gdm_regla') {
            return;
        }
        
        wp_add_inline_script('jquery', "
        jQuery(document).ready(function($) {
            // Cambiar tipo de acción
            $('#gdm_titulo_accion').on('change', function() {
                var accion = $(this).val();
                
                // Ocultar todos
                $('.gdm-titulo-agregar, .gdm-titulo-reemplazar, .gdm-titulo-reemplazar-todo, .gdm-titulo-regex').hide();
                
                // Mostrar según acción
                switch(accion) {
                    case 'agregar_inicio':
                    case 'agregar_final':
                        $('.gdm-titulo-agregar').show();
                        break;
                    case 'reemplazar':
                        $('.gdm-titulo-reemplazar').show();
                        break;
                    case 'reemplazar_todo':
                        $('.gdm-titulo-reemplazar-todo').show();
                        break;
                    case 'regex':
                        $('.gdm-titulo-regex').show();
                        break;
                }
            });
            
            // Vista previa
            $('#gdm-titulo-test-btn').on('click', function() {
                var original = $('#gdm-titulo-test-original').val();
                var accion = $('#gdm_titulo_accion').val();
                var resultado = original;
                
                try {
                    switch(accion) {
                        case 'agregar_inicio':
                            var texto = $('[name=\"gdm_titulo_texto_agregar\"]').val();
                            resultado = texto + original;
                            break;
                            
                        case 'agregar_final':
                            var texto = $('[name=\"gdm_titulo_texto_agregar\"]').val();
                            resultado = original + texto;
                            break;
                            
                        case 'reemplazar':
                            var buscar = $('[name=\"gdm_titulo_texto_buscar\"]').val();
                            var reemplazar = $('[name=\"gdm_titulo_texto_reemplazar\"]').val();
                            var caseSensitive = $('[name=\"gdm_titulo_case_sensitive\"]').is(':checked');
                            
                            if (caseSensitive) {
                                resultado = original.split(buscar).join(reemplazar);
                            } else {
                                var regex = new RegExp(buscar.replace(/[.*+?^${}()|[\]\\\\]/g, '\\\\$&'), 'gi');
                                resultado = original.replace(regex, reemplazar);
                            }
                            break;
                            
                        case 'reemplazar_todo':
                            resultado = $('[name=\"gdm_titulo_nuevo_completo\"]').val();
                            break;
                            
                        case 'regex':
                            var patron = $('[name=\"gdm_titulo_regex_patron\"]').val();
                            var reemplazo = $('[name=\"gdm_titulo_regex_reemplazo\"]').val();
                            
                            // Extraer patrón y flags
                            var matches = patron.match(/^\/(.*)\/([gimsuy]*)$/);
                            if (matches) {
                                var regex = new RegExp(matches[1], matches[2]);
                                resultado = original.replace(regex, reemplazo);
                            } else {
                                resultado = '❌ Patrón regex inválido';
                            }
                            break;
                    }
                    
                    $('#gdm-titulo-test-resultado').text(resultado).css('color', '#2271b1');
                } catch(e) {
                    $('#gdm-titulo-test-resultado').text('❌ Error: ' + e.message).css('color', '#dc3232');
                }
            });
            
            // Auto-actualizar vista previa
            $('#gdm-titulo-test-original, [name^=\"gdm_titulo_\"]').on('change input', function() {
                setTimeout(function() {
                    $('#gdm-titulo-test-btn').click();
                }, 300);
            });
            
            // Ejecutar preview inicial
            setTimeout(function() {
                $('#gdm-titulo-test-btn').click();
            }, 500);
        });
        ");
    }
}
