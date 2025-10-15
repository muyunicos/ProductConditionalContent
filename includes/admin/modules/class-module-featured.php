<?php
/**
 * Módulo de Destacado
 * Permite modificar el estado "destacado" de productos
 * Compatible con WordPress 6.8.3, PHP 8.2, WooCommerce 10.2.2
 * 
 * @package ProductConditionalContent
 * @since 6.1.0
 * @date 2025-10-15
 */

if (!defined('ABSPATH')) exit;

class GDM_Module_Featured extends GDM_Module_Base {
    
    protected $module_id = 'destacado';
    protected $module_name = 'Destacado';
    protected $module_icon = '⭐';
    protected $priority = 'default';
    
    /**
     * Renderizar metabox
     */
    public function render_metabox($post) {
        $data = $this->get_module_data($post->ID);
        ?>
        <div class="gdm-module-destacado">
            
            <!-- Acción sobre destacado -->
            <div class="gdm-field-group">
                <label>
                    <strong><?php _e('⚙️ Acción sobre el Estado Destacado:', 'product-conditional-content'); ?></strong>
                </label>
                <select name="gdm_destacado_accion" id="gdm_destacado_accion" class="regular-text">
                    <option value="marcar" <?php selected($data['accion'], 'marcar'); ?>>
                        <?php _e('⭐ Marcar como Destacado', 'product-conditional-content'); ?>
                    </option>
                    <option value="desmarcar" <?php selected($data['accion'], 'desmarcar'); ?>>
                        <?php _e('☆ Quitar Destacado', 'product-conditional-content'); ?>
                    </option>
                    <option value="alternar" <?php selected($data['accion'], 'alternar'); ?>>
                        <?php _e('🔄 Alternar Estado (si está destacado lo quita, si no lo marca)', 'product-conditional-content'); ?>
                    </option>
                </select>
                <p class="gdm-field-description" id="gdm-destacado-descripcion">
                    <?php $this->get_description_by_action($data['accion']); ?>
                </p>
            </div>
            
            <hr class="gdm-separator">
            
            <!-- Opciones avanzadas -->
            <div class="gdm-field-group">
                <label>
                    <strong><?php _e('🎯 Opciones Avanzadas:', 'product-conditional-content'); ?></strong>
                </label>
                
                <div style="margin-top: 10px;">
                    <label>
                        <input type="checkbox" 
                               name="gdm_destacado_exclusivo" 
                               id="gdm_destacado_exclusivo"
                               value="1" 
                               <?php checked($data['exclusivo'], '1'); ?>>
                        <strong><?php _e('Destacado Exclusivo', 'product-conditional-content'); ?></strong>
                    </label>
                    <p class="gdm-field-description">
                        <?php _e('Solo este producto estará destacado, quitando el estado destacado de otros productos que cumplan las mismas condiciones', 'product-conditional-content'); ?>
                    </p>
                </div>
                
                <div style="margin-top: 15px;" id="gdm-destacado-limite-wrapper" style="<?php echo $data['exclusivo'] !== '1' ? 'display:none;' : ''; ?>">
                    <label>
                        <strong><?php _e('📊 Límite de Productos Destacados:', 'product-conditional-content'); ?></strong>
                    </label>
                    <input type="number" 
                           name="gdm_destacado_limite" 
                           value="<?php echo esc_attr($data['limite']); ?>" 
                           min="1" 
                           max="100"
                           class="small-text">
                    <p class="gdm-field-description">
                        <?php _e('Máximo número de productos que pueden estar destacados simultáneamente (0 = sin límite)', 'product-conditional-content'); ?>
                    </p>
                </div>
            </div>
            
            <hr class="gdm-separator">
            
            <!-- Comportamiento con variaciones -->
            <div class="gdm-field-group">
                <label>
                    <strong><?php _e('🔀 Productos Variables:', 'product-conditional-content'); ?></strong>
                </label>
                <select name="gdm_destacado_variaciones" class="regular-text">
                    <option value="padre" <?php selected($data['variaciones'], 'padre'); ?>>
                        <?php _e('Solo marcar el producto padre', 'product-conditional-content'); ?>
                    </option>
                    <option value="todas" <?php selected($data['variaciones'], 'todas'); ?>>
                        <?php _e('Marcar padre y todas las variaciones', 'product-conditional-content'); ?>
                    </option>
                    <option value="solo_variaciones" <?php selected($data['variaciones'], 'solo_variaciones'); ?>>
                        <?php _e('Solo las variaciones que cumplan condiciones', 'product-conditional-content'); ?>
                    </option>
                </select>
            </div>
            
            <hr class="gdm-separator">
            
            <!-- Prioridad de destacado -->
            <div class="gdm-field-group">
                <label>
                    <strong><?php _e('🔢 Prioridad de Destacado:', 'product-conditional-content'); ?></strong>
                </label>
                <input type="number" 
                       name="gdm_destacado_prioridad" 
                       value="<?php echo esc_attr($data['prioridad']); ?>" 
                       min="1" 
                       max="100"
                       class="small-text">
                <p class="gdm-field-description">
                    <?php _e('Si hay múltiples reglas que marcan productos como destacados, se aplicará la de mayor prioridad. Número más alto = mayor prioridad.', 'product-conditional-content'); ?>
                </p>
            </div>
            
            <hr class="gdm-separator">
            
            <!-- Badge personalizado -->
            <div class="gdm-field-group">
                <label>
                    <input type="checkbox" 
                           name="gdm_destacado_badge_enabled" 
                           id="gdm_destacado_badge_enabled"
                           value="1" 
                           <?php checked($data['badge_enabled'], '1'); ?>>
                    <strong><?php _e('🏷️ Agregar Badge Personalizado', 'product-conditional-content'); ?></strong>
                </label>
                <p class="gdm-field-description">
                    <?php _e('Muestra un badge visual en el producto destacado (ej: "DESTACADO", "TOP", "POPULAR")', 'product-conditional-content'); ?>
                </p>
                
                <div id="gdm-destacado-badge-config" style="<?php echo $data['badge_enabled'] !== '1' ? 'display:none;' : ''; ?>; margin-top: 15px;">
                    <label style="display: block; margin-bottom: 5px;">
                        <strong><?php _e('Texto del Badge:', 'product-conditional-content'); ?></strong>
                    </label>
                    <input type="text" 
                           name="gdm_destacado_badge_texto" 
                           value="<?php echo esc_attr($data['badge_texto']); ?>" 
                           class="regular-text"
                           placeholder="<?php esc_attr_e('DESTACADO', 'product-conditional-content'); ?>">
                    
                    <label style="display: block; margin-top: 15px; margin-bottom: 5px;">
                        <strong><?php _e('Color del Badge:', 'product-conditional-content'); ?></strong>
                    </label>
                    <input type="color" 
                           name="gdm_destacado_badge_color" 
                           value="<?php echo esc_attr($data['badge_color']); ?>">
                    
                    <label style="display: block; margin-top: 15px; margin-bottom: 5px;">
                        <strong><?php _e('Posición del Badge:', 'product-conditional-content'); ?></strong>
                    </label>
                    <select name="gdm_destacado_badge_posicion" class="regular-text">
                        <option value="superior-izquierda" <?php selected($data['badge_posicion'], 'superior-izquierda'); ?>>
                            <?php _e('Superior Izquierda', 'product-conditional-content'); ?>
                        </option>
                        <option value="superior-derecha" <?php selected($data['badge_posicion'], 'superior-derecha'); ?>>
                            <?php _e('Superior Derecha', 'product-conditional-content'); ?>
                        </option>
                        <option value="inferior-izquierda" <?php selected($data['badge_posicion'], 'inferior-izquierda'); ?>>
                            <?php _e('Inferior Izquierda', 'product-conditional-content'); ?>
                        </option>
                        <option value="inferior-derecha" <?php selected($data['badge_posicion'], 'inferior-derecha'); ?>>
                            <?php _e('Inferior Derecha', 'product-conditional-content'); ?>
                        </option>
                    </select>
                </div>
            </div>
            
            <hr class="gdm-separator">
            
            <!-- Vista previa del badge -->
            <div class="gdm-field-group">
                <label>
                    <strong><?php _e('👁️ Vista Previa del Badge:', 'product-conditional-content'); ?></strong>
                </label>
                <div style="position: relative; width: 200px; height: 150px; border: 2px dashed #ddd; border-radius: 4px; background: #f9f9f9;">
                    <div id="gdm-badge-preview" style="
                        position: absolute;
                        top: 10px;
                        left: 10px;
                        background: <?php echo esc_attr($data['badge_color']); ?>;
                        color: #fff;
                        padding: 5px 10px;
                        border-radius: 3px;
                        font-weight: bold;
                        font-size: 12px;
                        display: <?php echo $data['badge_enabled'] === '1' ? 'block' : 'none'; ?>;
                    ">
                        <?php echo esc_html($data['badge_texto'] ?: 'DESTACADO'); ?>
                    </div>
                    <div style="position: absolute; bottom: 10px; right: 10px; color: #999; font-size: 11px;">
                        Imagen del producto
                    </div>
                </div>
            </div>
            
        </div>
        
        <style>
            .gdm-module-destacado .gdm-field-group {
                margin-bottom: 20px;
            }
        </style>
        <?php
    }
    
