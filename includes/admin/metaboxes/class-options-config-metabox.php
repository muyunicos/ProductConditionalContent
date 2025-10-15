<?php
/**
 * Metabox para Opciones de Producto (Agregar Nueva Opción)
 * Compatible con WordPress 6.8.3, PHP 8.2, WooCommerce 10.2.2
 */
if (!defined('ABSPATH')) exit;

final class GDM_Opciones_Metabox {
    
    public static function init() {
        add_action('add_meta_boxes', [__CLASS__, 'add_metabox']);
        add_action('save_post_gdm_opcion', [__CLASS__, 'save_metabox'], 10, 2);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    public static function enqueue_assets($hook) {
        $screen = get_current_screen();
        if ($screen->id !== 'gdm_opcion') {
            return;
        }

        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_media(); // Para selector de archivos
        
        wp_enqueue_script(
            'gdm-opciones-admin',
            GDM_PLUGIN_URL . 'assets/admin/js/metaboxes/options-config-metabox.js',
            ['jquery', 'jquery-ui-sortable'],
            GDM_VERSION,
            true
        );
        
        wp_enqueue_style(
            'gdm-opciones-admin',
            GDM_PLUGIN_URL . 'assets/admin/css/options-config-metabox.css',
            [],
            GDM_VERSION
        );
        
        wp_localize_script('gdm-opciones-admin', 'gdmOpcionesAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gdm_opciones_nonce'),
            'currentPostId' => get_the_ID(),
            'i18n' => [
                'addChoice' => __('Agregar Opción', 'product-conditional-content'),
                'removeChoice' => __('Eliminar', 'product-conditional-content'),
                'selectFile' => __('Seleccionar Archivo', 'product-conditional-content'),
            ]
        ]);
    }

    public static function add_metabox() {
        add_meta_box(
            'gdm_opcion_config',
            __('Configuración de la Opción', 'product-conditional-content'),
            [__CLASS__, 'render_metabox'],
            'gdm_opcion',
            'normal',
            'high'
        );
    }

    public static function render_metabox($post) {
        wp_nonce_field('gdm_save_opcion_data', 'gdm_opcion_nonce');
        
        $data = self::get_opcion_data($post->ID);
        ?>
        <div class="gdm-opcion-container">
            <!-- SECCIÓN: Información Básica -->
            <div class="gdm-section">
                <h3><?php _e('Información Básica', 'product-conditional-content'); ?></h3>
                
                <table class="form-table">
                    <tr>
                        <th><label for="gdm_opcion_slug"><?php _e('Slug (Identificador)', 'product-conditional-content'); ?> <span class="required">*</span></label></th>
                        <td>
                            <input type="text" 
                                   id="gdm_opcion_slug" 
                                   name="gdm_opcion_slug" 
                                   value="<?php echo esc_attr($data['slug']); ?>" 
                                   class="regular-text" 
                                   pattern="[a-z0-9-]+"
                                   required />
                            <p class="description">
                                <?php _e('Identificador único (solo letras minúsculas, números y guiones). Ejemplo: "telefono-contacto"', 'product-conditional-content'); ?><br>
                                <?php _e('Usa este slug en reglas con:', 'product-conditional-content'); ?> 
                                <code>[opcion <?php echo esc_html($data['slug'] ?: 'mi-opcion'); ?>]</code>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="gdm_opcion_label"><?php _e('Etiqueta Visible', 'product-conditional-content'); ?> <span class="required">*</span></label></th>
                        <td>
                            <input type="text" 
                                   id="gdm_opcion_label" 
                                   name="gdm_opcion_label" 
                                   value="<?php echo esc_attr($data['label']); ?>" 
                                   class="large-text" 
                                   required />
                            <p class="description"><?php _e('Nombre que verá el cliente en la tienda', 'product-conditional-content'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="gdm_opcion_descripcion"><?php _e('Descripción', 'product-conditional-content'); ?></label></th>
                        <td>
                            <textarea id="gdm_opcion_descripcion" 
                                      name="gdm_opcion_descripcion" 
                                      rows="3" 
                                      class="large-text"><?php echo esc_textarea($data['descripcion']); ?></textarea>
                            <p class="description"><?php _e('Texto de ayuda que aparecerá debajo de la opción', 'product-conditional-content'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- SECCIÓN: Tipo de Opción -->
            <div class="gdm-section">
                <h3><?php _e('Tipo de Opción', 'product-conditional-content'); ?></h3>
                
                <table class="form-table">
                    <tr>
                        <th><?php _e('Tipo de Campo', 'product-conditional-content'); ?> <span class="required">*</span></th>
                        <td>
                            <select id="gdm_opcion_tipo" name="gdm_opcion_tipo" class="gdm-opcion-tipo-select">
                                <optgroup label="<?php esc_attr_e('Texto', 'product-conditional-content'); ?>">
                                    <option value="text" <?php selected($data['tipo'], 'text'); ?>><?php _e('Texto (una línea)', 'product-conditional-content'); ?></option>
                                    <option value="textarea" <?php selected($data['tipo'], 'textarea'); ?>><?php _e('Texto largo (múltiples líneas)', 'product-conditional-content'); ?></option>
                                    <option value="email" <?php selected($data['tipo'], 'email'); ?>><?php _e('Email', 'product-conditional-content'); ?></option>
                                    <option value="tel" <?php selected($data['tipo'], 'tel'); ?>><?php _e('Teléfono', 'product-conditional-content'); ?></option>
                                    <option value="number" <?php selected($data['tipo'], 'number'); ?>><?php _e('Número', 'product-conditional-content'); ?></option>
                                    <option value="url" <?php selected($data['tipo'], 'url'); ?>><?php _e('URL', 'product-conditional-content'); ?></option>
                                    <option value="date" <?php selected($data['tipo'], 'date'); ?>><?php _e('Fecha', 'product-conditional-content'); ?></option>
                                </optgroup>
                                <optgroup label="<?php esc_attr_e('Archivos', 'product-conditional-content'); ?>">
                                    <option value="file" <?php selected($data['tipo'], 'file'); ?>><?php _e('Archivo (subir uno)', 'product-conditional-content'); ?></option>
                                    <option value="file_multi" <?php selected($data['tipo'], 'file_multi'); ?>><?php _e('Archivos (subir varios)', 'product-conditional-content'); ?></option>
                                </optgroup>
                                <optgroup label="<?php esc_attr_e('Selección', 'product-conditional-content'); ?>">
                                    <option value="select" <?php selected($data['tipo'], 'select'); ?>><?php _e('Lista desplegable (select)', 'product-conditional-content'); ?></option>
                                    <option value="radio" <?php selected($data['tipo'], 'radio'); ?>><?php _e('Botones de opción (radio)', 'product-conditional-content'); ?></option>
                                    <option value="checkbox" <?php selected($data['tipo'], 'checkbox'); ?>><?php _e('Casillas de verificación (checkbox)', 'product-conditional-content'); ?></option>
                                </optgroup>
                            </select>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- SECCIÓN: Configuración Específica del Tipo -->
            <div class="gdm-section gdm-tipo-config" id="gdm-config-text" style="display:none;">
                <h3><?php _e('Configuración de Texto', 'product-conditional-content'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th><label for="gdm_texto_placeholder"><?php _e('Placeholder', 'product-conditional-content'); ?></label></th>
                        <td>
                            <input type="text" 
                                   id="gdm_texto_placeholder" 
                                   name="gdm_texto_placeholder" 
                                   value="<?php echo esc_attr($data['texto_placeholder'] ?? ''); ?>" 
                                   class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th><label for="gdm_texto_maxlength"><?php _e('Longitud Máxima', 'product-conditional-content'); ?></label></th>
                        <td>
                            <input type="number" 
                                   id="gdm_texto_maxlength" 
                                   name="gdm_texto_maxlength" 
                                   value="<?php echo esc_attr($data['texto_maxlength'] ?? ''); ?>" 
                                   class="small-text" 
                                   min="1" />
                        </td>
                    </tr>
                </table>
            </div>

            <div class="gdm-section gdm-tipo-config" id="gdm-config-file" style="display:none;">
                <h3><?php _e('Configuración de Archivos', 'product-conditional-content'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th><label for="gdm_file_tipos"><?php _e('Tipos de Archivo Permitidos', 'product-conditional-content'); ?></label></th>
                        <td>
                            <input type="text" 
                                   id="gdm_file_tipos" 
                                   name="gdm_file_tipos" 
                                   value="<?php echo esc_attr($data['file_tipos'] ?? 'jpg,jpeg,png,pdf'); ?>" 
                                   class="regular-text" />
                            <p class="description"><?php _e('Extensiones separadas por comas. Ej: jpg,png,pdf', 'product-conditional-content'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="gdm_file_max_size"><?php _e('Tamaño Máximo (MB)', 'product-conditional-content'); ?></label></th>
                        <td>
                            <input type="number" 
                                   id="gdm_file_max_size" 
                                   name="gdm_file_max_size" 
                                   value="<?php echo esc_attr($data['file_max_size'] ?? '5'); ?>" 
                                   class="small-text" 
                                   min="1" 
                                   max="100" />
                        </td>
                    </tr>
                </table>
            </div>

            <div class="gdm-section gdm-tipo-config" id="gdm-config-choices" style="display:none;">
                <h3><?php _e('Opciones de Selección', 'product-conditional-content'); ?></h3>
                <p class="description"><?php _e('Define las opciones que el cliente podrá elegir', 'product-conditional-content'); ?></p>
                
                <table class="gdm-choices-table widefat">
                    <thead>
                        <tr>
                            <th class="handle-col"></th>
                            <th><?php _e('Valor', 'product-conditional-content'); ?></th>
                            <th><?php _e('Etiqueta', 'product-conditional-content'); ?></th>
                            <th><?php _e('Modificador de Precio', 'product-conditional-content'); ?></th>
                            <th class="actions-col"><?php _e('Acciones', 'product-conditional-content'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="gdm-choices-tbody">
                        <?php
                        if (!empty($data['choices']) && is_array($data['choices'])) {
                            foreach ($data['choices'] as $i => $choice) {
                                self::render_choice_row($i, $choice);
                            }
                        }
                        ?>
                    </tbody>
                </table>
                
                <button type="button" class="button" id="gdm-add-choice">
                    <span class="dashicons dashicons-plus-alt"></span> <?php _e('Agregar Opción', 'product-conditional-content'); ?>
                </button>
                
                <script type="text/html" id="gdm-choice-template">
                    <?php self::render_choice_row('__INDEX__', []); ?>
                </script>
            </div>

            <!-- SECCIÓN: Opciones Avanzadas -->
            <div class="gdm-section">
                <h3><?php _e('Opciones Avanzadas', 'product-conditional-content'); ?></h3>
                
                <table class="form-table">
                    <tr>
                        <th><?php _e('Campo Requerido', 'product-conditional-content'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="gdm_opcion_required" 
                                       value="1" 
                                       <?php checked($data['required'], '1'); ?> />
                                <?php _e('El cliente debe completar este campo obligatoriamente', 'product-conditional-content'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="gdm_opcion_precio_base"><?php _e('Modificador de Precio Base', 'product-conditional-content'); ?></label></th>
                        <td>
                            <input type="text" 
                                   id="gdm_opcion_precio_base" 
                                   name="gdm_opcion_precio_base" 
                                   value="<?php echo esc_attr($data['precio_base']); ?>" 
                                   class="small-text" 
                                   placeholder="0" />
                            <span class="currency-symbol"><?php echo get_woocommerce_currency_symbol(); ?></span>
                            <p class="description">
                                <?php _e('Añade este valor al precio del producto (puede ser positivo o negativo)', 'product-conditional-content'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="gdm_opcion_css_class"><?php _e('Clase CSS Personalizada', 'product-conditional-content'); ?></label></th>
                        <td>
                            <input type="text" 
                                   id="gdm_opcion_css_class" 
                                   name="gdm_opcion_css_class" 
                                   value="<?php echo esc_attr($data['css_class']); ?>" 
                                   class="regular-text" />
                        </td>
                    </tr>
                </table>
            </div>

            <!-- SECCIÓN: Condiciones de Visualización -->
            <div class="gdm-section">
                <h3><?php _e('Condiciones de Visualización', 'product-conditional-content'); ?></h3>
                <p class="description"><?php _e('Define cuándo se mostrará esta opción (opcional)', 'product-conditional-content'); ?></p>
                
                <table class="form-table">
                    <tr>
                        <th><label for="gdm_condicion_categorias"><?php _e('Solo en Categorías', 'product-conditional-content'); ?></label></th>
                        <td>
                            <select id="gdm_condicion_categorias" 
                                    name="gdm_condicion_categorias[]" 
                                    multiple 
                                    class="gdm-select2" 
                                    style="width:100%;max-width:500px;">
                                <?php
                                $cats = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
                                foreach ($cats as $cat) {
                                    $selected = in_array($cat->term_id, $data['condicion_categorias']) ? 'selected' : '';
                                    printf(
                                        '<option value="%d" %s>%s</option>',
                                        $cat->term_id,
                                        $selected,
                                        esc_html($cat->name)
                                    );
                                }
                                ?>
                            </select>
                            <p class="description"><?php _e('Dejar vacío para mostrar en todas las categorías', 'product-conditional-content'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * Renderizar fila de opción (para select, radio, checkbox)
     */
    private static function render_choice_row($index, $data) {
        $valor = $data['valor'] ?? '';
        $label = $data['label'] ?? '';
        $precio = $data['precio'] ?? '';
        ?>
        <tr class="gdm-choice-row" data-index="<?php echo esc_attr($index); ?>">
            <td class="handle-col">
                <span class="dashicons dashicons-menu gdm-sort-handle"></span>
            </td>
            <td>
                <input type="text" 
                       name="gdm_choices[<?php echo esc_attr($index); ?>][valor]" 
                       value="<?php echo esc_attr($valor); ?>" 
                       class="regular-text" 
                       placeholder="valor-interno" 
                       required />
            </td>
            <td>
                <input type="text" 
                       name="gdm_choices[<?php echo esc_attr($index); ?>][label]" 
                       value="<?php echo esc_attr($label); ?>" 
                       class="regular-text" 
                       placeholder="Etiqueta visible" 
                       required />
            </td>
            <td>
                <input type="text" 
                       name="gdm_choices[<?php echo esc_attr($index); ?>][precio]" 
                       value="<?php echo esc_attr($precio); ?>" 
                       class="small-text" 
                       placeholder="0" />
                <span class="currency-symbol"><?php echo get_woocommerce_currency_symbol(); ?></span>
            </td>
            <td>
                <button type="button" class="button button-small gdm-remove-choice">
                    <span class="dashicons dashicons-trash"></span>
                </button>
            </td>
        </tr>
        <?php
    }

    public static function save_metabox($post_id, $post) {
    // ✅ USAR HELPER CENTRALIZADO (con logging solo en debug)
    if (!GDM_Admin_Helpers::validate_metabox_save(
        $post_id, 
        $post, 
        'gdm_opcion_nonce', 
        'gdm_save_opcion_data', 
        'gdm_opcion'
    )) {
        return;
    }

    // ✅ DEBUG (solo si WP_DEBUG está activo)
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('=== GDM OPCIONES SAVE START ===');
        error_log('Post ID: ' . $post_id);
    }

    // Guardar campos básicos
    $fields = [
        '_gdm_opcion_slug' => isset($_POST['gdm_opcion_slug']) 
            ? sanitize_title($_POST['gdm_opcion_slug']) 
            : '',
        '_gdm_opcion_label' => isset($_POST['gdm_opcion_label']) 
            ? sanitize_text_field($_POST['gdm_opcion_label']) 
            : '',
        '_gdm_opcion_descripcion' => isset($_POST['gdm_opcion_descripcion']) 
            ? sanitize_textarea_field($_POST['gdm_opcion_descripcion']) 
            : '',
        '_gdm_opcion_tipo' => isset($_POST['gdm_opcion_tipo']) 
            ? sanitize_text_field($_POST['gdm_opcion_tipo']) 
            : 'text',
        '_gdm_opcion_required' => isset($_POST['gdm_opcion_required']) ? '1' : '0',
        '_gdm_opcion_precio_base' => isset($_POST['gdm_opcion_precio_base']) 
            ? sanitize_text_field($_POST['gdm_opcion_precio_base']) 
            : '',
        '_gdm_opcion_css_class' => isset($_POST['gdm_opcion_css_class']) 
            ? sanitize_html_class($_POST['gdm_opcion_css_class']) 
            : '',
        '_gdm_texto_placeholder' => isset($_POST['gdm_texto_placeholder']) 
            ? sanitize_text_field($_POST['gdm_texto_placeholder']) 
            : '',
        '_gdm_texto_maxlength' => isset($_POST['gdm_texto_maxlength']) 
            ? absint($_POST['gdm_texto_maxlength']) 
            : '',
        '_gdm_file_tipos' => isset($_POST['gdm_file_tipos']) 
            ? sanitize_text_field($_POST['gdm_file_tipos']) 
            : '',
        '_gdm_file_max_size' => isset($_POST['gdm_file_max_size']) 
            ? absint($_POST['gdm_file_max_size']) 
            : 5,
    ];
    
    foreach ($fields as $key => $value) {
        update_post_meta($post_id, $key, $value);
    }

    // Guardar choices
    $choices = [];
    if (isset($_POST['gdm_choices']) && is_array($_POST['gdm_choices'])) {
        foreach ($_POST['gdm_choices'] as $choice) {
            if (empty($choice['valor']) || empty($choice['label'])) {
                continue;
            }
            $choices[] = [
                'valor' => sanitize_title($choice['valor']),
                'label' => sanitize_text_field($choice['label']),
                'precio' => sanitize_text_field($choice['precio'] ?? ''),
            ];
        }
    }
    update_post_meta($post_id, '_gdm_opcion_choices', $choices);

    // ✅ USAR HELPER PARA SANITIZAR ARRAY DE ENTEROS
    $condicion_categorias = GDM_Admin_Helpers::sanitize_int_array(
        $_POST['gdm_condicion_categorias'] ?? []
    );
    update_post_meta($post_id, '_gdm_condicion_categorias', $condicion_categorias);
    
    // ✅ DEBUG (solo si WP_DEBUG está activo)
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('✅ Opción guardada exitosamente');
        error_log('Choices guardados: ' . count($choices));
        error_log('Categorías guardadas: ' . count($condicion_categorias));
        error_log('=== GDM OPCIONES SAVE END ===');
    }
    
    // ✅ Limpiar caché
    wp_cache_delete("gdm_opcion_{$post_id}", 'gdm_opciones');
}

    /**
     * Obtener datos
     */
    private static function get_opcion_data($opcion_id) {
        static $cache = [];
        
        if (isset($cache[$opcion_id])) {
            return $cache[$opcion_id];
        }

        $data = [
            'slug' => get_post_meta($opcion_id, '_gdm_opcion_slug', true),
            'label' => get_post_meta($opcion_id, '_gdm_opcion_label', true),
            'descripcion' => get_post_meta($opcion_id, '_gdm_opcion_descripcion', true),
            'tipo' => get_post_meta($opcion_id, '_gdm_opcion_tipo', true) ?: 'text',
            'required' => get_post_meta($opcion_id, '_gdm_opcion_required', true),
            'precio_base' => get_post_meta($opcion_id, '_gdm_opcion_precio_base', true),
            'css_class' => get_post_meta($opcion_id, '_gdm_opcion_css_class', true),
            'texto_placeholder' => get_post_meta($opcion_id, '_gdm_texto_placeholder', true),
            'texto_maxlength' => get_post_meta($opcion_id, '_gdm_texto_maxlength', true),
            'file_tipos' => get_post_meta($opcion_id, '_gdm_file_tipos', true),
            'file_max_size' => get_post_meta($opcion_id, '_gdm_file_max_size', true) ?: 5,
            'choices' => get_post_meta($opcion_id, '_gdm_opcion_choices', true) ?: [],
            'condicion_categorias' => get_post_meta($opcion_id, '_gdm_condicion_categorias', true) ?: [],
        ];
        
        $cache[$opcion_id] = $data;
        return $data;
    }
}

GDM_Opciones_Metabox::init();