<?php
/**
 * Módulo de Galería
 * Permite agregar imágenes a la galería de productos y aplicar marca de agua
 * Compatible con WordPress 6.8.3, PHP 8.2, WooCommerce 10.2.2
 * 
 * @package ProductConditionalContent
 * @since 6.1.0
 * @date 2025-10-15
 */

if (!defined('ABSPATH')) exit;

class GDM_Module_Gallery extends GDM_Module_Base {
    
    protected $module_id = 'galeria';
    protected $module_name = 'Galería';
    protected $module_icon = '🖼️';
    protected $priority = 'default';
    
    /**
     * Renderizar metabox
     */
    public function render_metabox($post) {
        $data = $this->get_module_data($post->ID);
        ?>
        <div class="gdm-module-galeria">
            
            <!-- Agregar Imágenes -->
            <div class="gdm-field-group">
                <label>
                    <strong><?php _e('📷 Imágenes a Agregar:', 'product-conditional-content'); ?></strong>
                </label>
                <p class="gdm-field-description">
                    <?php _e('Selecciona las imágenes que se agregarán a la galería del producto', 'product-conditional-content'); ?>
                </p>
                
                <div class="gdm-gallery-images">
                    <div id="gdm-gallery-preview" class="gdm-gallery-preview">
                        <?php
                        if (!empty($data['imagenes'])) {
                            foreach ($data['imagenes'] as $index => $image_id) {
                                $this->render_gallery_image($index, $image_id);
                            }
                        }
                        ?>
                    </div>
                    
                    <button type="button" class="button" id="gdm-add-gallery-image">
                        <span class="dashicons dashicons-plus-alt"></span>
                        <?php _e('Agregar Imagen', 'product-conditional-content'); ?>
                    </button>
                </div>
                
                <input type="hidden" 
                       name="gdm_galeria_imagenes" 
                       id="gdm_galeria_imagenes" 
                       value="<?php echo esc_attr(json_encode($data['imagenes'])); ?>">
            </div>
            
            <!-- Posición de las imágenes -->
            <div class="gdm-field-group">
                <label>
                    <strong><?php _e('📍 Posición en la Galería:', 'product-conditional-content'); ?></strong>
                </label>
                <select name="gdm_galeria_posicion" class="regular-text">
                    <option value="inicio" <?php selected($data['posicion'], 'inicio'); ?>>
                        <?php _e('Al principio de la galería', 'product-conditional-content'); ?>
                    </option>
                    <option value="final" <?php selected($data['posicion'], 'final'); ?>>
                        <?php _e('Al final de la galería', 'product-conditional-content'); ?>
                    </option>
                    <option value="posicion" <?php selected($data['posicion'], 'posicion'); ?>>
                        <?php _e('En posición específica', 'product-conditional-content'); ?>
                    </option>
                </select>
            </div>
            
            <!-- Posición específica -->
            <div class="gdm-field-group" id="gdm-posicion-especifica" style="<?php echo $data['posicion'] !== 'posicion' ? 'display:none;' : ''; ?>">
                <label>
                    <strong><?php _e('Número de Posición:', 'product-conditional-content'); ?></strong>
                </label>
                <input type="number" 
                       name="gdm_galeria_posicion_numero" 
                       value="<?php echo esc_attr($data['posicion_numero']); ?>" 
                       min="1" 
                       class="small-text">
                <p class="gdm-field-description">
                    <?php _e('1 = primera posición, 2 = segunda posición, etc.', 'product-conditional-content'); ?>
                </p>
            </div>
            
            <hr class="gdm-separator">
            
            <!-- Marca de Agua -->
            <div class="gdm-field-group">
                <label>
                    <input type="checkbox" 
                           name="gdm_galeria_marca_agua_enabled" 
                           id="gdm_galeria_marca_agua_enabled" 
                           value="1" 
                           <?php checked($data['marca_agua_enabled'], '1'); ?>>
                    <strong><?php _e('💧 Aplicar Marca de Agua', 'product-conditional-content'); ?></strong>
                </label>
                <p class="gdm-field-description">
                    <?php _e('Aplica una imagen PNG superpuesta sobre la imagen de portada del producto', 'product-conditional-content'); ?>
                </p>
            </div>
            
            <div id="gdm-marca-agua-config" style="<?php echo $data['marca_agua_enabled'] !== '1' ? 'display:none;' : ''; ?>">
                
                <!-- Imagen de marca de agua -->
                <div class="gdm-field-group">
                    <label>
                        <strong><?php _e('Imagen de Marca de Agua (PNG):', 'product-conditional-content'); ?></strong>
                    </label>
                    
                    <div class="gdm-watermark-selector">
                        <div id="gdm-watermark-preview">
                            <?php
                            if ($data['marca_agua_imagen']) {
                                echo wp_get_attachment_image($data['marca_agua_imagen'], 'thumbnail');
                            }
                            ?>
                        </div>
                        <button type="button" class="button" id="gdm-select-watermark">
                            <?php _e('Seleccionar Imagen PNG', 'product-conditional-content'); ?>
                        </button>
                        <button type="button" class="button" id="gdm-remove-watermark" style="<?php echo !$data['marca_agua_imagen'] ? 'display:none;' : ''; ?>">
                            <?php _e('Eliminar', 'product-conditional-content'); ?>
                        </button>
                        <input type="hidden" 
                               name="gdm_galeria_marca_agua_imagen" 
                               id="gdm_galeria_marca_agua_imagen" 
                               value="<?php echo esc_attr($data['marca_agua_imagen']); ?>">
                    </div>
                </div>
                
                <!-- Posición de la marca de agua -->
                <div class="gdm-field-group">
                    <label>
                        <strong><?php _e('Posición de la Marca:', 'product-conditional-content'); ?></strong>
                    </label>
                    <select name="gdm_galeria_marca_agua_posicion" class="regular-text">
                        <option value="centro" <?php selected($data['marca_agua_posicion'], 'centro'); ?>>
                            <?php _e('Centro', 'product-conditional-content'); ?>
                        </option>
                        <option value="superior-izquierda" <?php selected($data['marca_agua_posicion'], 'superior-izquierda'); ?>>
                            <?php _e('Superior Izquierda', 'product-conditional-content'); ?>
                        </option>
                        <option value="superior-derecha" <?php selected($data['marca_agua_posicion'], 'superior-derecha'); ?>>
                            <?php _e('Superior Derecha', 'product-conditional-content'); ?>
                        </option>
                        <option value="inferior-izquierda" <?php selected($data['marca_agua_posicion'], 'inferior-izquierda'); ?>>
                            <?php _e('Inferior Izquierda', 'product-conditional-content'); ?>
                        </option>
                        <option value="inferior-derecha" <?php selected($data['marca_agua_posicion'], 'inferior-derecha'); ?>>
                            <?php _e('Inferior Derecha', 'product-conditional-content'); ?>
                        </option>
                    </select>
                </div>
                
                <!-- Opacidad -->
                <div class="gdm-field-group">
                    <label>
                        <strong><?php _e('Opacidad (%):', 'product-conditional-content'); ?></strong>
                    </label>
                    <input type="range" 
                           name="gdm_galeria_marca_agua_opacidad" 
                           id="gdm_galeria_marca_agua_opacidad" 
                           min="0" 
                           max="100" 
                           value="<?php echo esc_attr($data['marca_agua_opacidad']); ?>" 
                           class="regular-text">
                    <span id="gdm-opacidad-valor"><?php echo esc_html($data['marca_agua_opacidad']); ?>%</span>
                </div>
                
                <!-- Escala -->
                <div class="gdm-field-group">
                    <label>
                        <strong><?php _e('Escala (%):', 'product-conditional-content'); ?></strong>
                    </label>
                    <input type="range" 
                           name="gdm_galeria_marca_agua_escala" 
                           id="gdm_galeria_marca_agua_escala" 
                           min="10" 
                           max="200" 
                           value="<?php echo esc_attr($data['marca_agua_escala']); ?>" 
                           class="regular-text">
                    <span id="gdm-escala-valor"><?php echo esc_html($data['marca_agua_escala']); ?>%</span>
                </div>
                
            </div>
            
        </div>
        
        <style>
            .gdm-gallery-preview {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
                margin-bottom: 15px;
                padding: 15px;
                border: 1px dashed #ddd;
                border-radius: 4px;
                min-height: 100px;
            }
            .gdm-gallery-image {
                position: relative;
                width: 100px;
                height: 100px;
                border: 1px solid #ddd;
                border-radius: 4px;
                overflow: hidden;
            }
            .gdm-gallery-image img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }
            .gdm-gallery-image .remove-image {
                position: absolute;
                top: 5px;
                right: 5px;
                background: #dc3232;
                color: #fff;
                border: none;
                border-radius: 50%;
                width: 24px;
                height: 24px;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .gdm-watermark-selector {
                display: flex;
                gap: 10px;
                align-items: center;
            }
            #gdm-watermark-preview {
                width: 150px;
                height: 150px;
                border: 1px dashed #ddd;
                border-radius: 4px;
                display: flex;
                align-items: center;
                justify-content: center;
                background: repeating-conic-gradient(#f0f0f0 0% 25%, #fff 0% 50%) 50% / 20px 20px;
            }
            #gdm-watermark-preview img {
                max-width: 100%;
                max-height: 100%;
            }
        </style>
        <?php
    }
    
    /**
     * Renderizar imagen de galería
     */
    private function render_gallery_image($index, $image_id) {
        $image = wp_get_attachment_image($image_id, 'thumbnail');
        ?>
        <div class="gdm-gallery-image" data-id="<?php echo esc_attr($image_id); ?>">
            <?php echo $image; ?>
            <button type="button" class="remove-image" data-index="<?php echo esc_attr($index); ?>">×</button>
        </div>
        <?php
    }
    
    /**
     * Guardar datos del módulo
     */
    public function save_module_data($post_id, $post) {
        if (!$this->is_module_active($post_id)) {
            return;
        }
        
        // Imágenes
        $imagenes = isset($_POST['gdm_galeria_imagenes']) ? json_decode(stripslashes($_POST['gdm_galeria_imagenes']), true) : [];
        update_post_meta($post_id, '_gdm_galeria_imagenes', $imagenes);
        
        // Posición
        $posicion = isset($_POST['gdm_galeria_posicion']) ? sanitize_text_field($_POST['gdm_galeria_posicion']) : 'final';
        update_post_meta($post_id, '_gdm_galeria_posicion', $posicion);
        
        $posicion_numero = isset($_POST['gdm_galeria_posicion_numero']) ? absint($_POST['gdm_galeria_posicion_numero']) : 1;
        update_post_meta($post_id, '_gdm_galeria_posicion_numero', $posicion_numero);
        
        // Marca de agua
        $marca_agua_enabled = isset($_POST['gdm_galeria_marca_agua_enabled']) ? '1' : '0';
        update_post_meta($post_id, '_gdm_galeria_marca_agua_enabled', $marca_agua_enabled);
        
        $marca_agua_imagen = isset($_POST['gdm_galeria_marca_agua_imagen']) ? absint($_POST['gdm_galeria_marca_agua_imagen']) : 0;
        update_post_meta($post_id, '_gdm_galeria_marca_agua_imagen', $marca_agua_imagen);
        
        $marca_agua_posicion = isset($_POST['gdm_galeria_marca_agua_posicion']) ? sanitize_text_field($_POST['gdm_galeria_marca_agua_posicion']) : 'centro';
        update_post_meta($post_id, '_gdm_galeria_marca_agua_posicion', $marca_agua_posicion);
        
        $marca_agua_opacidad = isset($_POST['gdm_galeria_marca_agua_opacidad']) ? absint($_POST['gdm_galeria_marca_agua_opacidad']) : 50;
        update_post_meta($post_id, '_gdm_galeria_marca_agua_opacidad', $marca_agua_opacidad);
        
        $marca_agua_escala = isset($_POST['gdm_galeria_marca_agua_escala']) ? absint($_POST['gdm_galeria_marca_agua_escala']) : 100;
        update_post_meta($post_id, '_gdm_galeria_marca_agua_escala', $marca_agua_escala);
    }
    
    /**
     * Obtener datos por defecto
     */
    protected function get_default_data() {
        return [
            'imagenes' => [],
            'posicion' => 'final',
            'posicion_numero' => 1,
            'marca_agua_enabled' => '0',
            'marca_agua_imagen' => 0,
            'marca_agua_posicion' => 'centro',
            'marca_agua_opacidad' => 50,
            'marca_agua_escala' => 100,
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
        
        wp_enqueue_media();
        
        wp_add_inline_script('jquery', "
        jQuery(document).ready(function($) {
            // Seleccionar posición específica
            $('[name=\"gdm_galeria_posicion\"]').on('change', function() {
                if ($(this).val() === 'posicion') {
                    $('#gdm-posicion-especifica').slideDown();
                } else {
                    $('#gdm-posicion-especifica').slideUp();
                }
            });
            
            // Toggle marca de agua
            $('#gdm_galeria_marca_agua_enabled').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#gdm-marca-agua-config').slideDown();
                } else {
                    $('#gdm-marca-agua-config').slideUp();
                }
            });
            
            // Agregar imagen a galería
            $('#gdm-add-gallery-image').on('click', function(e) {
                e.preventDefault();
                
                var mediaUploader = wp.media({
                    title: 'Seleccionar Imagen',
                    button: { text: 'Agregar a Galería' },
                    multiple: true
                });
                
                mediaUploader.on('select', function() {
                    var selection = mediaUploader.state().get('selection');
                    var imagenesActuales = JSON.parse($('#gdm_galeria_imagenes').val() || '[]');
                    
                    selection.each(function(attachment) {
                        attachment = attachment.toJSON();
                        imagenesActuales.push(attachment.id);
                        
                        var img = $('<div class=\"gdm-gallery-image\" data-id=\"' + attachment.id + '\">');
                        img.append('<img src=\"' + attachment.sizes.thumbnail.url + '\">');
                        img.append('<button type=\"button\" class=\"remove-image\">×</button>');
                        $('#gdm-gallery-preview').append(img);
                    });
                    
                    $('#gdm_galeria_imagenes').val(JSON.stringify(imagenesActuales));
                });
                
                mediaUploader.open();
            });
            
            // Eliminar imagen de galería
            $(document).on('click', '.gdm-gallery-image .remove-image', function() {
                var imageId = $(this).closest('.gdm-gallery-image').data('id');
                var imagenesActuales = JSON.parse($('#gdm_galeria_imagenes').val() || '[]');
                imagenesActuales = imagenesActuales.filter(id => id != imageId);
                $('#gdm_galeria_imagenes').val(JSON.stringify(imagenesActuales));
                $(this).closest('.gdm-gallery-image').remove();
            });
            
            // Seleccionar marca de agua
            $('#gdm-select-watermark').on('click', function(e) {
                e.preventDefault();
                
                var mediaUploader = wp.media({
                    title: 'Seleccionar Marca de Agua (PNG)',
                    button: { text: 'Usar esta imagen' },
                    multiple: false,
                    library: { type: 'image/png' }
                });
                
                mediaUploader.on('select', function() {
                    var attachment = mediaUploader.state().get('selection').first().toJSON();
                    $('#gdm_galeria_marca_agua_imagen').val(attachment.id);
                    $('#gdm-watermark-preview').html('<img src=\"' + attachment.sizes.thumbnail.url + '\">');
                    $('#gdm-remove-watermark').show();
                });
                
                mediaUploader.open();
            });
            
            // Eliminar marca de agua
            $('#gdm-remove-watermark').on('click', function() {
                $('#gdm_galeria_marca_agua_imagen').val('');
                $('#gdm-watermark-preview').html('');
                $(this).hide();
            });
            
            // Actualizar valores de sliders
            $('#gdm_galeria_marca_agua_opacidad').on('input', function() {
                $('#gdm-opacidad-valor').text($(this).val() + '%');
            });
            
            $('#gdm_galeria_marca_agua_escala').on('input', function() {
                $('#gdm-escala-valor').text($(this).val() + '%');
            });
        });
        ");
    }
}