    /**
     * Obtener descripción según acción
     */
    private function get_description_by_action($accion) {
        $descriptions = [
            'marcar' => __('Los productos que cumplan las condiciones serán marcados como destacados', 'product-conditional-content'),
            'desmarcar' => __('Los productos que cumplan las condiciones perderán el estado destacado', 'product-conditional-content'),
            'alternar' => __('Si el producto está destacado se quitará, si no está destacado se marcará', 'product-conditional-content'),
        ];
        
        echo esc_html($descriptions[$accion] ?? '');
    }
    
    /**
     * Guardar datos del módulo
     */
    public function save_module_data($post_id, $post) {
        if (!$this->is_module_active($post_id)) {
            return;
        }
        
        // Acción
        $accion = isset($_POST['gdm_destacado_accion']) ? sanitize_text_field($_POST['gdm_destacado_accion']) : 'marcar';
        update_post_meta($post_id, '_gdm_destacado_accion', $accion);
        
        // Exclusivo
        $exclusivo = isset($_POST['gdm_destacado_exclusivo']) ? '1' : '0';
        update_post_meta($post_id, '_gdm_destacado_exclusivo', $exclusivo);
        
        // Límite
        $limite = isset($_POST['gdm_destacado_limite']) ? absint($_POST['gdm_destacado_limite']) : 0;
        update_post_meta($post_id, '_gdm_destacado_limite', $limite);
        
        // Variaciones
        $variaciones = isset($_POST['gdm_destacado_variaciones']) ? sanitize_text_field($_POST['gdm_destacado_variaciones']) : 'padre';
        update_post_meta($post_id, '_gdm_destacado_variaciones', $variaciones);
        
        // Prioridad
        $prioridad = isset($_POST['gdm_destacado_prioridad']) ? absint($_POST['gdm_destacado_prioridad']) : 10;
        update_post_meta($post_id, '_gdm_destacado_prioridad', $prioridad);
        
        // Badge
        $badge_enabled = isset($_POST['gdm_destacado_badge_enabled']) ? '1' : '0';
        update_post_meta($post_id, '_gdm_destacado_badge_enabled', $badge_enabled);
        
        $badge_texto = isset($_POST['gdm_destacado_badge_texto']) ? sanitize_text_field($_POST['gdm_destacado_badge_texto']) : 'DESTACADO';
        update_post_meta($post_id, '_gdm_destacado_badge_texto', $badge_texto);
        
        $badge_color = isset($_POST['gdm_destacado_badge_color']) ? sanitize_hex_color($_POST['gdm_destacado_badge_color']) : '#ff6b6b';
        update_post_meta($post_id, '_gdm_destacado_badge_color', $badge_color);
        
        $badge_posicion = isset($_POST['gdm_destacado_badge_posicion']) ? sanitize_text_field($_POST['gdm_destacado_badge_posicion']) : 'superior-izquierda';
        update_post_meta($post_id, '_gdm_destacado_badge_posicion', $badge_posicion);
    }
    
    /**
     * Obtener datos por defecto
     */
    protected function get_default_data() {
        return [
            'accion' => 'marcar',
            'exclusivo' => '0',
            'limite' => 0,
            'variaciones' => 'padre',
            'prioridad' => 10,
            'badge_enabled' => '0',
            'badge_texto' => 'DESTACADO',
            'badge_color' => '#ff6b6b',
            'badge_posicion' => 'superior-izquierda',
        ];
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
            // Actualizar descripción según acción
            $('#gdm_destacado_accion').on('change', function() {
                var accion = $(this).val();
                var descripciones = {
                    'marcar': 'Los productos que cumplan las condiciones serán marcados como destacados',
                    'desmarcar': 'Los productos que cumplan las condiciones perderán el estado destacado',
                    'alternar': 'Si el producto está destacado se quitará, si no está destacado se marcará'
                };
                $('#gdm-destacado-descripcion').text(descripciones[accion] || '');
            });
            
            // Toggle exclusivo
            $('#gdm_destacado_exclusivo').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#gdm-destacado-limite-wrapper').slideDown();
                } else {
                    $('#gdm-destacado-limite-wrapper').slideUp();
                }
            });
            
            // Toggle badge
            $('#gdm_destacado_badge_enabled').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#gdm-destacado-badge-config').slideDown();
                    $('#gdm-badge-preview').show();
                } else {
                    $('#gdm-destacado-badge-config').slideUp();
                    $('#gdm-badge-preview').hide();
                }
            });
            
            // Actualizar preview del badge
            function updateBadgePreview() {
                var texto = $('[name=\"gdm_destacado_badge_texto\"]').val() || 'DESTACADO';
                var color = $('[name=\"gdm_destacado_badge_color\"]').val();
                var posicion = $('[name=\"gdm_destacado_badge_posicion\"]').val();
                
                var positions = {
                    'superior-izquierda': { top: '10px', left: '10px', right: 'auto', bottom: 'auto' },
                    'superior-derecha': { top: '10px', right: '10px', left: 'auto', bottom: 'auto' },
                    'inferior-izquierda': { bottom: '10px', left: '10px', right: 'auto', top: 'auto' },
                    'inferior-derecha': { bottom: '10px', right: '10px', left: 'auto', top: 'auto' }
                };
                
                $('#gdm-badge-preview')
                    .text(texto)
                    .css('background', color)
                    .css(positions[posicion]);
            }
            
            $('[name=\"gdm_destacado_badge_texto\"], [name=\"gdm_destacado_badge_color\"], [name=\"gdm_destacado_badge_posicion\"]')
                .on('change input', updateBadgePreview);
        });
        ");
    }
}
